<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\RencanaAksi\PreviewTargetPeriode;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
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
use Tests\TestCase;

/**
 * Regresi Review4 Q2 (F2 preview terikat token + F3 validasi set-based).
 *
 * F2: `POST /rencana-aksi/{id}/preview` menerima + membandingkan token
 * snapshot halaman; konteks usang ditolak 409 (bukan menampilkan snapshot
 * terbaru diam-diam). Konsisten dengan jalur simpan.
 *
 * F3: validasi periode set-based — satu query untuk seluruh ID unik, bukan
 * `exists()` per-sel, agar lock transaksi tak tertahan s/d 600 query.
 */
class RencanaAksiReview4Q2Test extends TestCase
{
    use RefreshDatabase;

    public function test_preview_menolak_token_usang_setelah_snapshot_v2_terbit(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $v2 = JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => 2,
            'menggantikan_id' => $fixture['snapshot']->id,
            'alasan_koreksi' => 'Koreksi resmi target PK Q2.',
            'rujukan_koreksi' => 'SK-Q2-001',
            'periode_mulai_id' => $fixture['periode1']->id,
            'unit_id' => $fixture['unit']->id,
            'nama' => $fixture['indikator']->nama,
            'definisi' => 'Definisi beku v2.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 150,
        ]);

        $payloadUsang = [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 30, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ];

        $respons = $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", $payloadUsang)
            ->assertConflict();

        $respons->assertJsonValidationErrors('expected_snapshot_id');
        $this->assertArrayNotHasKey('periode', $respons->json());
        $this->assertArrayNotHasKey('deviasi_pk', $respons->json());

        $this->assertSame(1, $header->fresh()->versi);
        $this->assertSame(0, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $v2->id,
            'expected_snapshot_versi' => 2,
            'targets' => $payloadUsang['targets'],
        ])->assertOk()->assertJsonPath('deviasi_pk.target_pk', '150.000000000000');

        $this->assertSame(1, $header->fresh()->versi);
        $this->assertSame(0, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
    }

    public function test_preview_tanpa_token_ditolak_dan_null_hanya_tanpa_snapshot(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", [
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['expected_versi', 'expected_snapshot_id', 'expected_snapshot_versi']);

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", [
            'expected_versi' => 1,
            'expected_snapshot_id' => null,
            'expected_snapshot_versi' => null,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertConflict()->assertJsonValidationErrors('expected_snapshot_id');

        $this->assertSame(1, $header->fresh()->versi);
        $this->assertSame(0, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
    }

    public function test_preview_token_null_diterima_bila_konteks_tanpa_snapshot(): void
    {
        $fixture = $this->buatFixtureDraftTanpaSnapshot();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $header = RencanaAksi::create([
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
            'unit_id' => $fixture['unit']->id,
            'jadwal_tahunan_id' => $fixture['jadwal']->id,
            'penanggung_jawab_id' => $fixture['pic']->id,
            'status_alur' => 'draft',
            'versi' => 1,
            'created_by' => $fixture['perencanaan']->id,
        ]);

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", [
            'expected_versi' => 1,
            'expected_snapshot_id' => null,
            'expected_snapshot_versi' => null,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 25, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 35, 'keterangan' => null],
            ],
        ])->assertOk()->assertJsonPath('periode.0.skor.nilai', '25.00');

        $this->assertSame(1, $header->fresh()->versi);
        $this->assertSame(0, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
        $this->assertSame(0, AuditLog::where('objek_id', $header->id)->count());
    }

    public function test_preview_dan_simpan_konsisten_memakai_snapshot_terbaru(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $v2 = JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => 2,
            'menggantikan_id' => $fixture['snapshot']->id,
            'alasan_koreksi' => 'Koreksi konsistensi Q2.',
            'rujukan_koreksi' => 'SK-Q2-002',
            'periode_mulai_id' => $fixture['periode1']->id,
            'unit_id' => $fixture['unit']->id,
            'nama' => $fixture['indikator']->nama,
            'definisi' => 'Definisi beku v2.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 150,
        ]);

        $targets = [
            ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 150, 'keterangan' => null],
            ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 150, 'keterangan' => null],
        ];

        $pratinjau = $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $v2->id,
            'expected_snapshot_versi' => 2,
            'targets' => $targets,
        ])->assertOk();

        $this->assertSame('150.00', collect($pratinjau->json('periode'))->keyBy('id')[$fixture['periode2']->id]['skor']['nilai']);
        $this->assertFalse($pratinjau->json('deviasi_pk.ada'));

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => $targets,
        ])->assertConflict();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $v2->id,
            'expected_snapshot_versi' => 2,
            'targets' => $targets,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $header->fresh()->versi);
    }

    public function test_validasi_periode_set_based_tanpa_n_plus_1(): void
    {
        $fixture = $this->buatFixtureBanyakPeriode(12);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $targets = collect($fixture['periodes'])
            ->map(fn (Periode $periode): array => ['periode_id' => $periode->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null])
            ->all();
        $payload = [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => $targets,
        ];

        // F3: hitung query `periode` pada Action langsung (tanpa FormRequest
        // `exists` yang berjalan di luar lock). Set-based = satu whereIn +
        // lookup periode_mulai + eager jendela; per-sel lama = 12 exists.
        DB::enableQueryLog();
        app(PreviewTargetPeriode::class)->handle($fixture['pic'], $header->id, $payload);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $queryPeriode = array_values(array_filter($log, fn (array $entry): bool => str_contains(strtolower((string) $entry['query']), 'from "periode"')));
        $this->assertLessThanOrEqual(3, count($queryPeriode), 'Validasi periode harus set-based (satu whereIn + lookup periode_mulai), bukan exists per-sel.');

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", $payload)->assertOk();

        $this->assertSame(1, $header->fresh()->versi);
        $this->assertSame(0, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());

        $buruk = $targets;
        $buruk[0]['periode_id'] = (string) Str::uuid();
        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => $buruk,
        ])->assertUnprocessable();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => $targets,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $header->fresh()->versi);
        $this->assertSame(12, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji Q2 F2F3', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-Q2', 'nama' => 'Renstra Uji Q2', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-Q2', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Uji Q2',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $periode1 = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $periode2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => true]);
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
        $snapshot = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $indikator->id,
            'nomor_versi' => 1,
            'periode_mulai_id' => $periode1->id,
            'unit_id' => $unit->id,
            'nama' => $indikator->nama,
            'definisi' => 'Definisi beku.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 100,
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
    private function buatFixtureDraftTanpaSnapshot(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji Q2 Tanpa Snapshot', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-Q2D', 'nama' => 'Renstra Uji Q2 Draft', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-Q2D', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Uji Q2 Draft',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $periode1 = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $periode2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => true]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'rencana_aksi_mulai' => '2026-03-01',
            'rencana_aksi_selesai' => '2026-03-31',
            'penutupan' => '2026-12-31',
            'status' => 'draft',
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
        PenugasanIndikator::create([
            'indikator_id' => $indikator->id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $perencanaan->id,
            'created_at' => now(),
        ]);

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periode1', 'periode2', 'jadwal');
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureBanyakPeriode(int $jumlah): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji Q2 Banyak Periode', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-Q2B', 'nama' => 'Renstra Uji Q2 Banyak', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-Q2B', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Uji Q2 Banyak',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $periodes = [];
        for ($i = 1; $i <= $jumlah; $i++) {
            $periodes[] = Periode::create(['nama' => 'Periode '.$i, 'urutan' => $i, 'aktif' => true, 'is_nilai_akhir' => $i === $jumlah]);
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
            'nomor_versi' => 1,
            'periode_mulai_id' => $periodes[0]->id,
            'unit_id' => $unit->id,
            'nama' => $indikator->nama,
            'definisi' => 'Definisi beku.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 100,
        ]);
        PenugasanIndikator::create([
            'indikator_id' => $indikator->id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $perencanaan->id,
            'created_at' => now(),
        ]);

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periodes', 'jadwal', 'snapshot');
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
