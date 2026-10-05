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
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/**
 * Urutan kunci global RencanaAksi → Indikator → Jadwal (T5, lihat
 * `EnsureDraftRencanaAksi` dan `SimpanTargetPeriode`).
 *
 * Memakai DatabaseMigrations (bukan RefreshDatabase seperti tetangga
 * RencanaAksi lain) agar fixture ter-commit dan terlihat oleh dua koneksi
 * PDO eksternal pada test dua-koneksi; preseden pola:
 * `RegulasiIndikatorLockOrderTest` (R2-21).
 */
class RencanaAksiLockOrderTest extends TestCase
{
    use DatabaseMigrations;

    private User $perencanaan;

    private Unit $unit;

    /**
     * Rebuild via migrate:fresh (bukan rollback) agar teardown tak menyentuh
     * down() migrasi lifecycle yang menolak baris pasca-cutover tanpa backup
     * (keterbatasan pre-existing, di luar scope N2). Fixture tetap
     * ter-commit dan terlihat oleh dua koneksi PDO eksternal.
     * Preseden pola: AccountConcurrencyTest.
     */
    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            try {
                if (DB::transactionLevel() !== 0) {
                    throw new RuntimeException('Rebuild ditolak: transaksi masih aktif.');
                }
                $this->assertDisposableDatabase($this->app);
                $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            } finally {
                RefreshDatabaseState::$migrated = false;
            }
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessCatalogSeeder::class);
        $this->perencanaan = $this->buatUserDenganRole('perencanaan', 'perencanaan-ra-lockorder-test@sakip.test');
        $this->unit = Unit::create(['nama' => 'Unit Uji Kunci RA', 'status' => 'aktif', 'created_by' => $this->perencanaan->id]);
    }

    public function test_create_lalu_update_berurutan_sukses_tanpa_deadlock(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        // Jalur Ensure (kunci ordering header-nihil → indikator → jadwal).
        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        // Jalur Simpan (header → indikator → jadwal) untuk header yang sama.
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $header->fresh()->versi);

        // Ensure idempoten kedua tetap mengembalikan baris yang sama.
        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $this->assertSame($header->id, RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole()->id);
    }

    /**
     * Dua koneksi PDO nyata ke PostgreSQL disposable: kedua jalur menulis
     * mengakuisisi header (FOR UPDATE) sebelum indikator, sehingga sisi yang
     * menunggu di header tidak pernah memegang indikator — hasilnya antre
     * (55P03 di bawah lock_timeout), BUKAN deadlock (40P01). Urutan lama
     * yang terbalik (Ensure Indikator-dahulu vs Simpan Header-dahulu) akan
     * saling menunggu pada kunci blocking.
     *
     * Batas bukti: orkestrasi antar-koneksi sekuensial dalam satu proses
     * (bukan dua proses paralel seperti AccountConcurrencyTest::race);
     * pemblokiran baris dan SQLSTATE PostgreSQL-nya nyata. Tidak ada klaim
     * 40P01 end-to-end (itu butuh dua proses benar-benar paralel).
     */
    public function test_dua_koneksi_header_dahulu_hanya_antre_tanpa_deadlock(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = RencanaAksi::create([
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
            'unit_id' => $this->unit->id,
            'jadwal_tahunan_id' => $fixture['jadwal']->id,
            'penanggung_jawab_id' => $fixture['pic']->id,
            'status_alur' => RencanaAksi::STATUS_DRAFT,
            'versi' => 1,
            'created_by' => $this->perencanaan->id,
        ]);

        $headerId = $header->id;
        $indikatorId = $fixture['indikator']->id;

        // Ronde 1: sisi Ensure pegang header via predikat indikator×tahun
        // (cermin kunci ordering T5), sisi Simpan meminta header yang sama
        // via id (cermin SimpanTargetPeriode) → menunggu, bukan deadlock.
        $pdoEnsure = $this->koneksiPengujian();
        $pdoSimpan = $this->koneksiPengujian();
        try {
            $pdoEnsure->exec('BEGIN');
            $kunciHeaderEnsure = $pdoEnsure->prepare('SELECT id FROM rencana_aksi WHERE indikator_id = :indikator AND tahun = :tahun FOR UPDATE');
            $kunciHeaderEnsure->execute(['indikator' => $indikatorId, 'tahun' => 2026]);
            $this->assertSame($headerId, $kunciHeaderEnsure->fetchColumn());

            $pdoSimpan->exec('BEGIN');
            $pdoSimpan->exec("SET LOCAL lock_timeout = '2s'");
            try {
                $pdoSimpan->prepare('SELECT id FROM rencana_aksi WHERE id = :id FOR UPDATE')->execute(['id' => $headerId]);
                $this->fail('Sisi simpan seharusnya menunggu header yang dipegang sisi ensure.');
            } catch (PDOException $exception) {
                $this->assertSame('55P03', $this->kodeSqlState($exception), 'Sisi simpan harus menunggu (lock timeout), bukan deadlock.');
            }
            $pdoSimpan->exec('ROLLBACK');

            // Sisi ensure dapat melanjutkan ke kunci kedua (indikator FOR
            // UPDATE) karena sisi simpan tak pernah memegang indikator.
            $kunciIndikator = $pdoEnsure->prepare('SELECT id FROM indikator_kinerjas WHERE id = :id FOR UPDATE');
            $kunciIndikator->execute(['id' => $indikatorId]);
            $this->assertSame($indikatorId, $kunciIndikator->fetchColumn());
            $pdoEnsure->exec('ROLLBACK');
        } finally {
            $this->rollbackTenang($pdoEnsure);
            $this->rollbackTenang($pdoSimpan);
        }

        // Ronde 2 (simetris): sisi Simpan pegang header via id, sisi Ensure
        // meminta via predikat indikator×tahun → menunggu, lalu sisi Simpan
        // tetap dapat mengunci indikator.
        $pdoA = $this->koneksiPengujian();
        $pdoB = $this->koneksiPengujian();
        try {
            $pdoB->exec('BEGIN');
            $pdoB->prepare('SELECT id FROM rencana_aksi WHERE id = :id FOR UPDATE')->execute(['id' => $headerId]);

            $pdoA->exec('BEGIN');
            $pdoA->exec("SET LOCAL lock_timeout = '2s'");
            try {
                $pdoA->prepare('SELECT id FROM rencana_aksi WHERE indikator_id = :indikator AND tahun = :tahun FOR UPDATE')
                    ->execute(['indikator' => $indikatorId, 'tahun' => 2026]);
                $this->fail('Sisi ensure seharusnya menunggu header yang dipegang sisi simpan.');
            } catch (PDOException $exception) {
                $this->assertSame('55P03', $this->kodeSqlState($exception));
            }
            $pdoA->exec('ROLLBACK');

            $pdoB->prepare('SELECT id FROM indikator_kinerjas WHERE id = :id FOR UPDATE')->execute(['id' => $indikatorId]);
            $pdoB->exec('ROLLBACK');
        } finally {
            $this->rollbackTenang($pdoA);
            $this->rollbackTenang($pdoB);
        }

        // Hasil deterministik: tanpa mutasi/audit parsial dari sonde kunci.
        $this->assertSame(1, $header->fresh()->versi);
        $this->assertSame(0, AuditLog::where('tindakan', 'rencana_aksi.ubah')->where('objek_id', (string) $headerId)->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(): array
    {
        $pic = $this->buatUserDenganRole('pegawai', 'pic-ra-lockorder-'.Str::random(6).'@sakip.test');
        $this->grant($pic, 'rencana_aksi:create', $this->unit->id, $this->perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $this->unit->id, $this->perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-LOCK-'.Str::random(4), 'nama' => 'Renstra Uji Kunci RA', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $this->perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-LOCK-'.Str::random(4), 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $this->unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Kunci Uji',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->perencanaan->id,
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
            'unit_id' => $this->unit->id,
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
            'ditetapkan_oleh' => $this->perencanaan->id,
            'created_at' => now(),
        ]);

        return compact('pic', 'renstra', 'sasaran', 'indikator', 'periode1', 'periode2', 'jadwal', 'snapshot');
    }

    private function buatUserDenganRole(string $roleName, string $email): User
    {
        $role = Role::where('kode', $roleName)->firstOrFail();
        $user = User::factory()->create(['email' => $email, 'status' => 'aktif']);
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

    private function koneksiPengujian(): PDO
    {
        /** @var array{host: mixed, port: mixed, database: mixed, username: mixed, password: mixed} $cfg */
        $cfg = config('database.connections.pgsql');
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $cfg['host'], $cfg['port'], $cfg['database']);

        $pdo = new PDO($dsn, (string) $cfg['username'], (string) $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("SET lock_timeout = '5s'");

        return $pdo;
    }

    private function kodeSqlState(PDOException $exception): string
    {
        $kode = $exception->errorInfo[0] ?? $exception->getCode();

        return is_string($kode) ? $kode : (string) $kode;
    }

    private function rollbackTenang(?PDO $pdo): void
    {
        if ($pdo === null) {
            return;
        }

        try {
            $pdo->exec('ROLLBACK');
        } catch (PDOException) {
            // Di luar transaksi (sudah commit/rollback) — abaikan.
        }
    }
}
