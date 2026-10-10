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
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/**
 * Regresi serialisasi INSERT komponen vs finalisasi komposisi snapshot.
 *
 * Guard INSERT lama membaca induk via SELECT biasa tanpa kunci sehingga
 * finalisasi konkuren (false→true) vs INSERT dapat lolos bersama: B membaca
 * false basi lalu commit setelah A. Perbaikan mengunci baris induk mode
 * berkonflik di kedua sisi (guard INSERT `SELECT ... FOR UPDATE` + sisi
 * finalisasi `PERFORM ... FOR UPDATE`), sehingga tak ada INSERT yang commit
 * setelah terbit. Koreksi berversi (snapshot baru + komponen selagi belum
 * final) tetap terbuka.
 */
class RencanaAksiSerialisasiFinalisasiKomposisiTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * Rebuild via migrate:fresh (bukan rollback) agar teardown tak menyentuh
     * down() migrasi lifecycle yang menolak baris pasca-cutover tanpa backup.
     * Fixture ter-commit dan terlihat oleh dua koneksi PDO eksternal.
     * Preseden pola: RencanaAksiLockOrderTest.
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
    }

    public function test_guard_insert_dan_finalisasi_memakai_kunci_baris_induk_berkonflik(): void
    {
        $definisi = (string) DB::selectOne(
            "SELECT pg_get_functiondef(oid) AS def FROM pg_proc WHERE proname = 'guard_referenced_schedule_snapshot' LIMIT 1"
        )->def;

        $this->assertStringContainsString(
            'FOR UPDATE',
            $definisi,
            'Guard harus mengunci induk mode berkonflik (FOR UPDATE).'
        );
        $this->assertMatchesRegularExpression(
            '/SELECT\s+ks\.komposisi_final\s+INTO\s+parent_final\s+FROM\s+jadwal_snapshot\s+AS\s+ks\s+WHERE\s+ks\.id\s*=\s*NEW\.jadwal_snapshot_id\s+FOR UPDATE/i',
            $definisi,
            'Guard INSERT harus SELECT induk FOR UPDATE (bukan SELECT biasa).'
        );
    }

    /**
     * Dua koneksi PDO nyata ke PostgreSQL disposable, orkestrasi sekuensial
     * dalam satu proses (bukan dua proses paralel seperti
     * AccountConcurrencyTest::race); pemblokiran baris dan SQLSTATE-nya nyata.
     *
     * Ronde 1: finalisasi (UPDATE) memegang lock induk → INSERT konkuren
     * menunggu (55P03 di bawah lock_timeout), BUKAN lolos bacaan basi. Tanpa
     * fix, INSERT akan sukses langsung tanpa menunggu (SELECT biasa tak
     * berkonflik). Setelah final commit, INSERT ulang ditolak 23514.
     *
     * Ronde 2 (arah balik): INSERT menahan induk via guard FOR UPDATE →
     * finalisasi konkuren menunggu (55P03). Tanpa fix, UPDATE akan sukses
     * langsung. Koreksi berversi setelah final tetap terbuka.
     *
     * Batas bukti: tidak ada klaim deadlock 40P01 end-to-end (itu butuh dua
     * proses benar-benar paralel); yang dibuktikan adalah serialisasi via
     * mode kunci berkonflik + penolakan pasca-final.
     */
    public function test_dua_koneksi_insert_vs_finalisasi_serialisasi_benar(): void
    {
        $fixture = $this->buatFixturePenjumlahan();
        $snapshotA = $fixture['snapshot'];
        $snapshotB = $this->terbitkanSnapshotLengkap($fixture, 2, $snapshotA->id, false);

        $komponenC = IndikatorKomponen::create([
            'indikator_id' => $fixture['indikator']->id,
            'kode' => 'c-w1',
            'label' => 'Komponen C W1',
            'peran' => 'penjumlah',
            'bobot' => 1,
            'urutan' => 3,
            'aktif' => true,
            'created_by' => $fixture['perencanaan']->id,
        ]);
        $komponenD = IndikatorKomponen::create([
            'indikator_id' => $fixture['indikator']->id,
            'kode' => 'd-w1',
            'label' => 'Komponen D W1',
            'peran' => 'penjumlah',
            'bobot' => 1,
            'urutan' => 4,
            'aktif' => true,
            'created_by' => $fixture['perencanaan']->id,
        ]);

        // Ronde 1: finalisasi dahulu memegang induk, INSERT menunggu.
        $pdoFinal = $this->koneksiPengujian();
        $pdoInsert = $this->koneksiPengujian();
        try {
            $pdoFinal->exec('BEGIN');
            $pdoFinal->prepare('UPDATE jadwal_snapshot SET komposisi_final = TRUE WHERE id = :id')
                ->execute(['id' => $snapshotA->id]);

            $pdoInsert->exec('BEGIN');
            $pdoInsert->exec("SET LOCAL lock_timeout = '2s'");
            try {
                $pdoInsert->prepare(
                    'INSERT INTO jadwal_snapshot_komponen (id, jadwal_snapshot_id, komponen_id, kode, label, peran, bobot, urutan) VALUES (:id, :snapshot, :komponen, :kode, :label, :peran, :bobot, :urutan)'
                )->execute([
                    'id' => (string) Str::uuid(),
                    'snapshot' => $snapshotA->id,
                    'komponen' => $komponenC->id,
                    'kode' => 'c-w1',
                    'label' => 'Komponen C W1',
                    'peran' => 'penjumlah',
                    'bobot' => 1,
                    'urutan' => 3,
                ]);
                $this->fail('INSERT konkuren harus menunggu finalisasi yang memegang kunci induk (lock timeout), bukan lolos bacaan basi.');
            } catch (PDOException $exception) {
                $this->assertSame('55P03', $this->kodeSqlState($exception), 'INSERT harus antre pada kunci induk finalisasi.');
            }
            $this->rollbackTenang($pdoInsert);

            $pdoFinal->exec('COMMIT');
            $this->assertTrue($snapshotA->fresh()->komposisi_final);

            // Setelah final commit, INSERT ulang ditolak 23514 (serialisasi benar).
            try {
                DB::transaction(function () use ($snapshotA, $komponenC): void {
                    JadwalSnapshotKomponen::create([
                        'jadwal_snapshot_id' => $snapshotA->id,
                        'komponen_id' => $komponenC->id,
                        'kode' => 'c-w1',
                        'label' => 'Komponen C W1',
                        'peran' => 'penjumlah',
                        'bobot' => 1,
                        'urutan' => 3,
                    ]);
                });
                $this->fail('INSERT setelah finalisasi commit harus ditolak 23514.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->getCode());
            }
            $this->assertSame(2, JadwalSnapshotKomponen::where('jadwal_snapshot_id', $snapshotA->id)->count());
        } finally {
            $this->rollbackTenang($pdoFinal);
            $this->rollbackTenang($pdoInsert);
        }

        // Ronde 2: arah balik — INSERT dahulu menahan induk, finalisasi menunggu.
        $pdoTahan = $this->koneksiPengujian();
        $pdoFinalisasi = $this->koneksiPengujian();
        try {
            $pdoTahan->exec('BEGIN');
            $pdoTahan->prepare(
                'INSERT INTO jadwal_snapshot_komponen (id, jadwal_snapshot_id, komponen_id, kode, label, peran, bobot, urutan) VALUES (:id, :snapshot, :komponen, :kode, :label, :peran, :bobot, :urutan)'
            )->execute([
                'id' => (string) Str::uuid(),
                'snapshot' => $snapshotB->id,
                'komponen' => $komponenD->id,
                'kode' => 'd-w1',
                'label' => 'Komponen D W1',
                'peran' => 'penjumlah',
                'bobot' => 1,
                'urutan' => 4,
            ]);

            $pdoFinalisasi->exec('BEGIN');
            $pdoFinalisasi->exec("SET LOCAL lock_timeout = '2s'");
            try {
                $pdoFinalisasi->prepare('UPDATE jadwal_snapshot SET komposisi_final = TRUE WHERE id = :id')
                    ->execute(['id' => $snapshotB->id]);
                $this->fail('Finalisasi konkuren harus menunggu INSERT yang menahan kunci induk, bukan menyalip.');
            } catch (PDOException $exception) {
                $this->assertSame('55P03', $this->kodeSqlState($exception), 'Finalisasi harus antre pada kunci induk INSERT.');
            }
            $pdoFinalisasi->exec('ROLLBACK');
            $pdoTahan->exec('ROLLBACK');

            // Tanpa mutasi parsial dari sonde kunci.
            $this->assertFalse((bool) $snapshotB->fresh()->komposisi_final);
            $this->assertSame(2, JadwalSnapshotKomponen::where('jadwal_snapshot_id', $snapshotB->id)->count());
        } finally {
            $this->rollbackTenang($pdoTahan);
            $this->rollbackTenang($pdoFinalisasi);
        }

        // Koreksi berversi tetap terbuka setelah finalisasi.
        $v3 = JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => 3,
            'menggantikan_id' => $snapshotB->id,
            'alasan_koreksi' => 'Koreksi resmi W1 serialisasi.',
            'rujukan_koreksi' => 'SK-KOREKSI-R9W1-001',
            'periode_mulai_id' => $fixture['periode1']->id,
            'unit_id' => $fixture['unit']->id,
            'nama' => $fixture['indikator']->nama,
            'definisi' => 'Definisi beku v3 W1.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'penjumlahan',
            'target' => 100,
        ]);
        foreach ([$fixture['komponenA'], $fixture['komponenB']] as $index => $komponen) {
            JadwalSnapshotKomponen::create([
                'jadwal_snapshot_id' => $v3->id,
                'komponen_id' => $komponen->id,
                'kode' => $komponen->kode,
                'label' => $komponen->label,
                'peran' => $komponen->peran,
                'bobot' => 1,
                'urutan' => $index + 1,
            ]);
        }
        $this->assertSame(2, JadwalSnapshotKomponen::where('jadwal_snapshot_id', $v3->id)->count());
        $v3->update(['komposisi_final' => true]);
        $this->assertTrue($v3->fresh()->komposisi_final);
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixturePenjumlahan(): array
    {
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji R9W1 Jumlah', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-R9W1J', 'nama' => 'Renstra Uji R9W1 Jumlah', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-R9W1J', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Jumlah Uji R9W1',
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
            'tipe_perhitungan' => 'penjumlahan',
            'target' => 100,
        ]);
        $komponenA = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 'a',
            'label' => 'Komponen A',
            'peran' => 'penjumlah',
            'bobot' => 1,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $perencanaan->id,
        ]);
        $komponenB = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 'b',
            'label' => 'Komponen B',
            'peran' => 'penjumlah',
            'bobot' => 1,
            'urutan' => 2,
            'aktif' => true,
            'created_by' => $perencanaan->id,
        ]);
        foreach ([$komponenA, $komponenB] as $index => $komponen) {
            JadwalSnapshotKomponen::create([
                'jadwal_snapshot_id' => $snapshot->id,
                'komponen_id' => $komponen->id,
                'kode' => $komponen->kode,
                'label' => $komponen->label,
                'peran' => $komponen->peran,
                'bobot' => 1,
                'urutan' => $index + 1,
            ]);
        }
        PenugasanIndikator::create([
            'indikator_id' => $indikator->id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $perencanaan->id,
            'created_at' => now(),
        ]);

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periode1', 'periode2', 'jadwal', 'snapshot', 'komponenA', 'komponenB');
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function terbitkanSnapshotLengkap(array $fixture, int $nomorVersi, string $menggantikanId, bool $finalkanLangsung): JadwalSnapshot
    {
        $snapshot = JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => $nomorVersi,
            'menggantikan_id' => $menggantikanId,
            'alasan_koreksi' => 'Koreksi resmi R9W1.',
            'rujukan_koreksi' => 'SK-KOREKSI-R9W1-'.str_pad((string) $nomorVersi, 3, '0', STR_PAD_LEFT),
            'periode_mulai_id' => $fixture['periode1']->id,
            'unit_id' => $fixture['unit']->id,
            'nama' => $fixture['indikator']->nama,
            'definisi' => 'Definisi beku R9W1.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'penjumlahan',
            'target' => 100,
            'komposisi_final' => $finalkanLangsung,
        ]);
        foreach ([$fixture['komponenA'], $fixture['komponenB']] as $index => $komponen) {
            JadwalSnapshotKomponen::create([
                'jadwal_snapshot_id' => $snapshot->id,
                'komponen_id' => $komponen->id,
                'kode' => $komponen->kode,
                'label' => $komponen->label,
                'peran' => $komponen->peran,
                'bobot' => 1,
                'urutan' => $index + 1,
            ]);
        }

        return $snapshot;
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
