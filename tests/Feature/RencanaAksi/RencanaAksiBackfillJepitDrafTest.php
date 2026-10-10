<?php

namespace Tests\Feature\RencanaAksi;

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
use App\Services\RencanaAksi\RekonsiliasiTargetDraf;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi backfill jepit draf lama.
 *
 * Keputusan: backfill deterministik dari tabel backup pemetaan snapshot lama
 * (`_backup_rencana_aksi_jadwal_snapshot_20261004`, dibuat SEBELUM kolom
 * `jadwal_snapshot_id` di-drop), BUKAN tebak versi terbaru. Alasan: jepit ke
 * snapshot asal preserves jejak transisi (pin v1 → terbaru v3 tetap
 * mendeteksi basi v2-tanpa-simpan); menebak terbaru akan menghapus jejak dan
 * membangkitkan nilai basi. NULL hanya bila memang tak ada peta/snapshot:
 * tanpa baris backup, peta NULL, atau snapshot rujukan sudah tak ada
 * (JOIN memastikan keberadaan) — dibaca fail-closed telusur-penuh (pin 0).
 * `UPDATE rencana_aksi` tak memicu `guard_referenced_schedule_snapshot()`
 * (terpasang pada snapshot/komponen, bukan header) sehingga aman terhadap
 * trigger immutable-sejak-terbit. `down()` hanya me-NULL-kan baris yang masih memegang
 * nilai backfill persis, préserver jepit baru pasca-backfill.
 */
class RencanaAksiBackfillJepitDrafTest extends TestCase
{
    use RefreshDatabase;

    private const BACKUP_TABLE = '_backup_rencana_aksi_jadwal_snapshot_20261004';

    private const MIGRATION_PATH = 'migrations/2026_10_05_160925_backfill_snapshot_draf_rencana_aksi_u2.php';

    public function test_backfill_mengisi_pin_draf_lama_sesuai_peta_backup(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        // Simulasi draf lama pra-backfill: pin NULL + peta backup ke v1.
        DB::table('rencana_aksi')->where('id', $header->id)->update(['snapshot_draf_id' => null]);
        DB::table(self::BACKUP_TABLE)->insert([
            'rencana_aksi_id' => $header->id,
            'jadwal_snapshot_id' => $fixture['snapshot']->id,
        ]);

        $this->migrasiBackfill()->up();
        $this->assertSame($fixture['snapshot']->id, $header->fresh()->snapshot_draf_id);

        // Deterministik: pin non-NULL (sudah maju ke v2) tak ditimpa peta lama v1.
        $v2 = $this->terbitkanSnapshotV2($fixture);
        DB::table('rencana_aksi')->where('id', $header->id)->update(['snapshot_draf_id' => $v2->id]);
        $this->migrasiBackfill()->up();
        $this->assertSame($v2->id, $header->fresh()->snapshot_draf_id);

        // Yatim: peta menunjuk snapshot yang sudah tak ada → tetap NULL.
        $yatim = RencanaAksi::create([
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2025,
            'unit_id' => $fixture['unit']->id,
            'jadwal_tahunan_id' => $fixture['jadwal']->id,
            'snapshot_draf_id' => null,
            'penanggung_jawab_id' => $fixture['pic']->id,
            'status_alur' => RencanaAksi::STATUS_DRAFT,
            'versi' => 1,
            'created_by' => $fixture['pic']->id,
        ]);
        DB::table(self::BACKUP_TABLE)->insert([
            'rencana_aksi_id' => $yatim->id,
            'jadwal_snapshot_id' => (string) Str::uuid(),
        ]);
        $this->migrasiBackfill()->up();
        $this->assertNull($yatim->fresh()->snapshot_draf_id);

        // Trigger immutable-sejak-terbit tetap utuh sesudah backfill.
        try {
            DB::transaction(function () use ($fixture): void {
                $fixture['snapshot']->update(['target' => 666]);
            });
            $this->fail('Mutasi snapshot beku harus tetap ditolak sesudah backfill.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }

        // down() aman: hanya baris bernilai backfill persis yang di-NULL-kan.
        // Kembalikan header ke nilai backfill dulu agar down() teramati.
        DB::table('rencana_aksi')->where('id', $header->id)->update(['snapshot_draf_id' => $fixture['snapshot']->id]);
        $this->migrasiBackfill()->down();
        $this->assertNull($header->fresh()->snapshot_draf_id);
        $this->assertNull($yatim->fresh()->snapshot_draf_id);

        // Baris yang sudah maju ke v2 dipertahankan down().
        DB::table('rencana_aksi')->where('id', $header->id)->update(['snapshot_draf_id' => $v2->id]);
        $this->migrasiBackfill()->down();
        $this->assertSame($v2->id, $header->fresh()->snapshot_draf_id);
    }

    public function test_tanpa_peta_pin_tetap_null_dan_fail_closed_benar(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        // Simulasi draf lama tanpa peta: pin NULL, tanpa baris backup.
        DB::table('rencana_aksi')->where('id', $header->id)->update(['snapshot_draf_id' => null]);

        $this->migrasiBackfill()->up();
        $this->assertNull($header->fresh()->snapshot_draf_id);

        // Fail-closed: jepit NULL dibaca sebagai telusur-penuh (pin 0).
        $jejak = app(RekonsiliasiTargetDraf::class)->rekonsiliasi($header->fresh(), $fixture['snapshot']);
        $this->assertSame(0, $jejak['pin_nomor']);
        $this->assertSame(1, $jejak['aktual_nomor']);

        // Baca tetap menyajikan konteks terbaru (tak menolak draf NULL).
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.expected_snapshot_id', $fixture['snapshot']->id)
                ->where('rencanaAksi.expected_snapshot_versi', 1));

        // Simpan meminta konteks: token null saat snapshot ada ditolak 409.
        $payloadTanpaKonteks = [
            'expected_versi' => 1,
            'expected_snapshot_id' => null,
            'expected_snapshot_versi' => null,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ];
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", $payloadTanpaKonteks)
            ->assertRedirect()
            ->assertSessionHasErrors('expected_snapshot_id');
        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/target", $payloadTanpaKonteks)
            ->assertConflict()
            ->assertJsonValidationErrors('expected_snapshot_id');
        $this->assertSame(1, $header->fresh()->versi);
        $this->assertSame(0, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());

        // Simpan bertoken benar menyembuhkan jepit (NULL → v1).
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
        $header->refresh();
        $this->assertSame(2, $header->versi);
        $this->assertSame($fixture['snapshot']->id, $header->snapshot_draf_id);
    }

    private function migrasiBackfill(): object
    {
        static $migrasi = null;

        if ($migrasi === null) {
            $migrasi = require database_path(self::MIGRATION_PATH);
        }

        return $migrasi;
    }

    private function terbitkanSnapshotV2(array $fixture): JadwalSnapshot
    {
        return JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => 2,
            'menggantikan_id' => $fixture['snapshot']->id,
            'alasan_koreksi' => 'Koreksi resmi target PK U2.',
            'rujukan_koreksi' => 'SK-KOREKSI-R7U2-001',
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
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji R7U2 Manual', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-R7U2M', 'nama' => 'Renstra Uji R7U2 Manual', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-R7U2M', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Manual Uji R7U2',
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
