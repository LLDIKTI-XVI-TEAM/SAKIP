<?php

namespace Tests\Feature\Laporan;

use App\Actions\Laporan\UnggahArsipDokumenResmi;
use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\Unit;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Laporan\ArsipDokumenResmi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class ArsipDokumenResmiTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private const URL = '/laporan/dokumen-resmi';

    private function unggah(array $data = [])
    {
        return $this->actingAs($this->actor)->post(self::URL.'/arsip', [
            'jadwal_id' => $this->jadwal->id,
            'file' => UploadedFile::fake()->create('resmi.pdf', 10, 'application/pdf'),
            'keterangan' => 'Dokumen sintetis', ...$data,
        ]);
    }

    public function test_arsip_ditutup_append_only_dan_tidak_mengubah_data_kinerja(): void
    {
        Storage::fake('local');
        $this->jadwal->update(['status' => 'ditutup']);
        $before = [$this->jadwal->fresh()->getAttributes(), $this->plan->fresh()->getAttributes(), $this->planVersion->fresh()->getAttributes(), $this->pengukuran->fresh()->getAttributes()];
        $this->unggah()->assertRedirect();
        $old = Berkas::where('berkasable_type', 'jadwal_tahunan')->firstOrFail();
        $this->unggah(['menggantikan_id' => $old->id, 'alasan_koreksi' => 'Halaman diperbaiki'])->assertRedirect();
        $this->assertSame(2, Berkas::where('berkasable_id', $this->jadwal->id)->count());
        $this->assertSame($before, [$this->jadwal->fresh()->getAttributes(), $this->plan->fresh()->getAttributes(), $this->planVersion->fresh()->getAttributes(), $this->pengukuran->fresh()->getAttributes()]);
        $this->assertSame(2, AuditLog::where('tindakan', 'berkas.unggah')->count());
        $this->get(self::URL)->assertOk()->assertInertia(fn ($p) => $p
            ->where('arsip.data.0.digantikan', false)->where('arsip.data.1.digantikan', true));
        $download = $this->get(self::URL.'/arsip/'.$old->id)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDownload('resmi.pdf');
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $download->headers->get('Cache-Control'));
        $this->delete(self::URL.'/arsip/'.$old->id)->assertStatus(405);
        $this->unggah(['menggantikan_id' => $old->id, 'alasan_koreksi' => 'Stale'])->assertSessionHasErrors('menggantikan_id');
    }

    public function test_periode_dan_penggantian_harus_pada_induk_yang_sama(): void
    {
        Storage::fake('local');
        $this->unggah()->assertRedirect();
        $old = Berkas::where('berkasable_type', 'jadwal_tahunan')->firstOrFail();
        $period = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
        $this->unggah(['periode_jadwal_id' => $period->id])->assertRedirect();
        $this->assertDatabaseHas('berkas', ['berkasable_type' => 'jadwal_periode', 'berkasable_id' => $period->id]);
        $this->unggah(['periode_jadwal_id' => $period->id, 'menggantikan_id' => $old->id, 'alasan_koreksi' => 'Lintas induk'])->assertSessionHasErrors('menggantikan_id');
        $this->unggah(['periode_jadwal_id' => (string) Str::uuid()])->assertNotFound();
    }

    public function test_validasi_pdf_dan_metadata_dan_audit_penolakan(): void
    {
        $this->unggah(['file' => UploadedFile::fake()->create('salah.docx', 10)])->assertSessionHasErrors('file');
        $this->unggah(['keterangan' => str_repeat('x', 1001)])->assertSessionHasErrors('keterangan');
        $this->unggah(['file' => UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('file');
        $this->unggah(['menggantikan_id' => (string) Str::uuid(), 'alasan_koreksi' => '   '])
            ->assertSessionHasErrors(['alasan_koreksi' => 'Alasan penggantian wajib diisi.']);
        $this->assertSame(0, Berkas::count());
        $this->assertSame(4, AuditLog::where('tindakan', 'berkas.unggah_ditolak')->count());
    }

    public function test_gagal_audit_membersihkan_file_baru_dan_action_memeriksa_izin(): void
    {
        Storage::fake('local');
        $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andThrow(new \RuntimeException('Audit gagal sintetis'));
        try {
            app(UnggahArsipDokumenResmi::class)->handle($this->actor, ['jadwal_id' => $this->jadwal->id, 'file' => UploadedFile::fake()->create('resmi.pdf', 10, 'application/pdf')]);
            $this->fail('Kegagalan audit seharusnya diteruskan.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Audit gagal sintetis', $e->getMessage());
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, Berkas::count());
    }

    public function test_action_langsung_tetap_menolak_aktor_tanpa_izin(): void
    {
        $this->expectException(AuthorizationException::class);
        app(UnggahArsipDokumenResmi::class)->handle($this->userWithRole('pimpinan'), ['jadwal_id' => $this->jadwal->id]);
    }

    public function test_database_menolak_perubahan_dan_penghapusan_arsip(): void
    {
        Storage::fake('local');
        $this->unggah()->assertRedirect();
        $berkas = Berkas::firstOrFail();
        foreach (['update', 'delete'] as $intent) {
            try {
                DB::transaction(function () use ($berkas, $intent) {
                    if ($intent === 'update') {
                        DB::table('berkas')->where('id', $berkas->id)->update(['keterangan' => 'Ditimpa']);
                    } else {
                        DB::table('berkas')->where('id', $berkas->id)->delete();
                    }
                });
                $this->fail('Arsip harus tetap.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('Arsip dokumen resmi', $e->getMessage());
            }
        }
        $this->assertSame('Dokumen sintetis', $berkas->fresh()->keterangan);
    }

    public function test_izin_dan_deny_unit_menolak_unduh_tanpa_audit_penolakan(): void
    {
        Storage::fake('local');
        $this->unggah()->assertRedirect();
        $file = Berkas::firstOrFail();
        $count = AuditLog::count();
        foreach (['laporan:read', 'laporan:ekspor', 'rencana_aksi:read', 'kegiatan:read'] as $code) {
            $id = (string) Str::uuid();
            DB::table('user_permission_denied')->insert(['id' => $id, 'user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', $code)->value('id'), 'unit_id' => $this->unit->id, 'alasan' => 'Uji deny', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
            $this->get(self::URL.'/arsip/'.$file->id)->assertForbidden();
            $this->get(self::URL)->assertOk()->assertInertia(fn ($p) => $p->where('can.unduh', false));
            DB::table('user_permission_denied')->where('id', $id)->delete();
        }
        $this->assertSame($count, AuditLog::count());
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get(self::URL)->assertForbidden();
        $this->actingAs($admin)->post(self::URL.'/arsip', ['jadwal_id' => $this->jadwal->id])->assertForbidden();
        $pimpinan = $this->userWithRole('pimpinan');
        $this->actingAs($pimpinan)->get(self::URL)->assertOk()->assertInertia(fn ($p) => $p->where('can.unggah', false));
    }

    public function test_alasan_pengganti_menolak_karakter_kontrol_di_http_dan_action(): void
    {
        Storage::fake('local');
        $this->unggah()->assertRedirect();
        $old = Berkas::firstOrFail();
        $invalid = ['menggantikan_id' => $old->id, 'alasan_koreksi' => "Halaman\x01 tertukar"];
        $this->unggah($invalid)->assertSessionHasErrors('alasan_koreksi');
        try {
            app(UnggahArsipDokumenResmi::class)->handle($this->actor, $invalid + [
                'jadwal_id' => $this->jadwal->id, 'file' => UploadedFile::fake()->create('pengganti.pdf', 10, 'application/pdf'),
            ]);
            $this->fail('Action harus menolak alasan dengan karakter kontrol.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('alasan_koreksi', $exception->errors());
        }
        $this->assertDatabaseCount('berkas', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertSame(2, AuditLog::where('tindakan', 'berkas.unggah_ditolak')->count());

        $valid = "Perbaikan\tlembar\nlampiran.";
        $this->unggah(['menggantikan_id' => $old->id, 'alasan_koreksi' => '  '.$valid.'  '])->assertRedirect();
        $replacement = Berkas::where('menggantikan_id', $old->id)->sole();
        $this->assertSame($valid, $replacement->alasan_koreksi);
        $audit = AuditLog::where('tindakan', 'berkas.unggah')->where('objek_id', $replacement->id)->sole();
        $this->assertSame($valid, $audit->alasan);
        $this->assertSame('laporan:unggah', $audit->dasar_izin['permission']);
        $this->assertSame('diizinkan', $audit->dasar_izin['keputusan']);
    }

    public function test_keterangan_menolak_teks_rusak_di_http_dan_action(): void
    {
        Storage::fake('local');
        foreach (["Arsip\x01tambahan", "Arsip\0tambahan", "Arsip\xB1tambahan"] as $keterangan) {
            $this->unggah(['keterangan' => $keterangan])->assertSessionHasErrors('keterangan');
            try {
                app(UnggahArsipDokumenResmi::class)->handle($this->actor, [
                    'jadwal_id' => $this->jadwal->id, 'file' => UploadedFile::fake()->create('resmi.pdf', 10, 'application/pdf'),
                    'keterangan' => $keterangan,
                ]);
                $this->fail('Action harus menolak keterangan dengan teks rusak atau karakter kontrol ilegal.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('keterangan', $exception->errors());
            }
        }
        $this->assertDatabaseCount('berkas', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $valid = "Arsip\tperubahan\ntriwulan.";
        $this->unggah(['keterangan' => $valid])->assertRedirect();
        $berkas = Berkas::sole();
        $this->assertSame($valid, $berkas->keterangan);
        $audit = AuditLog::where('tindakan', 'berkas.unggah')->where('objek_id', $berkas->id)->sole();
        $this->assertSame($valid, $audit->nilai_baru['keterangan']);
    }

    public function test_baca_unit_terbatas_menolak_unduh_dokumen_lembaga_tanpa_audit_penolakan(): void
    {
        Storage::fake('local');
        $this->unggah()->assertRedirect();
        $file = Berkas::firstOrFail();
        Unit::create(['nama' => 'Unit lain aktif', 'status' => 'aktif', 'created_by' => $this->actor->id]);
        $pegawai = $this->userWithRole('pegawai');
        foreach (['laporan:read', 'laporan:ekspor', 'pengukuran:create'] as $code) {
            DB::table('user_permission_granted')->insert([
                'id' => Str::uuid(), 'user_id' => $pegawai->id, 'permission_id' => Permission::where('kode', $code)->value('id'),
                'unit_id' => $code === 'pengukuran:create' ? $this->unit->id : null,
                'alasan' => 'Grant sintetis', 'diberikan_oleh' => $this->actor->id, 'created_at' => now(),
            ]);
        }
        $izin = app(PermissionResolver::class);
        foreach (['laporan:read', 'laporan:ekspor', 'rencana_aksi:read', 'kegiatan:read'] as $code) {
            $this->assertTrue($izin->allows($pegawai, $code));
            $this->assertFalse($izin->unitDitolak($pegawai, $code)->exists());
        }
        $this->assertNotNull($izin->unitTerbaca($pegawai, 'rencana_aksi:read'));
        $before = AuditLog::count();
        $this->actingAs($this->actor)->get(self::URL.'/arsip/'.$file->id)->assertOk();
        $this->actingAs($pegawai)->get(self::URL.'/arsip/'.$file->id)->assertForbidden();
        $this->get(self::URL)->assertOk()->assertInertia(fn ($p) => $p
            ->where('can.unduh', false)->where('alasan_unduh', ArsipDokumenResmi::AKSES_DIBATASI));
        $this->assertSame($before, AuditLog::count());
    }
}
