<?php

namespace Tests\Feature\Notifikasi;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Notifikasi\PenerimaNotifikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

/**
 * Penerima notifikasi ditentukan server: hanya akun aktif berperan aktif, deny menang,
 * dan peninjau Perencanaan wajib lolos gerbang halaman tujuan (baca objek + izin verifikasi unit).
 */
class PenerimaNotifikasiTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    private PenerimaNotifikasi $penerima;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        $this->penerima = app(PenerimaNotifikasi::class);
    }

    /** @param list<User> $users @return list<string> */
    private function ids(array $users): array
    {
        $ids = array_map(fn (User $u): string => $u->id, $users);
        sort($ids);

        return $ids;
    }

    /** @param list<User> $users */
    private function sama(array $expected, array $users): void
    {
        $expectedIds = array_map(fn (User $u): string => $u->id, $expected);
        sort($expectedIds);
        $this->assertSame($expectedIds, $this->ids($users));
    }

    private function pengukuran(): PengukuranKinerja
    {
        return PengukuranKinerja::create(['indikator_id' => $this->indikator->id, 'tahun' => 2026, 'periode_id' => $this->snapshot->periode_mulai_id,
            'jadwal_snapshot_id' => $this->snapshot->id, 'sumber_nilai' => 'manual', 'created_by' => $this->actor->id]);
    }

    public function test_aktif_membuang_duplikat_akun_nonaktif_akun_tanpa_peran_dan_pengecualian(): void
    {
        $a = $this->createUserWithRole('pegawai');
        $b = $this->createUserWithRole('pegawai');
        $nonaktif = $this->createUserWithRole('pegawai');
        $nonaktif->update(['status' => 'nonaktif']);
        $tanpaPeran = User::factory()->create(['status' => 'aktif']);

        $this->sama([$a], $this->penerima->aktif([$a->id, $nonaktif->id, $tanpaPeran->id, $a->id]));
        $this->sama([$b], $this->penerima->aktif([$a->id, $b->id], $a->id));
        $this->sama([], $this->penerima->aktif([]));
    }

    public function test_peninjau_perencanaan_rencana_aksi_hanya_yang_lolos_baca_dan_izin_verifikasi_unit(): void
    {
        $this->createRencanaAksiSubmission();
        $p1 = $this->createUserWithRole('perencanaan');
        $p2 = $this->createUserWithRole('perencanaan');
        $denyGlobal = $this->createUserWithRole('perencanaan');
        $this->denyPermission($denyGlobal, 'rencana_aksi:verifikasi');
        $denyUnit = $this->createUserWithRole('perencanaan');
        $this->denyPermission($denyUnit, 'rencana_aksi:verifikasi', $this->unit->id);
        $denyBaca = $this->createUserWithRole('perencanaan');
        $this->denyPermission($denyBaca, 'rencana_aksi:read', $this->unit->id);
        $nonaktif = $this->createUserWithRole('perencanaan');
        $nonaktif->update(['status' => 'nonaktif']);
        $pengaju = $this->createUserWithRole('perencanaan');
        $this->createUserWithRole('admin');
        $this->createUserWithRole('pimpinan');
        // Superadmin fixture ($this->actor) tidak termasuk walau memegang semua izin.

        $hasil = $this->penerima->peninjauPerencanaan($this->rencanaAksi, 'rencana_aksi:verifikasi', $this->unit->id, $pengaju->id);

        $this->sama([$p1, $p2], $hasil);
    }

    public function test_peninjau_perencanaan_pengukuran_mengecualikan_deny_baca_unit(): void
    {
        $p = $this->pengukuran();
        $p1 = $this->createUserWithRole('perencanaan');
        $denyBaca = $this->createUserWithRole('perencanaan');
        $this->denyPermission($denyBaca, 'pengukuran:read', $this->unit->id);
        $denyVerifikasi = $this->createUserWithRole('perencanaan');
        $this->denyPermission($denyVerifikasi, 'pengukuran:verifikasi', $this->unit->id);
        $pengaju = $this->createUserWithRole('perencanaan');

        $this->sama([$p1], $this->penerima->peninjauPerencanaan($p, 'pengukuran:verifikasi', $this->unit->id, $pengaju->id));
    }

    public function test_peran_perencanaan_nonaktif_tidak_menghasilkan_peninjau(): void
    {
        $this->createUserWithRole('perencanaan');
        Role::where('kode', 'perencanaan')->update(['aktif' => false]);

        $this->sama([], $this->penerima->peninjauPerencanaan($this->rencanaAksi, 'rencana_aksi:verifikasi', $this->unit->id, $this->actor->id));
    }

    public function test_pengelola_akses_adalah_pemegang_akses_update_dan_pengguna_read_yang_aktif(): void
    {
        $admin = $this->createUserWithRole('admin');
        $denyAkses = $this->createUserWithRole('admin');
        $this->denyPermission($denyAkses, 'akses:update');
        $denyPengguna = $this->createUserWithRole('admin');
        $this->denyPermission($denyPengguna, 'pengguna:read');
        $nonaktif = $this->createUserWithRole('admin');
        $nonaktif->update(['status' => 'nonaktif']);
        $this->createUserWithRole('perencanaan');

        $this->sama([$this->actor, $admin], $this->penerima->pengelolaAkses());
    }

    public function test_pj_jendela_hanya_pj_efektif_indikator_yang_wajib_diisi_pada_periode_itu(): void
    {
        $tw2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);
        $tw3 = Periode::create(['nama' => 'Triwulan III', 'urutan' => 3, 'aktif' => true, 'is_nilai_akhir' => false]);
        $jendelaTw2 = PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $tw2->id, 'pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-15']);
        $jendelaTw3 = PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $tw3->id, 'pengisian_mulai' => '2026-09-01', 'pengisian_selesai' => '2026-09-15']);
        $tanggal = '2026-06-10';

        // Indikator A (fixture): PJ lama = actor sejak 1 Jan; PJ baru = pjA sejak 1 Mei.
        $pjA = $this->createUserWithRole('pegawai');
        PenugasanIndikator::create(['indikator_id' => $this->indikator->id, 'user_id' => $pjA->id, 'tanggal_mulai_berlaku' => '2026-05-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        // Indikator B: versi snapshot baru berlaku sejak TW III.
        $pjB = $this->createUserWithRole('pegawai');
        $b = $this->indikatorDenganPj('IKU-B', $tw3->id, $pjB, $this->unit);
        // Indikator C: arsip.
        $pjC = $this->createUserWithRole('pegawai');
        $this->indikatorDenganPj('IKU-C', $this->snapshot->periode_mulai_id, $pjC, $this->unit, 'arsip');
        // Indikator D: unit nonaktif.
        $pjD = $this->createUserWithRole('pegawai');
        $unitNonaktif = Unit::create(['nama' => 'Unit Nonaktif', 'status' => 'nonaktif', 'created_by' => $this->actor->id]);
        $this->indikatorDenganPj('IKU-D', $this->snapshot->periode_mulai_id, $pjD, $unitNonaktif);
        // Indikator E: PJ sama dengan A → satu akun.
        $this->indikatorDenganPj('IKU-E', $this->snapshot->periode_mulai_id, $pjA, $this->unit);

        $this->sama([$pjA], $this->penerima->pjJendela($jendelaTw2->id, null, $tanggal));
        $this->sama([$pjA, $pjB], $this->penerima->pjJendela($jendelaTw3->id, null, '2026-09-10'));
        $this->sama([$pjB], $this->penerima->pjJendela($jendelaTw3->id, [$b->id], '2026-09-10'));
        $this->sama([], $this->penerima->pjJendela($jendelaTw3->id, [], '2026-09-10'));
    }

    private function indikatorDenganPj(string $kode, string $periodeMulaiId, User $pj, Unit $unit, string $status = 'aktif'): IndikatorKinerja
    {
        $indikator = IndikatorKinerja::create([...$this->indikator->only(['sasaran_strategis_id', 'nama', 'satuan', 'tipe_perhitungan', 'tahun_mulai_berlaku', 'created_by', 'created_by_role']),
            'unit_id' => $unit->id, 'kode' => $kode, 'status' => $status]);
        JadwalSnapshot::create([...$this->snapshot->only(['jadwal_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'indikator_id' => $indikator->id, 'periode_mulai_id' => $periodeMulaiId, 'unit_id' => $unit->id]);
        PenugasanIndikator::create(['indikator_id' => $indikator->id, 'user_id' => $pj->id, 'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        return $indikator;
    }
}
