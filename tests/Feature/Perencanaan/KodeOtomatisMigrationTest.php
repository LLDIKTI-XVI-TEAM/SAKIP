<?php

namespace Tests\Feature\Perencanaan;

use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Regresi migrasi kode otomatis (Review10 P1/P2).
 *
 * Dua sifat yang dijaga: (1) langkah yang mengubah data — backfill `urutan` —
 * hanya berjalan setelah pre-check duplikat, sehingga rollout yang dihentikan
 * tidak meninggalkan nilai yang sudah ditimpa; (2) backfill snapshot terbit
 * diserialkan dengan jalur aktivasi memakai advisory lock yang sama.
 */
class KodeOtomatisMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function isiMigrasi(string $berkas): string
    {
        $isi = file_get_contents(database_path('migrations/'.$berkas));

        $this->assertIsString($isi);

        return $isi;
    }

    public function test_precheck_duplikat_menghentikan_migrasi_sebelum_backfill_menimpa_urutan(): void
    {
        // Indeks unik dibuka sementara supaya kondisi upgrade (kode duplikat) dapat disimulasikan.
        Schema::table('sasaran_strategis', function (Blueprint $table): void {
            $table->dropUnique('sasaran_strategis_kode_unik');
        });

        $user = User::factory()->create(['status' => 'aktif']);
        $renstra = Renstra::create([
            'kode' => 'REN-'.Str::random(8), 'nama' => 'Renstra Fixture Migrasi', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
            'status' => 'draft', 'dasar_hukum' => 'Kepmen fixture', 'created_by' => $user->id,
        ]);

        SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'SS-77', 'deskripsi' => 'Sasaran duplikat satu', 'urutan' => 42]);
        SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'SS-77', 'deskripsi' => 'Sasaran duplikat dua', 'urutan' => 43]);

        /** @var object{up: callable} $migrasi */
        $migrasi = require database_path('migrations/2026_10_08_000002_unique_kode_sasaran_indikator.php');

        try {
            $migrasi->up();
            $this->fail('Migrasi harus berhenti saat menemukan kode duplikat.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplikat', $exception->getMessage());
        }

        // Rollout yang dihentikan tidak boleh meninggalkan urutan yang sudah ditimpa backfill.
        $this->assertSame([42, 43], SasaranStrategis::where('kode', 'SS-77')->orderBy('urutan')->pluck('urutan')->all());
    }

    public function test_precheck_lolos_backfill_menyelaraskan_urutan_dan_indeks_dipasang(): void
    {
        Schema::table('sasaran_strategis', function (Blueprint $table): void {
            $table->dropUnique('sasaran_strategis_kode_unik');
        });
        Schema::table('indikator_kinerjas', function (Blueprint $table): void {
            $table->dropUnique('indikator_kinerjas_kode_unik');
        });

        $user = User::factory()->create(['status' => 'aktif']);
        $renstra = Renstra::create([
            'kode' => 'REN-'.Str::random(8), 'nama' => 'Renstra Fixture Backfill', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
            'status' => 'draft', 'dasar_hukum' => 'Kepmen fixture', 'created_by' => $user->id,
        ]);

        // Baris pra-upgrade: satu kode berurutan dengan urutan manual yang menyimpang,
        // satu kode legacy di luar pola yang harus dibiarkan apa adanya.
        SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'SS-05', 'deskripsi' => 'Sasaran kelima', 'urutan' => 99]);
        SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'SS-LAMA', 'deskripsi' => 'Sasaran legacy', 'urutan' => 7]);

        /** @var object{up: callable} $migrasi */
        $migrasi = require database_path('migrations/2026_10_08_000002_unique_kode_sasaran_indikator.php');
        $migrasi->up();

        $this->assertSame(5, SasaranStrategis::where('kode', 'SS-05')->value('urutan'));
        $this->assertSame(7, SasaranStrategis::where('kode', 'SS-LAMA')->value('urutan'));

        $indeks = DB::table('pg_indexes')->whereIn('indexname', ['sasaran_strategis_kode_unik', 'indikator_kinerjas_kode_unik'])->pluck('indexname');
        $this->assertEqualsCanonicalizing(['sasaran_strategis_kode_unik', 'indikator_kinerjas_kode_unik'], $indeks->all());
    }

    public function test_migrasi_kolom_tidak_mengubah_data(): void
    {
        $isi = $this->isiMigrasi('2026_10_08_000001_kode_otomatis_sasaran_indikator.php');

        $this->assertStringNotContainsString('backfillUrutan', $isi);
        $this->assertStringNotContainsString('->update(', $isi);
    }

    public function test_urutan_langkah_migrasi_penyiapan_data_dan_lock_serialisasi(): void
    {
        $penyiapan = $this->isiMigrasi('2026_10_08_000002_unique_kode_sasaran_indikator.php');
        $precheck = strpos($penyiapan, 'pastikanTidakAdaKodeDuplikat');
        $backfill = strpos($penyiapan, 'backfillUrutan');
        $this->assertNotFalse($precheck);
        $this->assertNotFalse($backfill);
        $this->assertLessThan($backfill, $precheck, 'Pre-check duplikat harus mendahului backfill.');

        $finalisasi = $this->isiMigrasi('2026_10_08_000003_finalisasi_snapshot_terbit.php');
        $kunci = "hashtextextended('sakip:periode-konfigurasi', 0)";
        $panggilan = "pg_advisory_xact_lock($kunci)";
        $this->assertStringContainsString($panggilan, $finalisasi);

        // Nama lock harus tetap sinkron dengan jalur aktivasi: kunci yang sama diambil
        // sebagai `pg_advisory_xact_lock_shared` oleh Periode::lockConfiguration().
        $periode = (string) file_get_contents(base_path('app/Models/Periode.php'));
        $this->assertStringContainsString($kunci, $periode);
        $this->assertStringContainsString('pg_advisory_xact_lock_shared', $periode);

        $this->assertLessThan(
            strpos($finalisasi, "update(['komposisi_final' => true])"),
            strpos($finalisasi, $panggilan),
            'Lock serialisasi harus diambil sebelum backfill snapshot.',
        );
    }

    public function test_lock_migrasi_berkonflik_dengan_lock_bersama_jalur_aktivasi(): void
    {
        config(['database.connections.pg_kedua' => config('database.connections.pgsql')]);
        $kedua = DB::connection('pg_kedua');
        $kedua->beginTransaction();

        try {
            $kedua->select("SELECT pg_advisory_xact_lock_shared(hashtextextended('sakip:periode-konfigurasi', 0))");

            try {
                // SET LOCAL di dalam savepoint: kegagalan lock-timeout tidak membatalkan transaksi RefreshDatabase.
                DB::transaction(function (): void {
                    DB::statement("SET LOCAL lock_timeout = '250ms'");
                    DB::select("SELECT pg_advisory_xact_lock(hashtextextended('sakip:periode-konfigurasi', 0))");
                });
                $this->fail('Kunci eksklusif migrasi harus menunggu lock bersama aktivasi.');
            } catch (QueryException $exception) {
                $this->assertSame('55P03', $exception->errorInfo[0] ?? null);
            }
        } finally {
            $kedua->rollBack();
            DB::purge('pg_kedua');
            config(['database.connections.pg_kedua' => null]);
        }
    }
}
