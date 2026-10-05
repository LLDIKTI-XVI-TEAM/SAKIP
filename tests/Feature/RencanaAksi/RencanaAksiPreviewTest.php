<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\AuditLog;
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
use Tests\TestCase;

/**
 * Regresi P3 F5: pratinjau server-side tanpa persistensi.
 *
 * `POST /rencana-aksi/{id}/preview` memakai `CalculatePengukuran` yang
 * sama dengan jalur baca/tulis; edit nilai → pratinjau berubah tanpa POST
 * simpan dan tanpa draf tersimpan (versi tetap, target nihil berubah,
 * tanpa audit tulis).
 */
class RencanaAksiPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_manual_berubah_tanpa_menyimpan(): void
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
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $header->fresh()->versi);
        $auditSebelum = AuditLog::where('objek_id', $header->id)->count();

        // Edit TW I 20 → 30: pratinjau ikut berubah tanpa menyimpan.
        $respons = $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", [
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 30, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertOk();

        $periode = collect($respons->json('periode'))->keyBy('id');
        $this->assertSame('30.00', $periode[$fixture['periode1']->id]['skor']['nilai']);
        $this->assertSame('terhitung', $periode[$fixture['periode1']->id]['skor']['status_perhitungan']);
        $this->assertSame('10.00', $periode[$fixture['periode2']->id]['skor']['nilai']);
        // 10 < 30 → peringatan turun pada periode kedua.
        $this->assertTrue($periode[$fixture['periode2']->id]['peringatan_turun']);
        $this->assertSame([null], $periode[$fixture['periode2']->id]['komponen_turun']);

        // Tanpa persistensi: versi tetap, target tersimpan tak berubah, tanpa audit tulis.
        $this->assertSame(2, $header->fresh()->versi);
        $this->assertSame('20.000000000000', (string) RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('periode_id', $fixture['periode1']->id)->sole()->nilai);
        $this->assertSame('10.000000000000', (string) RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('periode_id', $fixture['periode2']->id)->sole()->nilai);
        $this->assertSame($auditSebelum, AuditLog::where('objek_id', $header->id)->count());
    }

    public function test_preview_nonmanual_menghitung_deviasi_tanpa_draf(): void
    {
        $fixture = $this->buatFixtureRasio();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 50, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 80, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
        $versi = $header->fresh()->versi;

        // Pratinjau menyamakan periode terakhir dengan target PK 100 → deviasi hilang.
        $respons = $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", [
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 50, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 100, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertOk();

        $this->assertSame('100.00', collect($respons->json('periode'))->keyBy('id')[$fixture['periode2']->id]['skor']['nilai']);
        $this->assertFalse($respons->json('deviasi_pk.ada'));
        $this->assertFalse($respons->json('deviasi_pk.alasan_diperlukan'));
        $this->assertSame('100.00', $respons->json('deviasi_pk.skor_periode_terakhir'));

        $this->assertSame($versi, $header->fresh()->versi);
        $this->assertSame(4, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
    }

    public function test_preview_menolak_tanpa_izin_dan_input_tak_sah(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $tanpaIzin = $this->penggunaDenganPeran('admin');
        $this->actingAs($tanpaIzin)->postJson("/rencana-aksi/{$header->id}/preview", [
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertForbidden();

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", [
            'targets' => [
                ['periode_id' => (string) Str::uuid(), 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertUnprocessable();

        $this->assertSame(1, $header->fresh()->versi);
        $this->assertDatabaseCount('rencana_aksi_target', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji Preview RA', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-PRV', 'nama' => 'Renstra Uji Preview', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-PRV', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Preview Uji',
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
    private function buatFixtureRasio(): array
    {
        $dasar = $this->buatFixtureManual();
        $dasar['indikator']->update(['tipe_perhitungan' => 'rasio_persen']);
        $dasar['snapshot']->update(['tipe_perhitungan' => 'rasio_persen']);

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
