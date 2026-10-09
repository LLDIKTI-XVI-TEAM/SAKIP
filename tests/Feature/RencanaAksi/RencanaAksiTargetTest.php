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

class RencanaAksiTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_menyimpan_satu_baris_null_per_periode(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();

        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->where('tahun', 2026)->sole();
        $this->assertSame($fixture['unit']->id, $header->unit_id);
        $this->assertSame($fixture['jadwal']->id, $header->jadwal_tahunan_id);
        $this->assertSame($fixture['pic']->id, $header->penanggung_jawab_id);
        $this->assertSame('draft', $header->status_alur);
        $this->assertSame(1, $header->versi);

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rencana_aksi', 1);

        $response = $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => null, 'keterangan' => null],
            ],
        ]);
        $response->assertSessionHasNoErrors();

        $header->refresh();
        $this->assertSame(2, $header->versi);
        $this->assertSame('10.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('periode_id', $fixture['periode1']->id)->whereNull('komponen_id')->sole()->getRawOriginal('nilai'));
        $this->assertNull(RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('periode_id', $fixture['periode2']->id)->whereNull('komponen_id')->sole()->nilai);
        $this->assertDatabaseMissing('rencana_aksi_target', ['rencana_aksi_id' => $header->id, 'keterangan' => 'skor_turunan']);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah')->where('objek_id', $header->id)->exists());
    }

    /**
     * Seluruh matriks masuk audit dua kali tiap simpan, jadi ukuran per
     * permintaan (keterangan per sel) dan frekuensi tulis dibatasi agar
     * `audit_log` yang append-only tidak dapat dibanjiri. Pratinjau tidak
     * menulis audit dan dipanggil tiap ketikan, sehingga tidak ikut dibatasi.
     */
    public function test_tulis_membatasi_keterangan_dan_frekuensi_tanpa_membatasi_pratinjau(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $ensure = fn () => $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', ['indikator_id' => $fixture['indikator']->id, 'tahun' => 2026]);
        $payload = fn (?string $keterangan) => [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => $keterangan]],
        ];

        $ensure()->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", $payload(str_repeat('x', 1001)))
            ->assertSessionHasErrors('targets.0.keterangan');
        $this->assertSame(1, $header->fresh()->versi);

        for ($permintaan = 3; $permintaan <= 30; $permintaan++) {
            $ensure()->assertSessionHasNoErrors();
        }
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", $payload(null))->assertStatus(429);
        $this->assertSame(1, $header->fresh()->versi);
        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", $payload(null))->assertOk();
    }

    public function test_nonmanual_menyimpan_per_komponen_efektif_dan_nol_berbeda_dari_null(): void
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
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 0, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('0.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('komponen_id', $fixture['pembilang']->id)->sole()->getRawOriginal('nilai'));
        $this->assertSame('10.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('komponen_id', $fixture['penyebut']->id)->sole()->getRawOriginal('nilai'));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->fresh()->id}/target", [
            'expected_versi' => 2,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 5, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 0, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('0.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('komponen_id', $fixture['penyebut']->id)->sole()->getRawOriginal('nilai'));
        $this->assertDatabaseCount('rencana_aksi_target', 2);
    }

    public function test_periode_non_efektif_ditolak(): void
    {
        $fixture = $this->buatFixtureManual('manual', 2);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors('targets');

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
    }

    public function test_jendela_pic_ditutup_tetapi_perencanaan_sampai_penutupan(): void
    {
        $fixture = $this->buatFixtureManual();
        $fixture['jadwal']->update(['rencana_aksi_mulai' => '2026-01-05', 'rencana_aksi_selesai' => '2026-01-31']);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasErrors('jendela');
        $this->assertDatabaseCount('rencana_aksi', 0);

        $this->actingAs($fixture['perencanaan'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors('jendela');
        $this->assertSame(1, $header->fresh()->versi);

        $this->actingAs($fixture['perencanaan'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $header->fresh()->versi);
    }

    public function test_ensure_draft_menolak_grant_unit_yang_bukan_pic_efektif(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $bukanPic = $this->penggunaDenganPeran('pegawai');
        $this->grant($bukanPic, 'rencana_aksi:create', $fixture['unit']->id, $fixture['perencanaan']);

        $this->actingAs($bukanPic)->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasErrors('jendela');

        $this->assertDatabaseCount('rencana_aksi', 0);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.buat_ditolak')->exists());
    }

    public function test_ensure_draft_menolak_pic_efektif_di_luar_jendela(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 4, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasErrors('jendela');

        $this->assertDatabaseCount('rencana_aksi', 0);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.buat_ditolak')->exists());
    }

    public function test_ensure_draft_mengizinkan_pic_efektif_di_dalam_jendela(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();

        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $this->assertSame($fixture['pic']->id, $header->penanggung_jawab_id);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.buat')->where('objek_id', $header->id)->exists());
    }

    public function test_ensure_draft_perencanaan_global_mengikuti_jalur_resmi_di_luar_jendela(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 4, 10)->setTime(9, 0));

        $this->actingAs($fixture['perencanaan'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();

        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $this->assertSame($fixture['pic']->id, $header->penanggung_jawab_id);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.buat')->where('objek_id', $header->id)->exists());
    }

    public function test_versi_stale_ditolak_409_dan_tanpa_izin_403(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $tanpaIzin = $this->penggunaDenganPeran('pegawai');
        $this->actingAs($tanpaIzin)->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertForbidden();
        $this->actingAs($tanpaIzin)->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 5, 'keterangan' => null],
            ],
        ])->assertForbidden();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 5, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 9, 'keterangan' => null],
            ],
        ])->assertRedirect()->assertSessionHasErrors('expected_versi');

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 9, 'keterangan' => null],
            ],
        ])->assertConflict()->assertJsonValidationErrors('expected_versi');
        $this->assertSame(2, $header->fresh()->versi);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah_ditolak')->where('objek_id', $header->id)->exists());
    }

    public function test_indikator_arsip_ditolak(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $fixture['indikator']->update(['status' => 'arsip']);

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasErrors('indikator_id');
        $this->assertDatabaseCount('rencana_aksi', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(string $tipe = 'manual', int $mulaiUrutan = 1): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji RA', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-RA', 'nama' => 'Renstra Uji RA', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
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
        $dasar = $this->buatFixtureManual('rasio_persen', 1);

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
