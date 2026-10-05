<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
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
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionDecision;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RencanaAksiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_menolak_saat_izin_dicabut_di_dalam_transaksi(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $counter = new \stdClass;
        $counter->count = 0;
        $mock = $this->createMock(PermissionResolver::class);
        $mock->method('resolve')->willReturnCallback(function ($user, $code, $unitId = null) use ($counter) {
            $counter->count++;
            if ($counter->count === 1) {
                return new PermissionDecision(true, $code, [
                    'alasan' => 'allow',
                    'sumber_allow' => ['roles' => ['role-test'], 'grants' => []],
                    'deny' => [],
                ]);
            }

            return new PermissionDecision(false, $code, [
                'alasan' => 'revoked_inside_transaction',
                'sumber_allow' => ['roles' => [], 'grants' => []],
                'deny' => [],
            ]);
        });
        $mock->method('decide')->willReturnCallback(function ($user, $code, $unitId = null) use ($counter) {
            $counter->count++;
            if ($counter->count === 1) {
                return ['allowed' => true, 'permission' => $code, 'reason' => 'allow', 'roles' => ['role-test'], 'grants' => [], 'denies' => []];
            }

            return ['allowed' => false, 'permission' => $code, 'reason' => 'revoked_inside_transaction', 'roles' => [], 'grants' => [], 'denies' => []];
        });
        $mock->method('allows')->willReturnCallback(function () use ($counter) {
            $counter->count++;

            return $counter->count === 1;
        });
        $this->app->instance(PermissionResolver::class, $mock);

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertForbidden();

        $this->assertGreaterThanOrEqual(2, $counter->count, 'PermissionResolver harus dipanggil kembali di dalam transaksi untuk otorisasi ulang.');
        $this->assertDatabaseCount('rencana_aksi', 0);

        $audit = AuditLog::where('tindakan', 'rencana_aksi.buat_ditolak')->latest('waktu')->first();
        $this->assertNotNull($audit);
        $this->assertSame('revoked_inside_transaction', $audit->dasar_izin['reason'] ?? $audit->dasar_izin['alasan'] ?? null);
    }

    public function test_update_menolak_saat_izin_dicabut_di_dalam_transaksi(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $counter = new \stdClass;
        $counter->count = 0;
        $mock = $this->createMock(PermissionResolver::class);
        $mock->method('resolve')->willReturnCallback(function ($user, $code, $unitId = null) use ($counter) {
            $counter->count++;
            if ($counter->count === 1) {
                return new PermissionDecision(true, $code, [
                    'alasan' => 'allow',
                    'sumber_allow' => ['roles' => ['role-test'], 'grants' => []],
                    'deny' => [],
                ]);
            }

            return new PermissionDecision(false, $code, [
                'alasan' => 'revoked_inside_transaction',
                'sumber_allow' => ['roles' => [], 'grants' => []],
                'deny' => [],
            ]);
        });
        $mock->method('decide')->willReturnCallback(function ($user, $code, $unitId = null) use ($counter) {
            $counter->count++;
            if ($counter->count === 1) {
                return ['allowed' => true, 'permission' => $code, 'reason' => 'allow', 'roles' => ['role-test'], 'grants' => [], 'denies' => []];
            }

            return ['allowed' => false, 'permission' => $code, 'reason' => 'revoked_inside_transaction', 'roles' => [], 'grants' => [], 'denies' => []];
        });
        $mock->method('allows')->willReturnCallback(function () use ($counter) {
            $counter->count++;

            return $counter->count === 1;
        });
        $this->app->instance(PermissionResolver::class, $mock);

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertForbidden();

        $this->assertGreaterThanOrEqual(2, $counter->count, 'PermissionResolver harus dipanggil kembali di dalam transaksi untuk otorisasi ulang.');
        $this->assertSame(1, $header->fresh()->versi);

        $audit = AuditLog::where('tindakan', 'rencana_aksi.ubah_ditolak')->where('objek_id', $header->id)->latest('waktu')->first();
        $this->assertNotNull($audit);
        $this->assertSame('revoked_inside_transaction', $audit->dasar_izin['reason'] ?? $audit->dasar_izin['alasan'] ?? null);
    }

    public function test_mencabut_grant_menolak_mutasi_berikutnya(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        DB::table('user_permission_granted')->where('user_id', $fixture['pic']->id)->delete();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertForbidden();
        $this->assertSame(1, $header->fresh()->versi);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah_ditolak')->where('objek_id', $header->id)->exists());

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertForbidden();
    }

    public function test_deny_menang_atas_grant_dan_peran(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->deny($fixture['pic'], 'rencana_aksi:create', $fixture['unit']->id);
        $this->deny($fixture['pic'], 'rencana_aksi:update', $fixture['unit']->id);
        $this->deny($fixture['pic'], 'rencana_aksi:read', $fixture['unit']->id);

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertForbidden();
        $this->assertDatabaseCount('rencana_aksi', 0);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.buat_ditolak')->exists());

        DB::table('user_permission_denied')->where('user_id', $fixture['pic']->id)->delete();

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->deny($fixture['pic'], 'rencana_aksi:update', $fixture['unit']->id);
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertForbidden();
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah_ditolak')->where('objek_id', $header->id)->exists());

        $this->deny($fixture['pic'], 'rencana_aksi:read', $fixture['unit']->id);
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertForbidden();
    }

    public function test_lintas_unit_ditolak(): void
    {
        $fixture = $this->buatFixtureManual();
        $unitLain = Unit::create(['nama' => 'Unit Lain RA', 'status' => 'aktif', 'created_by' => $fixture['perencanaan']->id]);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $picLain = $this->penggunaDenganPeran('pegawai');
        $this->grant($picLain, 'rencana_aksi:create', $unitLain->id, $fixture['perencanaan']);
        $this->grant($picLain, 'rencana_aksi:update', $unitLain->id, $fixture['perencanaan']);

        $this->actingAs($picLain)->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertForbidden();

        $this->actingAs($picLain)->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertForbidden();
        $this->assertSame(1, $header->fresh()->versi);
    }

    public function test_perencanaan_global_lolos_tanpa_grant_unit(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->assertDatabaseMissing('user_permission_granted', [
            'user_id' => $fixture['perencanaan']->id,
        ]);

        $this->actingAs($fixture['perencanaan'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['perencanaan'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $header->fresh()->versi);

        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}")->assertOk();
    }

    public function test_jendela_pic_ditutup_tetapi_perencanaan_lolos(): void
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
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors('jendela');
        $this->assertSame(1, $header->fresh()->versi);

        $this->actingAs($fixture['perencanaan'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $header->fresh()->versi);
    }

    public function test_pic_berganti_menolak_pic_lama_dan_meloloskan_pic_baru_tanpa_mengubah_header(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $this->assertSame($fixture['pic']->id, $header->penanggung_jawab_id);

        $picBaru = $this->penggunaDenganPeran('pegawai');
        $this->grant($picBaru, 'rencana_aksi:create', $fixture['unit']->id, $fixture['perencanaan']);
        $this->grant($picBaru, 'rencana_aksi:update', $fixture['unit']->id, $fixture['perencanaan']);
        PenugasanIndikator::create([
            'indikator_id' => $fixture['indikator']->id,
            'user_id' => $picBaru->id,
            'tanggal_mulai_berlaku' => '2026-03-05',
            'ditetapkan_oleh' => $fixture['perencanaan']->id,
            'created_at' => now(),
        ]);

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors('jendela');
        $this->assertSame(1, $header->fresh()->versi);

        $this->actingAs($picBaru)->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 25, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $header->refresh();
        $this->assertSame(2, $header->versi);
        $this->assertSame($fixture['pic']->id, $header->penanggung_jawab_id);
    }

    public function test_baca_menolak_tanpa_izin_dan_deny_menang(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $tanpaIzin = $this->penggunaDenganPeran('admin');
        $this->actingAs($tanpaIzin)->get("/rencana-aksi/{$header->id}")->assertForbidden();

        $pembaca = $this->penggunaDenganPeran('pimpinan');
        $this->actingAs($pembaca)->get("/rencana-aksi/{$header->id}")->assertOk();

        $this->deny($pembaca, 'rencana_aksi:read', $fixture['unit']->id);
        $this->actingAs($pembaca)->get("/rencana-aksi/{$header->id}")->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(string $tipe = 'manual', int $mulaiUrutan = 1): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji RA Auth', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-RAA', 'nama' => 'Renstra Uji RA Auth', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-RAA', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Auth Uji',
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

    private function deny(User $user, string $permission, ?string $unitId = null): void
    {
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'permission_id' => Permission::where('kode', $permission)->value('id'),
            'unit_id' => $unitId,
            'alasan' => 'Pembatasan uji',
            'ditetapkan_oleh' => $user->id,
            'created_at' => now(),
        ]);
    }
}
