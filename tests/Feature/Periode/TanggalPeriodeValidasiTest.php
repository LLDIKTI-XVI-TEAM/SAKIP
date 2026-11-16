<?php

namespace Tests\Feature\Periode;

use App\Models\AuditLog;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\UserPermissionDeny;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\JadwalFixtures;
use Tests\TestCase;

/** Q70 §70.1 butir 1–2: periode aktif wajib bertanggal dan tidak tumpang tindih dengan periode aktif lain. */
class TanggalPeriodeValidasiTest extends TestCase
{
    use JadwalFixtures, RefreshDatabase;

    private const KOSONG = ['bulan_mulai' => null, 'hari_mulai' => null, 'bulan_selesai' => null, 'hari_selesai' => null];

    /**
     * Triwulan I baru; fixture final aktif calendarMaster() memakai rentang 1 Oktober–31 Desember.
     *
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function payload(array $ubah = []): array
    {
        return ['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false,
            'bulan_mulai' => 1, 'hari_mulai' => 1, 'bulan_selesai' => 3, 'hari_selesai' => 31, ...$ubah];
    }

    /** @return array<string, int|null> */
    private function tanggalTersimpan(string $id): array
    {
        return (array) DB::table('periode')->where('id', $id)->first(array_keys(self::KOSONG));
    }

    public function test_periode_aktif_tanpa_tanggal_ditolak_dan_periode_nonaktif_boleh_tanpa_tanggal(): void
    {
        $actor = $this->calendarActor();
        $this->calendarMaster();

        $this->actingAs($actor)->post('/periode', $this->payload(self::KOSONG))->assertSessionHasErrors([
            'bulan_mulai' => 'Bulan mulai wajib diisi untuk periode aktif.',
            'hari_mulai' => 'Tanggal mulai wajib diisi untuk periode aktif.',
            'bulan_selesai' => 'Bulan selesai wajib diisi untuk periode aktif.',
            'hari_selesai' => 'Tanggal selesai wajib diisi untuk periode aktif.',
        ]);
        $this->assertSame(0, Periode::where('nama', 'Triwulan I')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'periode.tambah_ditolak')->count());

        // Kontrol positif: periode nonaktif boleh belum bertanggal.
        $this->post('/periode', $this->payload([...self::KOSONG, 'aktif' => false]))->assertSessionHasNoErrors();
        $this->assertEquals(self::KOSONG, $this->tanggalTersimpan(Periode::where('nama', 'Triwulan I')->value('id')));
    }

    public function test_isian_string_dari_form_tersimpan_sebagai_angka_dan_isian_kosong_sebagai_belum_diatur(): void
    {
        $actor = $this->calendarActor();
        $this->calendarMaster();
        $kosong = array_fill_keys(array_keys(self::KOSONG), '');

        $this->actingAs($actor)->post('/periode', $this->payload($kosong))
            ->assertSessionHasErrors(['bulan_mulai' => 'Bulan mulai wajib diisi untuk periode aktif.']);
        $this->post('/periode', $this->payload(['bulan_mulai' => '1', 'hari_mulai' => '1', 'bulan_selesai' => '3', 'hari_selesai' => '31']))
            ->assertSessionHasNoErrors();
        $this->assertEquals(['bulan_mulai' => 1, 'hari_mulai' => 1, 'bulan_selesai' => 3, 'hari_selesai' => 31],
            $this->tanggalTersimpan(Periode::where('nama', 'Triwulan I')->value('id')));

        $this->post('/periode', $this->payload([...$kosong, 'nama' => 'Periode Cadangan', 'urutan' => 7, 'aktif' => false]))->assertSessionHasNoErrors();
        $this->assertEquals(self::KOSONG, $this->tanggalTersimpan(Periode::where('nama', 'Periode Cadangan')->value('id')));
    }

    public function test_tanggal_tersimpan_sebagai_hari_dan_bulan_tanpa_tahun(): void
    {
        $actor = $this->calendarActor();
        $this->calendarMaster();

        $this->actingAs($actor)->post('/periode', $this->payload(['bulan_mulai' => 2, 'hari_mulai' => 15, 'bulan_selesai' => 5, 'hari_selesai' => 14]))
            ->assertSessionHasNoErrors();

        $this->assertEquals(['bulan_mulai' => 2, 'hari_mulai' => 15, 'bulan_selesai' => 5, 'hari_selesai' => 14],
            $this->tanggalTersimpan(Periode::where('nama', 'Triwulan I')->value('id')));
    }

    /** @param array<string, mixed> $ubah */
    #[DataProvider('tanggalTidakSah')]
    public function test_tanggal_yang_tidak_berlaku_setiap_tahun_ditolak_dengan_pesan(array $ubah, string $field, string $pesan): void
    {
        $actor = $this->calendarActor();
        $this->calendarMaster();

        $this->actingAs($actor)->post('/periode', $this->payload($ubah))->assertSessionHasErrors([$field => $pesan]);

        $this->assertSame(0, Periode::where('nama', 'Triwulan I')->count());
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function tanggalTidakSah(): array
    {
        return [
            'mulai setelah selesai' => [['bulan_mulai' => 4, 'hari_mulai' => 1, 'bulan_selesai' => 3, 'hari_selesai' => 31], 'hari_selesai', 'Tanggal selesai tidak boleh sebelum tanggal mulai.'],
            '31 April' => [['bulan_mulai' => 4, 'hari_mulai' => 31, 'bulan_selesai' => 6, 'hari_selesai' => 30], 'hari_mulai', 'Tanggal mulai tidak tersedia pada bulan tersebut setiap tahun.'],
            '29 Februari' => [['bulan_mulai' => 1, 'hari_mulai' => 1, 'bulan_selesai' => 2, 'hari_selesai' => 29], 'hari_selesai', 'Tanggal selesai tidak tersedia pada bulan tersebut setiap tahun.'],
            'bulan di luar 1-12' => [['bulan_selesai' => 13], 'bulan_selesai', 'Bulan selesai harus berada antara 1 dan 12.'],
            'sebagian terisi pada periode nonaktif' => [['aktif' => false, 'bulan_selesai' => null, 'hari_selesai' => null], 'bulan_selesai', 'Lengkapi tanggal mulai dan selesai, atau kosongkan keduanya.'],
        ];
    }

    public function test_tumpang_tindih_dengan_periode_aktif_lain_ditolak_dan_rentang_bersebelahan_diterima(): void
    {
        $actor = $this->calendarActor();
        $this->calendarMaster();
        $desember = ['bulan_mulai' => 12, 'hari_mulai' => 1, 'bulan_selesai' => 12, 'hari_selesai' => 31];

        $this->actingAs($actor)->post('/periode', $this->payload(['nama' => 'Bentrok', ...$desember]))
            ->assertSessionHasErrors(['bulan_mulai' => 'Rentang tanggal tumpang tindih dengan periode aktif Triwulan 4.']);
        $this->assertSame(0, Periode::where('nama', 'Bentrok')->count());

        // Kontrol positif: periode nonaktif boleh beririsan dan rentang bersebelahan dengan periode aktif diterima.
        $this->post('/periode', $this->payload(['nama' => 'Bentrok nonaktif', 'aktif' => false, ...$desember]))->assertSessionHasNoErrors();
        $this->post('/periode', $this->payload(['nama' => 'Bersebelahan', 'bulan_mulai' => 7, 'hari_mulai' => 1, 'bulan_selesai' => 9, 'hari_selesai' => 30]))
            ->assertSessionHasNoErrors();

        // Mengaktifkan periode nonaktif yang beririsan tetap ditolak.
        $nonaktif = Periode::where('nama', 'Bentrok nonaktif')->sole();
        $this->put('/periode/'.$nonaktif->id, $this->payload(['nama' => 'Bentrok nonaktif', ...$desember, 'revisi' => 1]))
            ->assertSessionHasErrors('bulan_mulai');
        $this->assertFalse($nonaktif->fresh()->aktif);
    }

    public function test_mengubah_tanggal_periode_sendiri_tidak_dianggap_tumpang_tindih(): void
    {
        $actor = $this->calendarActor();
        $final = $this->calendarMaster();

        $this->actingAs($actor)->put('/periode/'.$final->id, ['nama' => $final->nama, 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true, 'revisi' => 1,
            'bulan_mulai' => 10, 'hari_mulai' => 15, 'bulan_selesai' => 12, 'hari_selesai' => 31])->assertSessionHasNoErrors();

        $this->assertEquals(['bulan_mulai' => 10, 'hari_mulai' => 15, 'bulan_selesai' => 12, 'hari_selesai' => 31], $this->tanggalTersimpan($final->id));
    }

    public function test_ganti_nilai_akhir_ke_periode_tanpa_tanggal_atau_beririsan_ditolak(): void
    {
        $actor = $this->calendarActor();
        $final = $this->calendarMaster();
        $this->calendarMaster(1, false);
        $data = fn (Periode $pengganti): array => ['periode_lama_id' => $final->id, 'revisi_lama' => $final->fresh()->revisi,
            'periode_pengganti_id' => $pengganti->id, 'revisi_pengganti' => $pengganti->fresh()->revisi];

        $tanpaTanggal = Periode::create(['nama' => 'Tahunan', 'urutan' => 5, 'aktif' => false, 'is_nilai_akhir' => false]);
        $this->actingAs($actor)->post('/periode/ganti-nilai-akhir', $data($tanpaTanggal))
            ->assertSessionHasErrors(['periode_pengganti_id' => 'Periode pengganti: tanggal mulai dan selesai wajib diisi untuk periode aktif.']);
        $setahun = Periode::create(['nama' => 'Setahun', 'urutan' => 6, 'aktif' => false, 'is_nilai_akhir' => false,
            'bulan_mulai' => 1, 'hari_mulai' => 1, 'bulan_selesai' => 12, 'hari_selesai' => 31]);
        $this->post('/periode/ganti-nilai-akhir', $data($setahun))
            ->assertSessionHasErrors(['periode_pengganti_id' => 'Periode pengganti: rentang tanggal tumpang tindih dengan periode aktif Triwulan 1.']);

        $this->assertTrue($final->fresh()->aktif);
        $this->assertFalse($tanpaTanggal->fresh()->aktif);
        $this->assertFalse($setahun->fresh()->aktif);
        $this->assertSame(2, AuditLog::where('tindakan', 'periode.ganti_nilai_akhir_ditolak')->count());

        // Kontrol positif: rentang final lama tidak dihitung karena final lama dinonaktifkan pada pergantian yang sama.
        $pengganti = Periode::create(['nama' => 'Triwulan IV baru', 'urutan' => 7, 'aktif' => false, 'is_nilai_akhir' => false,
            'bulan_mulai' => 10, 'hari_mulai' => 1, 'bulan_selesai' => 12, 'hari_selesai' => 31]);
        $this->post('/periode/ganti-nilai-akhir', $data($pengganti))->assertSessionHasNoErrors();
        $this->assertTrue($pengganti->fresh()->aktif);
        $this->assertTrue($pengganti->fresh()->is_nilai_akhir);
        $this->assertFalse($final->fresh()->aktif);
    }

    public function test_audit_perubahan_tanggal_memuat_nilai_lama_baru_dan_dasar_izin(): void
    {
        $actor = $this->calendarActor();
        $final = $this->calendarMaster();

        $this->actingAs($actor)->put('/periode/'.$final->id, ['nama' => $final->nama, 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true, 'revisi' => 1,
            'bulan_mulai' => 11, 'hari_mulai' => 1, 'bulan_selesai' => 12, 'hari_selesai' => 31])->assertSessionHasNoErrors();

        $audit = AuditLog::where('tindakan', 'periode.ubah')->sole();
        // jsonb mengurutkan ulang key, jadi pembandingnya tidak peka urutan.
        $this->assertEquals(['bulan_mulai' => 10, 'hari_mulai' => 1, 'bulan_selesai' => 12, 'hari_selesai' => 31],
            array_intersect_key($audit->nilai_lama, self::KOSONG));
        $this->assertEquals(['bulan_mulai' => 11, 'hari_mulai' => 1, 'bulan_selesai' => 12, 'hari_selesai' => 31],
            array_intersect_key($audit->nilai_baru, self::KOSONG));
        $this->assertSame('periode:update', $audit->dasar_izin['permission']);
    }

    public function test_tanpa_izin_atau_deny_eksplisit_ditolak_403_tanpa_menyimpan_tanggal(): void
    {
        $final = $this->calendarMaster();
        $ubah = ['nama' => $final->nama, 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true, 'revisi' => 1,
            'bulan_mulai' => 11, 'hari_mulai' => 1, 'bulan_selesai' => 12, 'hari_selesai' => 31];

        $this->actingAs($this->calendarActor(['periode:create']))->put('/periode/'.$final->id, $ubah)->assertForbidden();
        $ditolak = $this->calendarActor();
        UserPermissionDeny::create(['user_id' => $ditolak->id, 'permission_id' => Permission::where('kode', 'periode:update')->sole()->id,
            'ditetapkan_oleh' => $ditolak->id, 'alasan' => 'Fixture deny']);
        $this->actingAs($ditolak)->put('/periode/'.$final->id, $ubah)->assertForbidden();

        $this->assertEquals(['bulan_mulai' => 10, 'hari_mulai' => 1, 'bulan_selesai' => 12, 'hari_selesai' => 31], $this->tanggalTersimpan($final->id));
        $this->assertSame(2, AuditLog::where('tindakan', 'periode.ubah_ditolak')->count());

        // Kontrol positif: pemegang periode:update tanpa deny dapat menyimpan.
        $this->actingAs($this->calendarActor())->put('/periode/'.$final->id, $ubah)->assertSessionHasNoErrors();
        $this->assertEquals(['bulan_mulai' => 11, 'hari_mulai' => 1, 'bulan_selesai' => 12, 'hari_selesai' => 31], $this->tanggalTersimpan($final->id));
    }
}
