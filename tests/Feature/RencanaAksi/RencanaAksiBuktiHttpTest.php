<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\RencanaAksi\HapusBuktiRencanaAksi;
use App\Actions\RencanaAksi\TambahBuktiRencanaAksi;
use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\Pengaturan;
use App\Models\RencanaAksi;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

/**
 * Jalur HTTP pemenuhan bukti rencana aksi (US-05.02 AC-1..AC-4, PRD §18,
 * Workflow §10.3, Q32): izin turun dari induk dan dijawab 403, sedangkan
 * status, unit, arsip, dan jendela adalah validasi bisnis 422 (Data Model
 * §3.2 langkah 6). Pembekuan bukti dalam versi (AC-5) dibuktikan di ISS-05.03.
 */
class RencanaAksiBuktiHttpTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        Storage::fake('local');
    }

    /** @param  array<string, mixed>  $payload */
    private function kirim(User $user, array $payload): TestResponse
    {
        return $this->actingAs($user)->post(route('rencana-aksi.bukti.store', $this->rencanaAksi->id), $payload);
    }

    private function hapus(User $user, string $buktiId, string $alasan = 'Bukti tidak relevan dengan target rencana aksi.'): TestResponse
    {
        return $this->actingAs($user)->delete(route('rencana-aksi.bukti.destroy', ['rencanaAksi' => $this->rencanaAksi->id, 'bukti' => $buktiId]), ['alasan' => $alasan]);
    }

    private function unduh(User $user, string $buktiId): TestResponse
    {
        return $this->actingAs($user)->get(route('rencana-aksi.bukti.download', ['rencanaAksi' => $this->rencanaAksi->id, 'bukti' => $buktiId]));
    }

    public function test_tiga_mode_tersimpan_terhubung_ke_rencana_aksi_dan_persyaratan(): void
    {
        $jb = $this->createJenisBerkas();

        $this->kirim($this->actor, ['jenis_berkas_id' => $jb->id, 'mode' => 'file', 'file' => UploadedFile::fake()->create('kak.pdf', 200, 'application/pdf')])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->kirim($this->actor, ['jenis_berkas_id' => $jb->id, 'mode' => 'tautan', 'tautan' => 'https://lldikti16.kemdiktisaintek.go.id/kak'])
            ->assertSessionHasNoErrors();
        $this->kirim($this->actor, ['mode' => 'teks', 'isi_teks' => 'Keterangan lampiran bebas.'])
            ->assertSessionHasNoErrors();

        $bukti = $this->rencanaAksi->buktiDukungs()->orderBy('created_at')->get();
        $this->assertSame(['file', 'tautan', 'teks'], $bukti->pluck('mode')->all());
        $this->assertSame([$jb->id, $jb->id, null], $bukti->pluck('jenis_berkas_id')->all());
        $this->assertSame('kak.pdf', $bukti[0]->nama_asli);
        Storage::disk('local')->assertExists($bukti[0]->path);

        $audit = AuditLog::where('tindakan', 'berkas.unggah')->where('objek_id', $bukti[2]->id)->firstOrFail();
        $this->assertNotNull($audit->dasar_izin);
        $this->assertSame(mb_strlen('Keterangan lampiran bebas.'), $audit->nilai_baru['panjang_teks']);
        $this->assertArrayNotHasKey('isi_teks', $audit->nilai_baru);
        $auditFile = AuditLog::where('tindakan', 'berkas.unggah')->where('objek_id', $bukti[0]->id)->firstOrFail();
        $this->assertArrayNotHasKey('path', $auditFile->nilai_baru);
        $this->assertSame('kak.pdf', $auditFile->nilai_baru['nama_asli']);
    }

    public function test_persyaratan_tahap_lain_atau_indikator_lain_ditolak(): void
    {
        $pengukuran = $this->createJenisBerkas(['tahap' => 'pengukuran']);
        $indikatorLain = $this->createJenisBerkas(['indikator_id' => IndikatorKinerja::create([
            'sasaran_strategis_id' => $this->indikator->sasaran_strategis_id,
            'unit_id' => $this->unit->id,
            'kode' => 'IKU-RA-02',
            'nama' => 'Indikator Lain',
            'satuan' => 'persen',
            'tipe_perhitungan' => 'manual',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->actor->id,
            'created_by_role' => 'superadmin',
        ])->id]);

        $this->kirim($this->actor, ['jenis_berkas_id' => $pengukuran->id, 'mode' => 'teks', 'isi_teks' => 'x'])->assertSessionHasErrors('jenis_berkas_id');
        $this->kirim($this->actor, ['jenis_berkas_id' => $indikatorLain->id, 'mode' => 'teks', 'isi_teks' => 'x'])->assertSessionHasErrors('jenis_berkas_id');
        $this->assertSame(0, $this->rencanaAksi->buktiDukungs()->count());
    }

    public function test_mode_di_luar_daftar_persyaratan_ditolak(): void
    {
        $jb = $this->createJenisBerkas(['izinkan_file' => false, 'izinkan_tautan' => false]);

        $this->kirim($this->actor, ['jenis_berkas_id' => $jb->id, 'mode' => 'tautan', 'tautan' => 'https://lldikti16.kemdiktisaintek.go.id/dok'])
            ->assertSessionHasErrors('mode');
        $this->assertDatabaseMissing('berkas', ['berkasable_id' => $this->rencanaAksi->id, 'jenis_berkas_id' => $jb->id]);
    }

    public function test_tautan_non_http_dan_file_di_luar_batas_persyaratan_ditolak(): void
    {
        $jb = $this->createJenisBerkas(['ukuran_maks_kb' => 100, 'format_diizinkan' => 'pdf']);

        $this->kirim($this->actor, ['mode' => 'tautan', 'tautan' => 'ftp://arsip.internal/dokumen'])->assertSessionHasErrors('tautan');
        $this->kirim($this->actor, ['mode' => 'tautan', 'tautan' => 'javascript:alert(1)'])->assertSessionHasErrors('tautan');
        $this->kirim($this->actor, ['jenis_berkas_id' => $jb->id, 'mode' => 'file', 'file' => UploadedFile::fake()->create('besar.pdf', 300, 'application/pdf')])
            ->assertSessionHasErrors('file');
        $this->kirim($this->actor, ['jenis_berkas_id' => $jb->id, 'mode' => 'file', 'file' => UploadedFile::fake()->create('catatan.txt', 10, 'text/plain')])
            ->assertSessionHasErrors('file');
        // Karakter kontrol ditolak 422, bukan diteruskan ke PostgreSQL (galat 22021).
        $this->kirim($this->actor, ['mode' => 'teks', 'isi_teks' => "Teks\x00rusak"])->assertSessionHasErrors('isi_teks');
        $this->kirim($this->actor, ['mode' => 'teks', 'isi_teks' => str_repeat('a', 10001)])->assertSessionHasErrors('isi_teks');

        $this->assertSame(0, $this->rencanaAksi->buktiDukungs()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_saklar_unggahan_nonaktif_menolak_file_namun_teks_tetap_diterima(): void
    {
        Pengaturan::updateOrCreate(['kunci' => 'berkas.unggahan_aktif'], ['grup' => 'berkas', 'nilai' => 'false', 'tipe' => 'boolean']);
        $jb = $this->createJenisBerkas();

        $this->kirim($this->actor, ['jenis_berkas_id' => $jb->id, 'mode' => 'file', 'file' => UploadedFile::fake()->create('kak.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');
        $this->kirim($this->actor, ['jenis_berkas_id' => $jb->id, 'mode' => 'teks', 'isi_teks' => 'Keterangan pengganti unggahan.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['teks'], $this->rencanaAksi->buktiDukungs()->pluck('mode')->all());
    }

    public function test_pic_pegawai_dengan_grant_unit_dapat_memenuhi_bukti_tanpa_izin_berkas(): void
    {
        $pic = $this->createPicPegawai();
        $tanpaGrant = $this->createUserWithRole('pegawai');
        $unitLain = $this->createUserWithRole('pegawai');
        $this->grantUnitPermission($unitLain, 'rencana_aksi:update', (string) Unit::create(['nama' => 'Unit Lain', 'created_by' => $this->actor->id])->id);

        $this->kirim($pic, ['mode' => 'teks', 'isi_teks' => 'Bukti dari PIC unit.'])->assertSessionHasNoErrors();
        $this->kirim($tanpaGrant, ['mode' => 'teks', 'isi_teks' => 'Tanpa grant.'])->assertForbidden();
        $this->kirim($unitLain, ['mode' => 'teks', 'isi_teks' => 'Grant unit lain.'])->assertForbidden();

        $bukti = $this->rencanaAksi->buktiDukungs()->sole();
        $this->assertSame($pic->id, $bukti->uploaded_by);
        $this->hapus($pic, $bukti->id)->assertSessionHasNoErrors();
        $this->assertNotNull($bukti->fresh()->dihapus_pada);
    }

    public function test_explicit_deny_menang_termasuk_untuk_superadmin(): void
    {
        $this->denyPermission($this->actor, 'berkas:upload');
        $this->kirim($this->actor, ['mode' => 'teks', 'isi_teks' => 'Ditolak deny berkas.'])->assertForbidden();
        $tolak = AuditLog::where('tindakan', 'berkas.unggah_ditolak')->where('objek_id', $this->rencanaAksi->id)->sole();
        $this->assertSame('berkas:upload', $tolak->dasar_izin['permission']);
        $this->assertSame('ditolak', $tolak->dasar_izin['keputusan']);

        $perencanaan = $this->createUserWithRole('perencanaan');
        $this->denyPermission($perencanaan, 'rencana_aksi:update');
        $this->kirim($perencanaan, ['mode' => 'teks', 'isi_teks' => 'Ditolak deny induk.'])->assertForbidden();

        $bukti = $this->createBuktiDukung();
        $this->denyPermission($this->actor, 'berkas:delete');
        $this->hapus($this->actor, $bukti->id)->assertForbidden();
        $this->assertNull($bukti->fresh()->dihapus_pada);
        $this->assertSame(0, $this->rencanaAksi->buktiDukungs()->where('mode', 'teks')->count());
    }

    public function test_pic_di_luar_jendela_ditolak_sedangkan_jalur_perencanaan_tetap_boleh(): void
    {
        $hariIni = today(config('app.business_timezone'));
        $this->jadwal->update(['rencana_aksi_mulai' => $hariIni->copy()->subDays(3)->toDateString(), 'rencana_aksi_selesai' => $hariIni->copy()->subDays(2)->toDateString()]);
        $pic = $this->createPicPegawai();

        $this->kirim($pic, ['mode' => 'teks', 'isi_teks' => 'Terlambat.'])->assertSessionHasErrors('jendela');
        $this->kirim($this->actor, ['mode' => 'teks', 'isi_teks' => 'Perencanaan tanpa batas jendela.'])->assertSessionHasNoErrors();
        $this->assertTrue(AuditLog::where('tindakan', 'berkas.unggah_ditolak')->where('actor_id', $pic->id)->exists());
    }

    public function test_status_di_luar_draf_ditolak_sebagai_validasi_bisnis_bukan_izin(): void
    {
        $bukti = $this->createBuktiDukung();

        foreach ([RencanaAksi::STATUS_DIAJUKAN, RencanaAksi::STATUS_DIVERIFIKASI, RencanaAksi::STATUS_DISAHKAN] as $status) {
            $this->rencanaAksi->update(['status_alur' => $status]);
            $this->kirim($this->actor, ['mode' => 'teks', 'isi_teks' => "Pada status {$status}."])->assertSessionHasErrors('status_alur');
            $this->hapus($this->actor, $bukti->id)->assertSessionHasErrors('status_alur');
        }
        $this->assertNull($bukti->fresh()->dihapus_pada);
        $this->assertSame(1, $this->rencanaAksi->buktiDukungs()->count());

        $this->rencanaAksi->update(['status_alur' => RencanaAksi::STATUS_DIKEMBALIKAN]);
        $this->kirim($this->actor, ['mode' => 'teks', 'isi_teks' => 'Revisi setelah dikembalikan.'])->assertSessionHasNoErrors();
    }

    public function test_unit_nonaktif_atau_indikator_arsip_menolak_mutasi_jalur_peran(): void
    {
        $bukti = $this->createBuktiDukung();

        Unit::whereKey($this->unit->id)->update(['status' => 'nonaktif']);
        $this->kirim($this->actor, ['mode' => 'teks', 'isi_teks' => 'Unit nonaktif.'])->assertSessionHasErrors('unit_id');
        $this->hapus($this->actor, $bukti->id)->assertSessionHasErrors('unit_id');
        Unit::whereKey($this->unit->id)->update(['status' => 'aktif']);

        $this->indikator->update(['status' => IndikatorKinerja::STATUS_ARSIP]);
        $this->kirim($this->actor, ['mode' => 'teks', 'isi_teks' => 'Indikator arsip.'])->assertSessionHasErrors('indikator_id');
        $this->hapus($this->actor, $bukti->id)->assertSessionHasErrors('indikator_id');
        $this->actingAs($this->actor)->get(route('rencana-aksi.show', $this->rencanaAksi->id))
            ->assertInertia(fn ($page) => $page->where('bukti.can.upload', false)->where('bukti.can.delete', false));

        $this->assertNull($bukti->fresh()->dihapus_pada);
        $this->assertSame(1, $this->rencanaAksi->buktiDukungs()->count());
    }

    public function test_aksi_memeriksa_ulang_izin_dan_status_pada_state_terkunci(): void
    {
        // Izin dicabut setelah lolos gerbang awal: Action tetap menolak tanpa baris maupun file.
        $this->denyPermission($this->actor, 'berkas:upload');
        try {
            app(TambahBuktiRencanaAksi::class)->handle($this->actor, $this->rencanaAksi->id, ['mode' => 'file', 'file' => UploadedFile::fake()->create('kak.pdf', 10, 'application/pdf')]);
            $this->fail('AuthorizationException seharusnya dilempar.');
        } catch (AuthorizationException) {
        }
        $this->assertSame(0, BuktiDukung::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $tolakUnggah = AuditLog::where('tindakan', 'berkas.unggah_ditolak')->sole();
        $this->assertSame('berkas:upload', $tolakUnggah->dasar_izin['permission']);
        $this->assertSame('ditolak', $tolakUnggah->dasar_izin['keputusan']);

        // Status berubah setelah halaman dimuat: hapus ditolak 422 dan bukti tetap utuh.
        $bukti = $this->createBuktiDukung();
        $this->rencanaAksi->update(['status_alur' => RencanaAksi::STATUS_DIAJUKAN]);
        try {
            app(HapusBuktiRencanaAksi::class)->handle($this->actor, $this->rencanaAksi->id, $bukti->id, 'Alasan uji status.');
            $this->fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status_alur', $exception->errors());
        }
        $this->assertNull($bukti->fresh()->dihapus_pada);
        $tolakHapus = AuditLog::where('tindakan', 'berkas.hapus_ditolak')->sole();
        $this->assertSame('rencana_aksi:update', $tolakHapus->dasar_izin['permission']);
        $this->assertSame('diizinkan', $tolakHapus->dasar_izin['keputusan']);
    }

    public function test_unduh_file_mengikuti_akses_induk_dan_menolak_bukti_induk_lain(): void
    {
        Storage::disk('local')->put('berkas/rencana_aksi/uji.pdf', 'Konten PDF Rahasia');
        $bukti = $this->createBuktiDukung(['mode' => 'file', 'tautan' => null, 'nama_asli' => 'uji.pdf', 'path' => 'berkas/rencana_aksi/uji.pdf', 'mime' => 'application/pdf', 'ukuran_bytes' => 18]);
        $milikLain = $this->createBuktiDukung(['berkasable_type' => 'pengukuran', 'berkasable_id' => (string) Str::uuid()]);

        $respons = $this->unduh($this->actor, $bukti->id)->assertOk();
        $this->assertSame('Konten PDF Rahasia', $respons->streamedContent());
        $this->assertStringContainsString('no-store', (string) $respons->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $respons->headers->get('Cache-Control'));
        $this->unduh($this->actor, $milikLain->id)->assertNotFound();

        $pic = $this->createPicPegawai();
        $this->unduh($pic, $bukti->id)->assertOk();

        $this->denyPermission($pic, 'berkas:read');
        $this->unduh($pic, $bukti->id)->assertForbidden();
    }

    /**
     * Penghapusan non-destruktif: baris hanya soft delete dan file fisik tetap
     * ada, sehingga versi pengajuan (ISS-05.03) yang merujuknya tidak putus.
     */
    public function test_hapus_soft_delete_beralasan_teraudit_dan_mempertahankan_file(): void
    {
        Storage::disk('local')->put('berkas/rencana_aksi/beku.pdf', 'isi');
        $bukti = $this->createBuktiDukung(['mode' => 'file', 'tautan' => null, 'nama_asli' => 'beku.pdf', 'path' => 'berkas/rencana_aksi/beku.pdf', 'mime' => 'application/pdf', 'ukuran_bytes' => 3]);
        $this->rencanaAksi->update(['status_alur' => RencanaAksi::STATUS_DIKEMBALIKAN]);

        $this->hapus($this->actor, $bukti->id, 'Dokumen salah unggah.')->assertSessionHasNoErrors()->assertRedirect();

        $segar = $bukti->fresh();
        $this->assertNotNull($segar->dihapus_pada);
        $this->assertSame($this->actor->id, $segar->dihapus_oleh);
        $this->assertSame('berkas/rencana_aksi/beku.pdf', $segar->path);
        Storage::disk('local')->assertExists('berkas/rencana_aksi/beku.pdf');
        $this->assertSame(0, $this->rencanaAksi->buktiDukungs()->current()->count());

        $audit = AuditLog::where('tindakan', 'berkas.hapus')->where('objek_id', $bukti->id)->firstOrFail();
        $this->assertSame('Dokumen salah unggah.', $audit->alasan);
        $this->assertNotNull($audit->dasar_izin);
        $this->assertArrayNotHasKey('path', $audit->nilai_lama);

        $this->hapus($this->actor, $bukti->id)->assertNotFound();
    }

    public function test_hapus_bukti_induk_lain_dijawab_404_dan_alasan_wajib(): void
    {
        $milikLain = $this->createBuktiDukung(['berkasable_type' => 'pengukuran', 'berkasable_id' => (string) Str::uuid()]);
        $bukti = $this->createBuktiDukung();

        $this->hapus($this->actor, $milikLain->id)->assertNotFound();
        $this->hapus($this->actor, $bukti->id, '')->assertSessionHasErrors('alasan');
        // Alasan rusak ditolak, bukan diganti diam-diam oleh teks bawaan audit.
        $this->hapus($this->actor, $bukti->id, "Alasan\x01 rusak")->assertSessionHasErrors('alasan');
        $this->assertNull($milikLain->fresh()->dihapus_pada);
        $this->assertNull($bukti->fresh()->dihapus_pada);
    }

    public function test_kegagalan_transaksi_tidak_meninggalkan_file_yatim(): void
    {
        $this->mock(AuditLogger::class, fn ($mock) => $mock->shouldReceive('catat')->andThrow(new RuntimeException('audit gagal')));
        $this->withoutExceptionHandling();

        try {
            $this->kirim($this->actor, ['mode' => 'file', 'file' => UploadedFile::fake()->create('yatim.pdf', 10, 'application/pdf')]);
            $this->fail('Pengecualian audit seharusnya diteruskan.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit gagal', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, BuktiDukung::count());
    }

    public function test_halaman_rencana_aksi_memuat_props_bukti_sesuai_capability(): void
    {
        $jb = $this->createJenisBerkas();
        $this->createBuktiDukung(['jenis_berkas_id' => $jb->id]);

        $this->actingAs($this->actor)->get(route('rencana-aksi.show', $this->rencanaAksi->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('RencanaAksi/Show')
                ->where('bukti.ringkasan.lengkap', true)
                ->where('bukti.persyaratan.0.id', $jb->id)
                ->where('bukti.daftar.0.nama_persyaratan', $jb->nama)
                ->missing('bukti.daftar.0.path')
                ->where('bukti.can.upload', true)
                ->where('bukti.can.delete', true));

        $this->rencanaAksi->update(['status_alur' => RencanaAksi::STATUS_DIAJUKAN]);
        $this->actingAs($this->actor)->get(route('rencana-aksi.show', $this->rencanaAksi->id))
            ->assertInertia(fn ($page) => $page->where('bukti.can.upload', false)->where('bukti.can.delete', false));

        $pembaca = $this->createUserWithRole('pegawai');
        $this->denyPermission($pembaca, 'berkas:read');
        $this->actingAs($pembaca)->get(route('rencana-aksi.show', $this->rencanaAksi->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('bukti', null));
    }

    public function test_capability_mengikuti_pj_efektif_dan_jendela_penyusunan(): void
    {
        $lihatCan = fn (User $user, bool $boleh) => $this->actingAs($user)->get(route('rencana-aksi.show', $this->rencanaAksi->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('bukti.can.upload', $boleh)->where('bukti.can.delete', $boleh));

        $pic = $this->createPicPegawai();
        $bukanPj = $this->createUserWithRole('pegawai');
        $this->grantUnitPermission($bukanPj, 'rencana_aksi:update');

        $lihatCan($pic, true);
        // Grant unit tanpa penugasan PJ efektif ditolak gerbang jendela, jadi tombol tidak ditawarkan.
        $lihatCan($bukanPj, false);

        $hariIni = today(config('app.business_timezone'));
        $this->jadwal->update(['rencana_aksi_mulai' => $hariIni->copy()->subDays(3)->toDateString(), 'rencana_aksi_selesai' => $hariIni->copy()->subDays(2)->toDateString()]);
        $lihatCan($pic, false);
        $lihatCan($this->actor, true);
    }

    public function test_membuka_halaman_tanpa_hak_mutasi_tidak_mencatat_percobaan_ditolak(): void
    {
        $pimpinan = $this->createUserWithRole('pimpinan');
        $terdeny = $this->createUserWithRole('perencanaan');
        $this->denyPermission($terdeny, 'berkas:upload');
        $auditAwal = AuditLog::count();

        foreach ([$pimpinan, $terdeny] as $user) {
            $this->actingAs($user)->get(route('rencana-aksi.show', $this->rencanaAksi->id))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('bukti.can.upload', false));
        }

        // Kunjungan biasa bukan percobaan unggah/hapus; hanya request mutasi yang diaudit.
        $this->assertSame($auditAwal, AuditLog::count());
    }
}
