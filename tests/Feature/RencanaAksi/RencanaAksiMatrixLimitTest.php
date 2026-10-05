<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JadwalTahunan;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\JadwalFixtures;
use Tests\TestCase;

/**
 * Regresi P1 F3-rework: batas matriks dikunci di sumbernya.
 *
 * Keputusan (a): `StoreJadwalRequest` membatasi `periode` max:12 sehingga
 * batas `targets` max:600 (= 50 komponen × 12 periode) selalu cukup untuk
 * konfigurasi sah mana pun. Alternatif (b) hapus batas ditolak karena
 * menghilangkan guard payload, dan (c) chunked menambah transaksi parsial/UX
 * tanpa menyelesaikan asumsi.
 */
class RencanaAksiMatrixLimitTest extends TestCase
{
    use JadwalFixtures, RefreshDatabase;

    public function test_jadwal_menolak_13_periode_agar_600_selalu_cukup(): void
    {
        $actor = $this->calendarActor();
        $renstra = $this->calendarRenstra($actor);
        $masters = [];
        for ($i = 1; $i <= 13; $i++) {
            $masters[] = Periode::create(['nama' => 'Bulan '.$i, 'urutan' => $i, 'aktif' => true, 'is_nilai_akhir' => $i === 13])->refresh();
        }
        $data = $this->payloadBanyakPeriode($renstra, $masters, 2026);

        $this->actingAs($actor)->post('/jadwal', $data)->assertSessionHasErrors('periode');
        $this->assertSame(0, JadwalTahunan::count());
    }

    public function test_jadwal_menerima_12_periode_maksimum_sah(): void
    {
        $actor = $this->calendarActor();
        $renstra = $this->calendarRenstra($actor);
        $masters = [];
        for ($i = 1; $i <= 12; $i++) {
            $masters[] = Periode::create(['nama' => 'Bulan '.$i, 'urutan' => $i, 'aktif' => true, 'is_nilai_akhir' => $i === 12])->refresh();
        }
        $data = $this->payloadBanyakPeriode($renstra, $masters, 2026);

        $this->actingAs($actor)->post('/jadwal', $data)->assertSessionHasNoErrors();
        $this->assertSame(1, JadwalTahunan::count());
        $this->assertSame(12, PeriodeJadwal::count());
    }

    public function test_matriks_maksimum_12x50_tersimpan_utuh_dari_ui(): void
    {
        $fixture = $this->buatFixtureMaksimum();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $targets = [];
        foreach ($fixture['periodes'] as $periode) {
            foreach ($fixture['komponens'] as $komponen) {
                $targets[] = [
                    'periode_id' => $periode->id,
                    'komponen_id' => $komponen->id,
                    'nilai' => 10,
                    'keterangan' => null,
                ];
            }
        }
        // Tepat 600 sel (12 periode × 50 komponen) = batas domain maksimum sah.
        $this->assertCount(600, $targets);

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => $targets,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $header->fresh()->versi);
        $this->assertSame(600, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.periode.0.skor.nilai', '500.00')
                ->where('rencanaAksi.periode.0.skor.status_perhitungan', 'terhitung'));
    }

    /**
     * @param  list<Periode>  $masters
     * @return array<string, mixed>
     */
    private function payloadBanyakPeriode(Renstra $renstra, array $masters, int $year = 2026): array
    {
        $count = count($masters);
        $periode = [];
        foreach (array_values($masters) as $index => $master) {
            $isLast = $index === $count - 1;
            // 11 jendela pertama di tahun berjalan (Feb..Des), terakhir boleh
            // masuk tahun berikut (pola kalender sah); kelebihan 13 tetap
            // ditolak max:12 sebelum aturan domain dijalankan.
            if (! $isLast || $count > 12) {
                $bulan = ($index % 11) + 2;
                $mm = str_pad((string) $bulan, 2, '0', STR_PAD_LEFT);
                $periode[] = [
                    'periode_id' => $master->id,
                    'periode_revisi' => $master->revisi,
                    'pengisian_mulai' => "{$year}-{$mm}-01",
                    'pengisian_selesai' => "{$year}-{$mm}-05",
                    'reviu_mulai' => "{$year}-{$mm}-06",
                    'reviu_selesai' => "{$year}-{$mm}-10",
                ];

                continue;
            }
            $periode[] = [
                'periode_id' => $master->id,
                'periode_revisi' => $master->revisi,
                'pengisian_mulai' => ($year + 1).'-01-11',
                'pengisian_selesai' => ($year + 1).'-01-12',
                'reviu_mulai' => ($year + 1).'-01-13',
                'reviu_selesai' => ($year + 1).'-01-14',
            ];
        }

        return [
            'renstra_id' => $renstra->id,
            'tahun' => $year,
            'rencana_aksi_mulai' => "{$year}-01-05",
            'rencana_aksi_selesai' => "{$year}-01-10",
            'penutupan' => ($year + 1).'-01-19',
            'periode' => $periode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureMaksimum(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji P1 Maks', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-P1M', 'nama' => 'Renstra Uji P1 Maks', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-P1M', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Maksimum P1',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'penjumlahan',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $periodes = [];
        for ($i = 1; $i <= 12; $i++) {
            $periodes[] = Periode::create([
                'nama' => 'Bulan RA '.$i,
                'urutan' => $i,
                'aktif' => true,
                'is_nilai_akhir' => $i === 12,
            ]);
        }
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'rencana_aksi_mulai' => '2026-03-01',
            'rencana_aksi_selesai' => '2026-03-31',
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);
        foreach ($periodes as $periode) {
            PeriodeJadwal::create([
                'jadwal_id' => $jadwal->id,
                'periode_id' => $periode->id,
                'pengisian_mulai' => '2026-03-01',
                'pengisian_selesai' => '2026-03-31',
                'reviu_mulai' => '2026-04-01',
                'reviu_selesai' => '2026-04-30',
            ]);
        }
        $snapshot = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $indikator->id,
            'periode_mulai_id' => $periodes[0]->id,
            'unit_id' => $unit->id,
            'nama' => $indikator->nama,
            'definisi' => 'Definisi beku maksimum.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'penjumlahan',
            'target' => 100,
        ]);

        $komponens = [];
        for ($i = 1; $i <= 50; $i++) {
            $komponen = IndikatorKomponen::create([
                'indikator_id' => $indikator->id,
                'kode' => 'k'.$i,
                'label' => 'Komponen '.$i,
                'peran' => 'penjumlah',
                'bobot' => 1,
                'urutan' => $i,
                'aktif' => true,
                'created_by' => $perencanaan->id,
            ]);
            JadwalSnapshotKomponen::create([
                'jadwal_snapshot_id' => $snapshot->id,
                'komponen_id' => $komponen->id,
                'kode' => $komponen->kode,
                'label' => $komponen->label,
                'peran' => 'penjumlah',
                'bobot' => 1,
                'urutan' => $i,
            ]);
            $komponens[] = $komponen;
        }

        PenugasanIndikator::create([
            'indikator_id' => $indikator->id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $perencanaan->id,
            'created_at' => now(),
        ]);

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periodes', 'jadwal', 'snapshot', 'komponens');
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
}
