<?php

namespace Tests\Feature\StatusCapaian;

use App\Actions\StatusCapaian\TetapkanStatusCapaian;
use App\Models\AuditLog;
use App\Models\StatusCapaian;
use App\Support\PermissionCodes;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class TetapkanStatusCapaianTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    /** Token konkurensi dibaca dari basis data, bukan dari instance fixture yang belum memuat kolom default. */
    private function token(): int
    {
        return (int) DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->value('versi');
    }

    private function tetapkan(string $versiId, string $status, int|string|null $token = null): StatusCapaian
    {
        return app(TetapkanStatusCapaian::class)->handle(
            $this->actor,
            $this->pengukuran->id,
            [
                'pengukuran_versi_id' => $versiId,
                'status' => $status,
                'versi' => $token ?? $this->token(),
            ],
        );
    }

    /** Jalankan aksi dan kembalikan penolakan validasi bila ada (agar assertion audit tetap dapat dijalankan). */
    private function tolak(callable $aksi): ?ValidationException
    {
        try {
            $aksi();
        } catch (ValidationException $exception) {
            return $exception;
        }

        return null;
    }

    public function test_penetapan_pada_versi_disahkan_menulis_baris_manual(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $hasil = $this->tetapkan($versi->id, 'tercapai');

        $baris = DB::table('status_capaian')->first();
        $this->assertNotNull($baris);
        $this->assertSame($hasil->id, $baris->id);
        $this->assertSame($versi->id, $baris->pengukuran_versi_id);
        $this->assertSame($this->pengukuran->id, $baris->pengukuran_id);
        $this->assertSame('tercapai', $baris->status);
        $this->assertSame('manual', $baris->sumber);
        $this->assertSame($this->actor->id, $baris->ditetapkan_oleh);
        $this->assertSame(1, DB::table('status_capaian')->count());
        $this->assertTrue(AuditLog::where('tindakan', 'status_capaian.tetapkan')->exists());
    }

    /** @return array<string, array{string}> */
    public static function statusBelumDisahkan(): array
    {
        return [
            'draft' => ['draft'],
            'diajukan' => ['diajukan'],
            'diverifikasi' => ['diverifikasi'],
            'dikembalikan' => ['dikembalikan'],
        ];
    }

    #[DataProvider('statusBelumDisahkan')]
    public function test_penetapan_ditolak_bila_header_belum_disahkan(string $statusAlur): void
    {
        $versi = $this->ajukan($this->pengukuran, [], $statusAlur);

        $penolakan = $this->tolak(fn () => $this->tetapkan($versi->id, 'tercapai'));

        $this->assertInstanceOf(ValidationException::class, $penolakan, 'Penetapan pada versi belum disahkan harus ditolak.');
        $this->assertArrayHasKey('status_capaian', $penolakan->errors());
        $this->assertSame(0, DB::table('status_capaian')->count());

        $log = AuditLog::where('tindakan', 'status_capaian.tetapkan_ditolak')->first();
        $this->assertNotNull($log);
        $this->assertSame('validasi_bisnis', $log->nilai_baru['alasan_penolakan']);
        $this->assertSame($this->pengukuran->id, $log->nilai_baru['pengukuran_id']);
    }

    /**
     * Q-C: versi sah lama tetap tersimpan saat header dikembalikan, sehingga penolakan harus datang
     * dari guard status alur header, bukan dari anggapan versi belum/tidak sah.
     */
    public function test_penetapan_ditolak_bila_header_dikembalikan_walau_versi_masih_disahkan(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $this->tetapkan($versi->id, 'tercapai');

        $this->pengukuran->forceFill(['status_alur' => 'dikembalikan'])->save();

        // Prasyarat penolakan: versi tetap sah dan masih versi sah terakhir menurut header terkini.
        $this->assertNotNull($versi->fresh()?->disahkan_at, 'Versi harus tetap sah agar penolakan terbukti berasal dari status alur header.');
        $this->assertSame($versi->id, $this->pengukuran->fresh()?->ratifiedVersion?->id);
        $this->assertSame('dikembalikan', DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->value('status_alur'));

        $penolakan = $this->tolak(fn () => $this->tetapkan($versi->id, 'belum_tercapai'));

        $this->assertInstanceOf(ValidationException::class, $penolakan, 'Penetapan pada header yang dikembalikan harus ditolak.');
        $this->assertArrayHasKey('status_capaian', $penolakan->errors());
        $this->assertSame(
            ['Status capaian hanya dapat ditetapkan pada pengukuran berstatus Disahkan.'],
            $penolakan->errors()['status_capaian'] ?? [],
            'Penolakan harus berasal dari guard status alur header, bukan dari guard versi belum disahkan.',
        );
        $this->assertSame(1, DB::table('status_capaian')->count(), 'Penolakan tidak boleh menambah baris status capaian.');
    }

    public function test_status_versi_lama_tidak_berlaku_setelah_versi_koreksi_disahkan(): void
    {
        $versi1 = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $this->tetapkan($versi1->id, 'tercapai');

        $sebelumKoreksi = $this->pengukuran->fresh();
        $this->assertNotNull($sebelumKoreksi);
        $this->assertSame('tercapai', $sebelumKoreksi->statusCapaianBerlaku()?->status);

        $this->sahkan($this->pengukuran, ['nilai' => '90']);

        $sesudahKoreksi = $this->pengukuran->fresh();
        $this->assertNotNull($sesudahKoreksi);
        $this->assertNull($sesudahKoreksi->statusCapaianBerlaku());
        $this->assertSame(1, DB::table('status_capaian')->where('pengukuran_versi_id', $versi1->id)->count());
    }

    public function test_revisi_menulis_baris_baru_dan_menyimpan_riwayat_di_audit(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $this->tetapkan($versi->id, 'tercapai');
        $this->tetapkan($versi->id, 'belum_tercapai');

        $this->assertSame(2, DB::table('status_capaian')->count());
        $this->assertSame('belum_tercapai', StatusCapaian::berlaku($versi->id)->first()?->status);

        $revisi = AuditLog::where('tindakan', 'status_capaian.revisi')->first();
        $this->assertNotNull($revisi);
        $this->assertSame('tercapai', $revisi->nilai_lama['status']);
        $this->assertSame('belum_tercapai', $revisi->nilai_baru['status']);
        $this->assertSame($versi->id, $revisi->nilai_baru['pengukuran_versi_id']);
    }

    public function test_urutan_berlaku_memakai_mikrodetik_created_at(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);

        try {
            Carbon::setTestNow('2026-03-15 09:00:00.100000');
            CarbonImmutable::setTestNow('2026-03-15 09:00:00.100000');
            $pertama = $this->tetapkan($versi->id, 'tercapai');

            Carbon::setTestNow('2026-03-15 09:00:00.900000');
            CarbonImmutable::setTestNow('2026-03-15 09:00:00.900000');
            $kedua = $this->tetapkan($versi->id, 'belum_tercapai');
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }

        $this->assertSame('2026-03-15 09:00:00.100000', StatusCapaian::query()->findOrFail($pertama->id)->created_at->format('Y-m-d H:i:s.u'));
        $this->assertSame('2026-03-15 09:00:00.900000', StatusCapaian::query()->findOrFail($kedua->id)->created_at->format('Y-m-d H:i:s.u'));
        $this->assertSame('belum_tercapai', StatusCapaian::berlaku($versi->id)->first()?->status);
    }

    public function test_penetapan_ditolak_bila_versi_tanpa_target_periode(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80', 'tanpa_target_periode' => true]);

        $penolakan = $this->tolak(fn () => $this->tetapkan($versi->id, 'tercapai'));

        $this->assertInstanceOf(ValidationException::class, $penolakan);
        $this->assertArrayHasKey('status_capaian', $penolakan->errors());
        $this->assertSame(0, DB::table('status_capaian')->count());
        $log = AuditLog::where('tindakan', 'status_capaian.tetapkan_ditolak')->first();
        $this->assertNotNull($log);
        $this->assertSame('validasi_bisnis', $log->nilai_baru['alasan_penolakan']);
    }

    public function test_penetapan_ditolak_bila_status_perhitungan_belum_dapat_diukur(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80', 'status_perhitungan' => 'belum_dapat_diukur']);

        $penolakan = $this->tolak(fn () => $this->tetapkan($versi->id, 'tercapai'));

        $this->assertInstanceOf(ValidationException::class, $penolakan);
        $this->assertArrayHasKey('status_capaian', $penolakan->errors());
        $this->assertSame(0, DB::table('status_capaian')->count());
        $log = AuditLog::where('tindakan', 'status_capaian.tetapkan_ditolak')->first();
        $this->assertNotNull($log);
        $this->assertSame('validasi_bisnis', $log->nilai_baru['alasan_penolakan']);
    }

    public function test_penetapan_ditolak_bila_status_perhitungan_tidak_dapat_dihitung(): void
    {
        $versi = $this->sahkan($this->pengukuran, [
            'nilai' => '80',
            'status_perhitungan' => 'tidak_dapat_dihitung',
            'tanpa_target_periode' => false,
        ]);

        $penolakan = $this->tolak(fn () => $this->tetapkan($versi->id, 'tercapai'));

        $this->assertInstanceOf(ValidationException::class, $penolakan);
        $this->assertArrayHasKey('status_capaian', $penolakan->errors());
        $this->assertSame(0, DB::table('status_capaian')->count());
        $log = AuditLog::where('tindakan', 'status_capaian.tetapkan_ditolak')->first();
        $this->assertNotNull($log);
        $this->assertSame('validasi_bisnis', $log->nilai_baru['alasan_penolakan']);
    }

    public function test_token_versi_usang_ditolak_dengan_status_409(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $penolakan = $this->tolak(fn () => $this->tetapkan($versi->id, 'tercapai', $this->token() + 1));

        $this->assertInstanceOf(ValidationException::class, $penolakan);
        $this->assertArrayHasKey('versi', $penolakan->errors());
        $this->assertSame(409, $penolakan->status);
        $this->assertSame(0, DB::table('status_capaian')->count());
        $log = AuditLog::where('tindakan', 'status_capaian.tetapkan_ditolak')->first();
        $this->assertNotNull($log);
        $this->assertSame('konflik_versi', $log->nilai_baru['alasan_penolakan']);
    }

    public function test_versi_milik_pengukuran_lain_ditolak(): void
    {
        $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $headerLain = $this->headerUntuk($this->indikatorBaru('I-LAIN'));
        $versiLain = $this->sahkan($headerLain, ['nilai' => '40']);

        $penolakan = $this->tolak(fn () => $this->tetapkan($versiLain->id, 'tercapai'));

        $this->assertInstanceOf(ValidationException::class, $penolakan);
        $this->assertArrayHasKey('pengukuran_versi_id', $penolakan->errors());
        $this->assertSame(0, DB::table('status_capaian')->count());
        $this->assertTrue(AuditLog::where('tindakan', 'status_capaian.tetapkan_ditolak')->exists());
    }

    /**
     * Penolakan izin sebelum keputusan per unit tetap wajib menyimpan `dasar_izin`. Akun nonaktif
     * ditolak resolver sebelum ACL unit dibaca, sehingga tidak ada grant/deny unit yang menjadi sebab.
     */
    public function test_penolakan_aktor_nonaktif_mencatat_dasar_izin_tanpa_keputusan_per_unit(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $pelaku = $this->userWithRole('perencanaan');
        $pelaku->update(['status' => 'nonaktif']);
        $pelaku->refresh();

        try {
            app(TetapkanStatusCapaian::class)->handle($pelaku, $this->pengukuran->id, [
                'pengukuran_versi_id' => $versi->id,
                'status' => 'tercapai',
                'versi' => $this->token(),
            ]);
            $this->fail('Aktor nonaktif harus ditolak sebelum penetapan ditulis.');
        } catch (AuthorizationException) {
            $this->assertSame(0, DB::table('status_capaian')->count());
        }

        $log = AuditLog::where('tindakan', 'status_capaian.tetapkan_ditolak')->sole();
        $this->assertNotNull($log->dasar_izin, 'Penolakan sebelum keputusan per unit tetap wajib menyimpan dasar izin.');
        $this->assertSame(PermissionCodes::STATUS_CAPAIAN_UPDATE, $log->dasar_izin['permission']);
        $this->assertSame('ditolak', $log->dasar_izin['keputusan']);
        $this->assertSame('inactive_user', $log->dasar_izin['alasan']);
        $this->assertSame([], $log->dasar_izin['deny']);
        $this->assertSame(['roles' => [], 'grants' => []], $log->dasar_izin['sumber_allow']);
        $this->assertSame('izin_ditolak', $log->nilai_baru['alasan_penolakan']);
    }

    public function test_baris_status_capaian_tidak_dapat_diubah(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $this->tetapkan($versi->id, 'tercapai');

        $kegagalan = null;

        try {
            DB::table('status_capaian')->update(['status' => 'belum_tercapai']);
        } catch (QueryException $exception) {
            $kegagalan = $exception;
        }

        $this->assertInstanceOf(QueryException::class, $kegagalan, 'UPDATE pada status_capaian harus ditolak basis data.');
        $this->assertSame('23514', (string) ($kegagalan->errorInfo[0] ?? $kegagalan->getCode()));
    }

    public function test_baris_status_capaian_tidak_dapat_dihapus(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $this->tetapkan($versi->id, 'tercapai');

        $kegagalan = null;

        try {
            DB::table('status_capaian')->delete();
        } catch (QueryException $exception) {
            $kegagalan = $exception;
        }

        $this->assertInstanceOf(QueryException::class, $kegagalan, 'DELETE pada status_capaian harus ditolak basis data.');
        $this->assertSame('23514', (string) ($kegagalan->errorInfo[0] ?? $kegagalan->getCode()));
    }

    public function test_skema_status_capaian_memenuhi_kontrak_append_only(): void
    {
        $unique = DB::select(
            "SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'status_capaian'
             AND indexdef LIKE '%UNIQUE%' AND indexdef LIKE '%pengukuran_versi_id%'"
        );
        $this->assertSame([], $unique, 'Tidak boleh ada unique index per pengukuran_versi_id (riwayat revisi).');

        $checks = DB::select("SELECT conname FROM pg_constraint WHERE conrelid = 'status_capaian'::regclass AND contype = 'c'");
        $this->assertContains('status_capaian_ditetapkan_oleh_sesuai_sumber', array_column($checks, 'conname'));

        $foreignKeys = DB::select("SELECT conname, confdeltype FROM pg_constraint WHERE conrelid = 'status_capaian'::regclass AND contype = 'f'");
        $this->assertNotEmpty($foreignKeys);
        $this->assertNotEmpty(array_filter($foreignKeys, fn (object $fk): bool => $fk->confdeltype === 'r'), 'FK status_capaian harus ON DELETE RESTRICT.');

        $indexes = array_column(
            DB::select("SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'status_capaian'"),
            'indexname'
        );
        $this->assertContains('status_capaian_versi_berlaku_idx', $indexes);
        $this->assertContains('status_capaian_pengukuran_berlaku_idx', $indexes);
    }
}
