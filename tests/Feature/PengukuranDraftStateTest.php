<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class PengukuranDraftStateTest extends TestCase
{
    use CreatesPengukuranFixture {
        setUp as private setUpPengukuran;
    }
    use RefreshDatabase;

    private const NARASI = ['progres_kegiatan', 'kendala_masalah', 'strategi_tindak_lanjut', 'tindak_lanjut_rekomendasi'];

    protected function setUp(): void
    {
        $this->setUpPengukuran();
        // Kasus draf dimulai kosong; fixture pengajuan bersama sengaja sudah memiliki narasi.
        $this->pengukuran->update(array_fill_keys(self::NARASI, null));
    }

    public function test_pengukuran_status_nilai_check_menegakkan_pasangan_status_dan_nilai(): void
    {
        $this->assertDitolakDb(['status_perhitungan' => 'terhitung', 'nilai' => null]);
        $this->assertDitolakDb(['status_perhitungan' => 'belum_dapat_diukur', 'nilai' => 0]);
        $this->assertDitolakDb(['status_perhitungan' => 'status_rekaan', 'nilai' => null]);

        DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->update(['status_perhitungan' => 'belum_dapat_diukur', 'nilai' => null]);
        $this->assertSame('belum_dapat_diukur', $this->pengukuran->fresh()->status_perhitungan);
    }

    public function test_narasi_draft_boleh_parsial_dan_catatan_tetap_dikosongkan(): void
    {
        $this->simpan(1, ['nilai' => 60, 'catatan' => 'Catatan awal.', 'progres_kegiatan' => 'Progres TW I.', 'kendala_masalah' => '   ',
            'strategi_tindak_lanjut' => 'Strategi.', 'tindak_lanjut_rekomendasi' => 'Tindak lanjut.'])->assertSessionHasNoErrors();
        $saved = $this->pengukuran->fresh();
        $this->assertSame('Progres TW I.', $saved->progres_kegiatan);
        $this->assertNull($saved->kendala_masalah);
        $this->assertSame('Tindak lanjut.', $saved->tindak_lanjut_rekomendasi);
        $audit = AuditLog::where('tindakan', 'pengukuran.draft')->sole();
        $this->assertSame($this->actor->id, $audit->actor_id);
        $this->assertSame($this->pengukuran->id, $audit->objek_id);
        $this->assertSame('pengukuran:update', $audit->dasar_izin['permission']);
        $this->assertNull($audit->nilai_lama['progres_kegiatan']);
        $this->assertSame('Strategi.', $audit->nilai_baru['strategi_tindak_lanjut']);
        $this->assertSame('terhitung', $audit->nilai_baru['status_perhitungan']);

        // Narasi yang tidak dikirim dipertahankan; null eksplisit mengosongkan. Catatan tetap kosong bila tidak dikirim.
        $this->simpan(2, ['nilai' => 60, 'progres_kegiatan' => null, 'kendala_masalah' => 'Kendala baru.'])->assertSessionHasNoErrors();
        $saved = $this->pengukuran->fresh();
        $this->assertNull($saved->progres_kegiatan);
        $this->assertSame('Kendala baru.', $saved->kendala_masalah);
        $this->assertSame('Strategi.', $saved->strategi_tindak_lanjut);
        $this->assertSame('Tindak lanjut.', $saved->tindak_lanjut_rekomendasi);
        $this->assertNull($saved->catatan);
    }

    public function test_batas_narasi_sepuluh_ribu_karakter(): void
    {
        $this->simpan(1, ['progres_kegiatan' => str_repeat('a', 10000)])->assertSessionHasNoErrors();
        $this->assertSame(10000, mb_strlen($this->pengukuran->fresh()->progres_kegiatan));

        $this->simpan(2, ['kendala_masalah' => str_repeat('b', 10001)])->assertSessionHasErrors('kendala_masalah');
        $this->assertNull($this->pengukuran->fresh()->kendala_masalah);
    }

    public function test_versi_basi_tidak_mengubah_narasi_atau_audit(): void
    {
        $this->simpan(1, ['progres_kegiatan' => 'Awal.'])->assertSessionHasNoErrors();
        $audits = AuditLog::where('tindakan', 'pengukuran.draft')->count();

        $this->simpan(1, ['progres_kegiatan' => 'Ditimpa.', 'kendala_masalah' => 'Baru.'])->assertSessionHasErrors('versi');

        $saved = $this->pengukuran->fresh();
        $this->assertSame('Awal.', $saved->progres_kegiatan);
        $this->assertNull($saved->kendala_masalah);
        $this->assertSame(2, $saved->versi);
        $this->assertSame($audits, AuditLog::where('tindakan', 'pengukuran.draft')->count());
    }

    public function test_belum_dapat_diukur_mengosongkan_realisasi_manual_lalu_dapat_dibatalkan(): void
    {
        $this->simpan(1, ['nilai' => 55, 'progres_kegiatan' => 'Tetap ada.'])->assertSessionHasNoErrors();

        $this->simpan(2, ['status_perhitungan' => 'belum_dapat_diukur'])->assertSessionHasNoErrors();
        $saved = $this->pengukuran->fresh();
        $this->assertSame('belum_dapat_diukur', $saved->status_perhitungan);
        $this->assertNull($saved->nilai);
        $this->assertNull($saved->alasan_tidak_dapat_dihitung);
        $this->assertSame('Tetap ada.', $saved->progres_kegiatan);
        $this->assertSame('belum_dapat_diukur', AuditLog::where('tindakan', 'pengukuran.draft')->orderByDesc('id')->firstOrFail()->nilai_baru['status_perhitungan']);

        // Input manual normal "0" tetap merupakan nilai ukur nol yang sah.
        $this->simpan(3, ['status_perhitungan' => null, 'nilai' => 0])->assertSessionHasNoErrors();
        $zero = $this->pengukuran->fresh();
        $this->assertSame('terhitung', $zero->status_perhitungan);
        $this->assertSame('0.000000000000', $zero->nilai);

        $this->simpan(4, [])->assertSessionHasNoErrors();
        $this->assertSame('belum_diisi', $this->pengukuran->fresh()->status_perhitungan);
    }

    public function test_status_perhitungan_client_hanya_menerima_belum_dapat_diukur(): void
    {
        foreach (['terhitung', 'belum_diisi', 'tidak_dapat_dihitung'] as $status) {
            $this->simpan(1, ['status_perhitungan' => $status, 'nilai' => 50])->assertSessionHasErrors('status_perhitungan');
        }
        $this->assertSame(1, $this->pengukuran->fresh()->versi);
        $this->assertSame('belum_diisi', $this->pengukuran->fresh()->status_perhitungan);
    }

    public function test_belum_dapat_diukur_bersama_angka_atau_pada_periode_akhir_ditolak_tanpa_mengubah_state(): void
    {
        $this->simpan(1, ['nilai' => 40])->assertSessionHasNoErrors();

        $this->simpan(2, ['status_perhitungan' => 'belum_dapat_diukur', 'nilai' => 0])->assertSessionHasErrors('status_perhitungan');
        $this->pengukuran->periode->update(['is_nilai_akhir' => true]);
        $this->simpan(2, ['status_perhitungan' => 'belum_dapat_diukur'])->assertSessionHasErrors('status_perhitungan');

        $saved = $this->pengukuran->fresh();
        $this->assertSame('terhitung', $saved->status_perhitungan);
        $this->assertSame('40.000000000000', $saved->nilai);
        $this->assertSame(2, $saved->versi);
        $this->assertSame(2, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    public function test_rollback_migration_menolak_saat_data_baru_terpakai(): void
    {
        $migration = require database_path('migrations/2026_10_14_000001_extend_pengukuran_draft_narratives_and_status.php');
        foreach ([['status_perhitungan' => 'belum_dapat_diukur', 'nilai' => null], ['kendala_masalah' => 'Narasi tersimpan.']] as $used) {
            DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->update($used);
            try {
                $migration->down();
                $this->fail('Rollback seharusnya ditolak selama data baru terpakai.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Rollback ditolak', $exception->getMessage());
            }
            $this->assertTrue(Schema::hasColumns('pengukuran_kinerjas', self::NARASI));
            $this->assertSame($used, collect($this->pengukuran->fresh()->only(array_keys($used)))->all());
            DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->update(['status_perhitungan' => 'belum_diisi', 'nilai' => null, 'kendala_masalah' => null]);
        }

        $migration->down();
        $this->assertFalse(Schema::hasColumn('pengukuran_kinerjas', 'progres_kegiatan'));
        $this->assertDitolakDb(['status_perhitungan' => 'belum_dapat_diukur', 'nilai' => null]);
        $migration->up();
        $this->assertTrue(Schema::hasColumns('pengukuran_kinerjas', self::NARASI));
    }

    private function simpan(int $versi, array $data): TestResponse
    {
        return $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => $versi, 'action' => 'draft', ...$data]);
    }

    /** Pelanggaran CHECK dijalankan dalam savepoint agar transaksi test tetap dapat dipakai. */
    private function assertDitolakDb(array $values): void
    {
        try {
            DB::transaction(fn () => DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->update($values));
            $this->fail('CHECK pengukuran seharusnya menolak '.json_encode($values));
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
    }
}
