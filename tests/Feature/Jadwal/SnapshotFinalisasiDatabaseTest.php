<?php

namespace Tests\Feature\Jadwal;

use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshotKomponen;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Finalisasi snapshot di level database (Review10 F4/F5).
 *
 * Trigger pada transisi jadwal ke `aktif` dan migrasi korektif backfill harus
 * bekerja tanpa bergantung pada kode aplikasi versi baru, sehingga aktivasi
 * dari worker lama pun tidak meninggalkan komposisi terbit yang masih terbuka.
 */
class SnapshotFinalisasiDatabaseTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_trigger_membekukan_snapshot_saat_jadwal_bertransisi_ke_aktif(): void
    {
        // Fixture membuat jadwal sudah `aktif` lewat INSERT, sehingga snapshot-nya masih terbuka
        // dan trigger (AFTER UPDATE) belum pernah berjalan.
        $this->assertFalse((bool) $this->context->fresh()->komposisi_final);

        DB::table('jadwal_tahunan')->where('id', $this->jadwal->id)->update(['status' => 'draft']);
        DB::table('jadwal_tahunan')->where('id', $this->jadwal->id)->update(['status' => 'aktif']);

        $this->assertTrue((bool) $this->context->fresh()->komposisi_final);
        $this->assertSisipanKomponenDitolak();
    }

    public function test_migrasi_korektif_membekukan_snapshot_terbit_yang_tertinggal(): void
    {
        $this->assertFalse((bool) $this->context->fresh()->komposisi_final);

        /** @var object{up: callable} $migrasi */
        $migrasi = require database_path('migrations/2026_10_08_000004_finalisasi_snapshot_terbit_korektif.php');
        $migrasi->up();

        $this->assertTrue((bool) $this->context->fresh()->komposisi_final);
        $this->assertSisipanKomponenDitolak();
    }

    /** Komposisi yang sudah terbit tidak dapat disisipi komponen (guard Review9 W1). */
    private function assertSisipanKomponenDitolak(): void
    {
        $komponen = IndikatorKomponen::create([
            'indikator_id' => $this->context->indikator_id, 'kode' => 'X', 'label' => 'Komponen uji',
            'peran' => 'penjumlah', 'bobot' => '1', 'urutan' => 1, 'aktif' => true, 'created_by' => $this->actor->id,
        ]);

        try {
            // Savepoint: penolakan trigger tidak membatalkan transaksi RefreshDatabase.
            DB::transaction(function () use ($komponen): void {
                JadwalSnapshotKomponen::create([
                    'jadwal_snapshot_id' => $this->context->id, 'komponen_id' => $komponen->id,
                    'kode' => 'X', 'label' => 'Sisipan', 'peran' => 'penjumlah', 'bobot' => '1', 'urutan' => 1,
                ]);
            });
            $this->fail('Komposisi terbit harus menolak komponen tambahan.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0] ?? null);
        }
    }
}
