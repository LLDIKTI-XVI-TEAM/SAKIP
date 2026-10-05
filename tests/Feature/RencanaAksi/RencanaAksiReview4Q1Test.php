<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\RencanaAksi\IndexRencanaAksi;
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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Regresi Review4 Q1 (F1 token wajib + F4 guard unit baca/pratinjau).
 *
 * F1: `expected_snapshot_id`/`expected_snapshot_versi` wajib dikirim pada
 * setiap penyimpanan (`present`); null hanya sah bila konteks memang tanpa
 * snapshot. Jalur bypass klien-lama-tanpa-token dihapus — Action selalu
 * membandingkan token dengan snapshot terbaru terkunci.
 *
 * F4: `IndexRencanaAksi` + `PreviewTargetPeriode` menolak fail-closed bila
 * snapshot terbaru milik unit B untuk header milik unit A, sebelum payload
 * dibangun dan tanpa mengekspos konteks lintas-unit.
 */
class RencanaAksiReview4Q1Test extends TestCase
{
    use RefreshDatabase;

    public function test_simpan_tanpa_token_ditolak_422(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors(['expected_snapshot_id', 'expected_snapshot_versi']);

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['expected_snapshot_id', 'expected_snapshot_versi']);

        $this->assertSame(1, $header->fresh()->versi);
        $this->assertSame(0, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
    }

    public function test_simpan_token_null_eksplisit_dengan_snapshot_ditolak_409(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $payload = [
            'expected_versi' => 1,
            'expected_snapshot_id' => null,
            'expected_snapshot_versi' => null,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ];

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('expected_snapshot_id');

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/target", $payload)
            ->assertConflict()
            ->assertJsonValidationErrors('expected_snapshot_id');

        $this->assertSame(1, $header->fresh()->versi);
        $this->assertSame(0, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah_ditolak')->where('objek_id', $header->id)->exists());
    }

    public function test_simpan_token_null_diterima_bila_konteks_tanpa_snapshot(): void
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

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => null,
            'expected_snapshot_versi' => null,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $header->fresh()->versi);
        $this->assertSame(2, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
    }

    public function test_baca_ditolak_saat_unit_snapshot_tidak_selaras_tanpa_ekspos_lintas_unit(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        // F3 (Review6 T2): koreksi pindah unit diterbitkan sebagai versi
        // baru — mutasi langsung snapshot yang dijepit draf kini ditolak
        // trigger 23514, sehingga skenario ini memakai pola berversi.
        $unitB = Unit::create(['nama' => 'Unit Rahasia B Q1', 'status' => 'aktif', 'created_by' => $fixture['perencanaan']->id]);
        JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => 2,
            'menggantikan_id' => $fixture['snapshot']->id,
            'alasan_koreksi' => 'Koreksi pindah unit.',
            'rujukan_koreksi' => 'SK-KOREKSI-Q1-UNIT',
            'periode_mulai_id' => $fixture['periode1']->id,
            'unit_id' => $unitB->id,
            'nama' => $fixture['indikator']->nama,
            'definisi' => 'Definisi beku v2 pindah unit.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 100,
        ]);

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertSessionHasErrors('snapshot');

        try {
            app(IndexRencanaAksi::class)->handle($fixture['pic'], $header->fresh());
            $this->fail('IndexRencanaAksi harus menolak header A + snapshot B.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('snapshot', $exception->errors());
            $this->assertStringNotContainsString('Unit Rahasia B Q1', $exception->getMessage());
            $this->assertStringNotContainsString((string) $unitB->id, $exception->getMessage());
        }
    }

    public function test_preview_ditolak_saat_unit_snapshot_tidak_selaras_tanpa_ekspos_lintas_unit(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        // F3 (Review6 T2): sama seperti di atas — koreksi pindah unit
        // sebagai versi baru; token pratinjau memakai v2 agar kegagalan
        // yang diuji murni guard unit (bukan 409 token usang).
        $unitB = Unit::create(['nama' => 'Unit Rahasia B Preview Q1', 'status' => 'aktif', 'created_by' => $fixture['perencanaan']->id]);
        $v2 = JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => 2,
            'menggantikan_id' => $fixture['snapshot']->id,
            'alasan_koreksi' => 'Koreksi pindah unit pratinjau.',
            'rujukan_koreksi' => 'SK-KOREKSI-Q1-PREVIEW',
            'periode_mulai_id' => $fixture['periode1']->id,
            'unit_id' => $unitB->id,
            'nama' => $fixture['indikator']->nama,
            'definisi' => 'Definisi beku v2 pindah unit pratinjau.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 100,
        ]);

        $respons = $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $v2->id,
            'expected_snapshot_versi' => 2,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertUnprocessable();

        $respons->assertJsonValidationErrors('snapshot');
        $this->assertArrayNotHasKey('periode', $respons->json());
        $this->assertArrayNotHasKey('deviasi_pk', $respons->json());
        $this->assertStringNotContainsString('Unit Rahasia B Preview Q1', (string) $respons->getContent());

        $this->assertSame(1, $header->fresh()->versi);
        $this->assertSame(0, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji Q1 F1F4', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-Q1', 'nama' => 'Renstra Uji Q1', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-Q1', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Uji Q1',
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
     * Jadwal draf yang belum pernah aktif → tanpa snapshot adalah konteks sah.
     *
     * @return array<string, mixed>
     */
    private function buatFixtureDraftTanpaSnapshot(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji Q1 Tanpa Snapshot', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-Q1D', 'nama' => 'Renstra Uji Q1 Draft', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-Q1D', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Uji Q1 Draft',
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
