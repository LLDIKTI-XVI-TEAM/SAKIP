<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\AuditLog;
use App\Models\JenisBerkas;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * ISS-11.05 AC-2..AC-4: persyaratan bukti dibekukan pada versi pengajuan.
 * Perubahan master `jenis_berkas` hanya berlaku pada pengajuan berikutnya,
 * sedangkan hasil yang sudah disahkan tidak otomatis dibatalkan (Q24).
 */
class PembekuanPersyaratanBuktiTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // Waktu beku eksplisit agar fixture manual dan draf berada pada jadwal aktif yang sama.
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
    }

    public function test_perubahan_master_jenis_berkas_setelah_submit_tidak_mengubah_versi(): void
    {
        $fixture = $this->buatFixtureManual();
        $fixture['jadwal']->update(['activated_at' => '2026-02-15 09:00:00']);
        $jenis = $this->buatPersyaratanWajib($fixture);
        $header = $this->drafManual($fixture);
        $this->tambahBuktiTeks($fixture, $header, $jenis, 'Kerangka acuan kerja versi awal.');
        $this->ajukan($fixture, $header)->assertSessionHasNoErrors();

        $versi = RencanaAksiVersi::where('rencana_aksi_id', $header->id)->sole();
        $snapshotAwal = $versi->snapshot;
        $persyaratanAwal = $snapshotAwal['persyaratan_bukti'];
        $this->assertCount(1, $persyaratanAwal);
        $this->assertSame('KAK', $persyaratanAwal[0]['nama']);
        $this->assertTrue($persyaratanAwal[0]['wajib']);
        $this->assertFalse($persyaratanAwal[0]['semua_mode_wajib']);
        $this->assertTrue($persyaratanAwal[0]['izinkan_file']);
        $this->assertFalse($persyaratanAwal[0]['izinkan_tautan']);
        $this->assertTrue($persyaratanAwal[0]['izinkan_teks']);

        $reviewSebelum = $this->propsReview($fixture, $header);
        $this->assertSame(RencanaAksi::STATUS_DIAJUKAN, $reviewSebelum['status_alur']);
        $this->assertSame(1, $reviewSebelum['versi_pengajuan']['nomor']);
        $this->assertTrue($reviewSebelum['can']['verifikasi']);

        $jenis->update([
            'nama' => 'KAK Revisi Setelah Submit',
            'wajib' => false,
            'semua_mode_wajib' => true,
            'izinkan_file' => false,
            'izinkan_tautan' => true,
            'izinkan_teks' => true,
            'format_diizinkan' => 'pdf',
            'ukuran_maks_kb' => 2048,
        ]);
        $jenis->refresh();
        $this->assertSame('KAK Revisi Setelah Submit', $jenis->nama);
        $this->assertFalse($jenis->izinkan_file);

        $this->assertSame($snapshotAwal, $versi->fresh()->snapshot);
        $this->assertSame($persyaratanAwal, $versi->fresh()->snapshot['persyaratan_bukti']);
        $this->assertSame($reviewSebelum, $this->propsReview($fixture, $header));
        $this->assertSame(RencanaAksi::STATUS_DIAJUKAN, $header->fresh()->status_alur);
    }

    public function test_ketentuan_baru_setelah_submit_dikembalikan_beralasan_dan_diajukan_ulang_sebagai_versi_baru(): void
    {
        $fixture = $this->buatFixtureManual();
        $fixture['jadwal']->update(['activated_at' => '2026-02-15 09:00:00']);
        $jenisAwal = $this->buatPersyaratanWajib($fixture);
        $header = $this->drafManual($fixture);
        $this->tambahBuktiTeks($fixture, $header, $jenisAwal, 'Kerangka acuan kerja versi awal.');
        $this->ajukan($fixture, $header)->assertSessionHasNoErrors();

        $versiSatu = RencanaAksiVersi::where('rencana_aksi_id', $header->id)->sole();
        $snapshotSatu = $versiSatu->snapshot;
        $this->assertSame(['KAK'], array_column($snapshotSatu['persyaratan_bukti'], 'nama'));

        $alasan = 'Ketentuan bukti baru ditetapkan setelah pengajuan versi 1.';
        $this->actingAs($fixture['perencanaan'])->postJson("/rencana-aksi/{$header->id}/kembalikan", [
            'expected_versi' => $header->fresh()->versi,
            'versi_pengajuan_id' => $versiSatu->id,
            'alasan' => $alasan,
        ])->assertOk();

        $this->assertSame(RencanaAksi::STATUS_DIKEMBALIKAN, $header->fresh()->status_alur);
        $auditKembali = AuditLog::where('tindakan', 'rencana_aksi.kembalikan')->sole();
        $this->assertSame($alasan, $auditKembali->alasan);
        $this->assertSame($versiSatu->id, $auditKembali->nilai_baru['versi_pengajuan_id']);
        $this->assertSame($snapshotSatu, $versiSatu->fresh()->snapshot);

        $jenisBaru = $this->buatPersyaratanWajib($fixture, [
            'nama' => 'Laporan Tambahan',
            'urutan' => 2,
            'izinkan_file' => false,
            'izinkan_tautan' => false,
            'izinkan_teks' => true,
        ]);
        $this->tambahBuktiTeks($fixture, $header, $jenisBaru, 'Laporan tambahan setelah pengembalian.');

        $this->ajukan($fixture, $header)->assertSessionHasNoErrors();

        $this->assertSame([1, 2], RencanaAksiVersi::where('rencana_aksi_id', $header->id)->orderBy('nomor')->pluck('nomor')->all());
        $versiDua = RencanaAksiVersi::where('rencana_aksi_id', $header->id)->where('nomor', 2)->sole();
        $this->assertSame(['KAK', 'Laporan Tambahan'], array_column($versiDua->snapshot['persyaratan_bukti'], 'nama'));
        $this->assertSame($snapshotSatu, $versiSatu->fresh()->snapshot);
        $this->assertSame(RencanaAksi::STATUS_DIAJUKAN, $header->fresh()->status_alur);
    }

    public function test_pengesahan_lama_tetap_sah_setelah_persyaratan_berubah(): void
    {
        $fixture = $this->buatFixtureManual();
        $fixture['jadwal']->update(['activated_at' => '2026-02-15 09:00:00']);
        $jenis = $this->buatPersyaratanWajib($fixture);
        $header = $this->drafManual($fixture);
        $this->tambahBuktiTeks($fixture, $header, $jenis, 'Kerangka acuan kerja versi awal.');
        $this->ajukan($fixture, $header)->assertSessionHasNoErrors();

        $versi = RencanaAksiVersi::where('rencana_aksi_id', $header->id)->sole();
        $this->verifikasi($fixture, $header, $versi)->assertOk();
        $this->sahkan($fixture, $header, $versi)->assertOk();

        $header->refresh();
        $versi->refresh();
        $this->assertSame(RencanaAksi::STATUS_DISAHKAN, $header->status_alur);
        $this->assertNotNull($header->disahkan_at);
        $headerDisahkanAt = $header->disahkan_at->toIso8601String();
        $versiDisahkanAt = $versi->disahkan_at?->toIso8601String();
        $this->assertNotNull($versiDisahkanAt);
        $snapshotSah = $versi->snapshot;
        $versiHeaderSah = $header->versi;

        $jenis->update([
            'nama' => 'KAK Revisi Setelah Sah',
            'wajib' => false,
            'semua_mode_wajib' => true,
            'izinkan_file' => false,
            'izinkan_tautan' => true,
            'izinkan_teks' => true,
        ]);
        $this->buatPersyaratanWajib($fixture, [
            'nama' => 'Laporan Setelah Sah',
            'urutan' => 2,
            'izinkan_file' => false,
            'izinkan_tautan' => false,
            'izinkan_teks' => true,
        ]);

        $header->refresh();
        $versi->refresh();
        $this->assertSame(RencanaAksi::STATUS_DISAHKAN, $header->status_alur);
        $this->assertSame($headerDisahkanAt, $header->disahkan_at?->toIso8601String());
        $this->assertSame($versiHeaderSah, $header->versi);
        $this->assertSame($versiDisahkanAt, $versi->disahkan_at?->toIso8601String());
        $this->assertSame($snapshotSah, $versi->snapshot);
        $this->assertSame(1, RencanaAksiVersi::where('rencana_aksi_id', $header->id)->count());
        $this->assertSame(['KAK'], array_column($snapshotSah['persyaratan_bukti'], 'nama'));

        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}/review")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rencanaAksi.status_alur', 'disahkan')
                ->where('rencanaAksi.versi_pengajuan.nomor', 1)
                ->where('rencanaAksi.pengesahan.pada', $versiDisahkanAt)
                ->where('rencanaAksi.can.sahkan', false));
    }

    /** @param  array<string, mixed>  $fixture */
    private function ajukan(array $fixture, RencanaAksi $header): TestResponse
    {
        return $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", [
            'expected_versi' => $header->fresh()->versi,
        ]);
    }

    /** @param  array<string, mixed>  $fixture */
    private function tambahBuktiTeks(array $fixture, RencanaAksi $header, JenisBerkas $jenis, string $isiTeks): void
    {
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/bukti", [
            'jenis_berkas_id' => $jenis->id,
            'mode' => 'teks',
            'isi_teks' => $isiTeks,
        ])->assertSessionHasNoErrors();
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function propsReview(array $fixture, RencanaAksi $header): array
    {
        return $this->actingAs($fixture['perencanaan'])
            ->get("/rencana-aksi/{$header->id}/review")
            ->assertOk()
            ->viewData('page')['props']['rencanaAksi'];
    }

    /** @param  array<string, mixed>  $fixture */
    private function verifikasi(array $fixture, RencanaAksi $header, RencanaAksiVersi $versi): TestResponse
    {
        return $this->actingAs($fixture['perencanaan'])->postJson("/rencana-aksi/{$header->id}/verifikasi", [
            'expected_versi' => $header->fresh()->versi,
            'versi_pengajuan_id' => $versi->id,
        ]);
    }

    /** @param  array<string, mixed>  $fixture */
    private function sahkan(array $fixture, RencanaAksi $header, RencanaAksiVersi $versi): TestResponse
    {
        return $this->actingAs($fixture['perencanaan'])->postJson("/rencana-aksi/{$header->id}/sahkan", [
            'expected_versi' => $header->fresh()->versi,
            'versi_pengajuan_id' => $versi->id,
        ]);
    }
}
