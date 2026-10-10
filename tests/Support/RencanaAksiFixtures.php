<?php

namespace Tests\Support;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JadwalTahunan;
use App\Models\JenisBerkas;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fixture Rencana Aksi dua periode (Triwulan I–II, jendela penyusunan Maret
 * 2026): PIC pegawai dengan grant unit `rencana_aksi:create/update/ajukan`
 * dan penugasan PJ efektif, aktor Perencanaan, serta snapshot jadwal beku
 * berisi target PK yang dapat diatur (null = target PK belum ditetapkan).
 */
trait RencanaAksiFixtures
{
    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(string $tipe = 'manual', int $mulaiUrutan = 1, ?string $targetPk = '100'): array
    {
        // Fixture dapat dibangun dua kali dalam satu test: nama unit dan kode
        // Renstra (unik) diberi sufiks acak, sedangkan periode master dipakai
        // ulang karena hanya boleh ada satu periode nilai akhir aktif.
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $sufiks = Str::random(4);
        $unit = Unit::create(['nama' => 'Unit Uji RA '.$sufiks, 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:ajukan', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-RA-'.$sufiks, 'nama' => 'Renstra Uji RA', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-RA', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Manual Uji',
            'satuan' => 'poin',
            'tipe_perhitungan' => $tipe,
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $periode1 = Periode::firstOrCreate(['nama' => 'Triwulan I', 'urutan' => 1], ['aktif' => true, 'is_nilai_akhir' => false]);
        $periode2 = Periode::firstOrCreate(['nama' => 'Triwulan II', 'urutan' => 2], ['aktif' => true, 'is_nilai_akhir' => true]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'rencana_aksi_mulai' => '2026-03-01',
            'rencana_aksi_selesai' => '2026-03-31',
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);
        foreach ([$periode1, $periode2] as $periode) {
            PeriodeJadwal::create([
                'jadwal_id' => $jadwal->id,
                'periode_id' => $periode->id,
                'pengisian_mulai' => '2026-03-01',
                'pengisian_selesai' => '2026-03-31',
                'reviu_mulai' => '2026-04-01',
                'reviu_selesai' => '2026-04-30',
            ]);
        }
        $mulaiId = $mulaiUrutan === 2 ? $periode2->id : $periode1->id;
        $snapshot = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $indikator->id,
            'periode_mulai_id' => $mulaiId,
            'unit_id' => $unit->id,
            'nama' => $indikator->nama,
            'definisi' => 'Definisi beku.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => $tipe,
            'target' => $targetPk,
        ]);
        PenugasanIndikator::create([
            'indikator_id' => $indikator->id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $perencanaan->id,
            'created_at' => now(),
        ]);

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periode1', 'periode2', 'jadwal', 'snapshot');
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureRasio(?string $targetPk = '100'): array
    {
        $dasar = $this->buatFixtureManual('rasio_persen', 1, $targetPk);

        $pembilang = IndikatorKomponen::create([
            'indikator_id' => $dasar['indikator']->id,
            'kode' => 'n',
            'label' => 'Pembilang',
            'peran' => 'pembilang',
            'bobot' => 1,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $dasar['perencanaan']->id,
        ]);
        $penyebut = IndikatorKomponen::create([
            'indikator_id' => $dasar['indikator']->id,
            'kode' => 't',
            'label' => 'Penyebut',
            'peran' => 'penyebut',
            'bobot' => 1,
            'urutan' => 2,
            'aktif' => true,
            'created_by' => $dasar['perencanaan']->id,
        ]);
        foreach ([$pembilang, $penyebut] as $index => $komponen) {
            JadwalSnapshotKomponen::create([
                'jadwal_snapshot_id' => $dasar['snapshot']->id,
                'komponen_id' => $komponen->id,
                'kode' => $komponen->kode,
                'label' => $komponen->label,
                'peran' => $komponen->peran,
                'bobot' => 1,
                'urutan' => $index + 1,
            ]);
        }

        return [...$dasar, 'pembilang' => $pembilang, 'penyebut' => $penyebut];
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function buatDraf(array $fixture): RencanaAksi
    {
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();

        return RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  list<array<string, mixed>>  $targets
     */
    private function simpan(array $fixture, RencanaAksi $header, int $versi, array $targets, ?User $aktor = null): void
    {
        $this->actingAs($aktor ?? $fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $versi,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => $targets,
        ])->assertSessionHasNoErrors();
    }

    private function penggunaDenganPeran(string $kode): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', $kode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function grant(User $user, string $permission, string $unitId, User $oleh): void
    {
        DB::table('user_permission_granted')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'permission_id' => Permission::where('kode', $permission)->value('id'),
            'unit_id' => $unitId,
            'alasan' => 'Fixture pengujian',
            'diberikan_oleh' => $oleh->id,
            'created_at' => now(),
        ]);
    }

    /**
     * Persyaratan bukti wajib tahap rencana aksi untuk indikator fixture:
     * mode file atau teks, satu mode cukup.
     *
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $atribut
     */
    private function buatPersyaratanWajib(array $fixture, array $atribut = []): JenisBerkas
    {
        return JenisBerkas::create([
            'nama' => 'KAK',
            'tahap' => 'rencana_aksi',
            'indikator_id' => $fixture['indikator']->id,
            'wajib' => true,
            'semua_mode_wajib' => false,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => true,
            'aktif' => true,
            'urutan' => 1,
            'created_by' => $fixture['perencanaan']->id,
            ...$atribut,
        ]);
    }
}
