<?php

namespace Tests\Feature\Laporan;

use App\Actions\Laporan\UnggahArsipDokumenResmi;
use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        config(['filesystems.disks.local.root' => sys_get_temp_dir().'/sakip179-'.Str::uuid()]);
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
        $this->get(self::URL.'/arsip/'.$old->id)->assertOk();
        $this->delete(self::URL.'/arsip/'.$old->id)->assertStatus(405);
        $this->unggah(['menggantikan_id' => $old->id, 'alasan_koreksi' => 'Stale'])->assertSessionHasErrors('menggantikan_id');
    }

    public function test_periode_dan_penggantian_harus_pada_induk_yang_sama(): void
    {
        config(['filesystems.disks.local.root' => sys_get_temp_dir().'/sakip179-'.Str::uuid()]);
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
        $this->unggah(['menggantikan_id' => (string) Str::uuid(), 'alasan_koreksi' => '   '])->assertSessionHasErrors('alasan_koreksi');
        $this->assertSame(0, Berkas::count());
        $this->assertSame(4, AuditLog::where('tindakan', 'berkas.unggah_ditolak')->count());
    }

    public function test_gagal_audit_membersihkan_file_baru_dan_action_memeriksa_izin(): void
    {
        $root = sys_get_temp_dir().'/sakip179-'.Str::uuid();
        config(['filesystems.disks.local.root' => $root]);
        Storage::forgetDisk('local');
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
        config(['filesystems.disks.local.root' => sys_get_temp_dir().'/sakip179-'.Str::uuid()]);
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
        config(['filesystems.disks.local.root' => sys_get_temp_dir().'/sakip179-'.Str::uuid()]);
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
}
