<?php

namespace Tests\Feature\Perencanaan;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
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
 * Urutan kunci global Regulasi → Indikator (lihat UpdateIndikator 2c/4b
 * dan RegulasiService::delete + referensiAktifTerkunci).
 *
 * Kelas ini memakai DatabaseMigrations (bukan RefreshDatabase seperti
 * tetangganya) agar fixture ter-commit dan terlihat oleh dua koneksi PDO
 * eksternal pada test dua-koneksi; preseden pola: AccountConcurrencyTest.
 */
class RegulasiIndikatorLockOrderTest extends TestCase
{
    use DatabaseMigrations;

    private User $perencanaan;

    private Renstra $renstra;

    private Unit $unit;

    private Regulasi $regulasi;

    /**
     * Rebuild via migrate:fresh (bukan rollback) agar teardown tak menyentuh
     * down() migrasi lifecycle yang menolak baris pasca-cutover tanpa backup
     * (keterbatasan pre-existing, di luar scope R2-21). Fixture tetap
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
        $this->pasangPresetRole('perencanaan');

        $this->perencanaan = $this->buatUserDenganRole('perencanaan', 'perencanaan-lockorder-test@sakip.test');

        $this->renstra = Renstra::create([
            'kode' => 'RENSTRA-2025-2029',
            'nama' => 'Renstra LLDIKTI Wilayah XVI 2025-2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $this->unit = Unit::create([
            'nama' => 'Bagian Tata Usaha',
            'status' => 'aktif',
            'created_by' => $this->perencanaan->id,
        ]);

        $this->regulasi = Regulasi::create([
            'jenis' => 'kepmen',
            'nomor' => '358/M/KEP/2025',
            'tahun' => 2025,
            'tentang' => 'Indikator Kinerja Utama Perguruan Tinggi',
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);
    }

    public function test_update_indikator_menautkan_regulasi_kedua_sukses_dan_diaudit(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R221-LINK',
            'deskripsi' => 'Sasaran uji urutan kunci regulasi',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R221-LINK',
            'nama' => 'Indikator Tanpa Regulasi',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
        ]);

        $regulasiKedua = Regulasi::create([
            'jenis' => 'permen',
            'nomor' => '10/2025',
            'tahun' => 2025,
            'tentang' => 'Regulasi Kedua Untuk Uji Taut',
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->put("/perencanaan/indikator/{$indikator->id}", [
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R221-LINK',
            'nama' => 'Indikator Menaut Regulasi Kedua',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $regulasiKedua->id,
            'expected_updated_at' => $indikator->fresh()->updated_at?->toISOString() ?? $indikator->fresh()->created_at->toISOString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame($regulasiKedua->id, $indikator->fresh()->regulasi_id);
        $this->assertSame('Indikator Menaut Regulasi Kedua', $indikator->fresh()->nama);

        $audit = AuditLog::where('tindakan', 'indikator.ubah')
            ->where('objek_id', (string) $indikator->id)
            ->latest('waktu')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame($regulasiKedua->id, $audit->nilai_baru['regulasi_id'] ?? null);
    }

    public function test_hapus_regulasi_ditolak_selama_dirujuk_indikator_aktif_tanpa_mutasi_parsial(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R221-GUARD',
            'deskripsi' => 'Sasaran uji guard hapus regulasi',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R221-GUARD',
            'nama' => 'Indikator Merujuk Regulasi',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $this->regulasi->id,
        ]);

        $response = $this->actingAs($this->perencanaan)->delete("/regulasi/{$this->regulasi->id}", [
            'alasan' => 'Upaya hapus regulasi yang masih dirujuk.',
        ]);

        $response->assertSessionHasErrors('regulasi');

        // Tanpa mutasi parsial: kedua baris utuh + rujukan bertahan.
        $this->assertDatabaseHas('regulasi', ['id' => $this->regulasi->id]);
        $this->assertSame($this->regulasi->id, $indikator->fresh()->regulasi_id);
        $this->assertSame('aktif', $indikator->fresh()->status);

        $this->assertDatabaseHas('audit_log', [
            'tindakan' => 'regulasi.hapus_ditolak',
            'objek_id' => $this->regulasi->id,
        ]);
        $this->assertSame(0, AuditLog::where('tindakan', 'regulasi.hapus')->where('objek_id', $this->regulasi->id)->count());
    }

    /**
     * Dua koneksi PDO nyata ke PostgreSQL disposable: sisi hapus memegang
     * Regulasi (FOR UPDATE, cermin RegulasiService::delete) lalu sisi ubah
     * meminta Regulasi dahulu (FOR SHARE, cermin UpdateIndikator 2c) —
     * hasilnya menunggu (55P03 di bawah lock_timeout), BUKAN deadlock
     * (40P01). Kunci kedua sisi hapus (Indikator FOR UPDATE, cermin
     * referensiAktifTerkunci) dapat dilanjutkan karena sisi ubah tak pernah
     * memegang Indikator. Dua ronde NOWAIT terpisah membuktikan kedua arah
     * saling-tunggu pada urutan lama (Indikator-dahulu vs Regulasi-dahulu)
     * yang pada kunci blocking menjadi 40P01.
     *
     * Batas bukti: orkestrasi antar-koneksi sekuensial dalam satu proses
     * (bukan dua proses paralel seperti AccountConcurrencyTest::race);
     * pemblokiran baris dan SQLSTATE PostgreSQL-nya nyata. Tiap arah NOWAIT
     * diuji dengan holder sehat tersendiri: holder yang transaksinya sudah
     * abort tidak lagi menahan waiter pada stack PDO pgsql/PG17 ini
     * (dibuktikan via probe mandiri dua-PDO), sehingga sonde gabungan dalam
     * satu pasangan koneksi akan memberi hasil semu. Tidak ada klaim 40P01
     * end-to-end (itu butuh dua proses benar-benar paralel).
     */
    public function test_dua_koneksi_urutan_kunci_regulasi_dahulu_tanpa_deadlock(): void
    {
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-R221-LOCK',
            'deskripsi' => 'Sasaran uji dua koneksi',
            'urutan' => 1,
        ]);

        $indikator = $this->buatIndikator([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IKU-R221-LOCK',
            'nama' => 'Indikator Uji Dua Koneksi',
            'satuan' => '%',
            'unit_id' => $this->unit->id,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'regulasi_id' => $this->regulasi->id,
        ]);

        $regulasiId = $this->regulasi->id;
        $indikatorId = $indikator->id;

        $pdoHapus = $this->koneksiPengujian();
        $pdoUbah = $this->koneksiPengujian();

        try {
            // Sisi hapus: kunci pertama Regulasi FOR UPDATE.
            $pdoHapus->exec('BEGIN');
            $kunciRegulasiHapus = $pdoHapus->prepare('SELECT id FROM regulasi WHERE id = :id FOR UPDATE');
            $kunciRegulasiHapus->execute(['id' => $regulasiId]);
            $this->assertSame($regulasiId, $kunciRegulasiHapus->fetchColumn());

            // Sisi ubah (urutan global baru): minta Regulasi FOR SHARE
            // dahulu, sebelum menyentuh Indikator. Karena sisi hapus
            // memegang FOR UPDATE, permintaan ini MENUNGGU lalu menyentuh
            // lock_timeout — tanpa deadlock.
            $pdoUbah->exec('BEGIN');
            $pdoUbah->exec("SET LOCAL lock_timeout = '2s'");
            try {
                $kunciRegulasiUbah = $pdoUbah->prepare('SELECT id FROM regulasi WHERE id = :id FOR SHARE');
                $kunciRegulasiUbah->execute(['id' => $regulasiId]);
                $this->fail('Sisi ubah seharusnya menunggu kunci Regulasi yang dipegang sisi hapus.');
            } catch (PDOException $exception) {
                $this->assertSame('55P03', $this->kodeSqlState($exception), 'Sisi ubah harus menunggu (lock timeout), bukan deadlock.');
            }
            $pdoUbah->exec('ROLLBACK');

            // Sisi hapus dapat melanjutkan ke kunci kedua (Indikator
            // FOR UPDATE, cermin referensiAktifTerkunci) karena sisi ubah
            // tak pernah memegang Indikator — tanpa tunggu-saling.
            $kunciIndikatorHapus = $pdoHapus->prepare('SELECT id FROM indikator_kinerjas WHERE id = :id FOR UPDATE');
            $kunciIndikatorHapus->execute(['id' => $indikatorId]);
            $this->assertSame($indikatorId, $kunciIndikatorHapus->fetchColumn());
            $pdoHapus->exec('ROLLBACK');

            // Sonde inversi lama dalam dua ronde dengan holder sehat per ronde
            // (holder yang transaksinya sudah abort tidak lagi menahan waiter
            // pada stack PDO pgsql/PG17 ini — dibuktikan via probe mandiri —
            // sehingga tiap arah diuji dengan pasangan koneksi segar):
            // Ronde 1: A pegang Indikator (FOR UPDATE, cermin urutan lama
            // sisi-ubah) → B minta Indikator NOWAIT → 55P03.
            $pdoA = $this->koneksiPengujian();
            $pdoB = $this->koneksiPengujian();
            try {
                $pdoA->exec('BEGIN');
                $pdoA->prepare('SELECT id FROM indikator_kinerjas WHERE id = :id FOR UPDATE')->execute(['id' => $indikatorId]);

                $pdoB->exec('BEGIN');
                try {
                    $pdoB->prepare('SELECT id FROM indikator_kinerjas WHERE id = :id FOR UPDATE NOWAIT')->execute(['id' => $indikatorId]);
                    $this->fail('Sonde ronde 1: permintaan Indikator sisi-hapus seharusnya terhalang NOWAIT.');
                } catch (PDOException $exception) {
                    $this->assertSame('55P03', $this->kodeSqlState($exception));
                }
            } finally {
                $this->rollbackTenang($pdoA ?? null);
                $this->rollbackTenang($pdoB ?? null);
            }

            // Ronde 2: B pegang Regulasi (FOR UPDATE, cermin delete) → A minta
            // Regulasi NOWAIT → 55P03. Bersama ronde 1: urutan lama
            // (Indikator-dahulu vs Regulasi-dahulu) saling menunggu pada kunci
            // blocking = 40P01; urutan global baru (keduanya Regulasi dahulu)
            // hanya antre, terbukti pada bagian utama di atas.
            $pdoA = $this->koneksiPengujian();
            $pdoB = $this->koneksiPengujian();
            try {
                $pdoB->exec('BEGIN');
                $pdoB->prepare('SELECT id FROM regulasi WHERE id = :id FOR UPDATE')->execute(['id' => $regulasiId]);

                $pdoA->exec('BEGIN');
                try {
                    $pdoA->prepare('SELECT id FROM regulasi WHERE id = :id FOR SHARE NOWAIT')->execute(['id' => $regulasiId]);
                    $this->fail('Sonde ronde 2: permintaan Regulasi sisi-ubah seharusnya terhalang NOWAIT.');
                } catch (PDOException $exception) {
                    $this->assertSame('55P03', $this->kodeSqlState($exception));
                }
            } finally {
                $this->rollbackTenang($pdoA ?? null);
                $this->rollbackTenang($pdoB ?? null);
            }
        } finally {
            $this->rollbackTenang($pdoHapus);
            $this->rollbackTenang($pdoUbah);
        }

        // Hasil deterministik: tanpa mutasi/audit parsial dari sonde kunci.
        $this->assertSame($regulasiId, $indikator->fresh()->regulasi_id);
        $this->assertSame('Indikator Uji Dua Koneksi', $indikator->fresh()->nama);
        $this->assertDatabaseHas('regulasi', ['id' => $regulasiId]);
        $this->assertSame(0, AuditLog::where('tindakan', 'indikator.ubah')->where('objek_id', (string) $indikatorId)->count());
        $this->assertSame(0, AuditLog::where('tindakan', 'regulasi.hapus')->where('objek_id', (string) $regulasiId)->count());
        $this->assertSame(0, AuditLog::where('tindakan', 'regulasi.hapus_ditolak')->where('objek_id', (string) $regulasiId)->count());
    }

    /**
     * Membuat Indikator langsung via model dengan kolom lifecycle wajib
     * terisi (pola helper yang sama di SasaranIndikatorTest).
     */
    private function buatIndikator(array $atribut): IndikatorKinerja
    {
        return IndikatorKinerja::create(array_merge([
            'status' => 'aktif',
            'tahun_mulai_berlaku' => $this->renstra->tahun_mulai,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'perencanaan',
        ], $atribut));
    }

    private function buatUserDenganRole(string $roleName, string $email): User
    {
        $role = Role::where('kode', $roleName)->firstOrFail();
        $user = User::factory()->create([
            'email' => $email,
            'status' => 'aktif',
        ]);

        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function pasangPresetRole(string $roleName): void
    {
        $role = Role::where('kode', $roleName)->firstOrFail();
        $permissionCodes = RolePermissionPresets::forRole($roleName);

        $permissionIds = Permission::whereIn('kode', $permissionCodes)->pluck('id');

        $role->permissions()->syncWithoutDetaching(
            $permissionIds->mapWithKeys(fn (string $id) => [
                $id => [
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                ],
            ])->all()
        );
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
