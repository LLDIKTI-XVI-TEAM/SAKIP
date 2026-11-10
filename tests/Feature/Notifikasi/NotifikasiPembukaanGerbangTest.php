<?php

namespace Tests\Feature\Notifikasi;

use App\Jobs\KirimNotifikasiEksternal;
use App\Models\NotifikasiPengiriman;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Notifikasi\AntrekanNotifikasiEksternal;
use App\Services\Notifikasi\HasilKirim;
use App\Services\Notifikasi\PengirimNotifikasiEksternal;
use App\Services\Notifikasi\PenyediaKanalNotifikasi;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Gerbang kirim prefiks `jendela_dibuka:`: sebelum setiap upaya kirim penerima harus aktif, berperan aktif, masih
 * PJ efektif indikator wajib pada jendela yang berjalan, dan punya hak isi yang sesuai status header. Jendela
 * fixture: Triwulan I, 2026-03-01 sampai 2026-03-15.
 */
class NotifikasiPembukaanGerbangTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    private PeriodeJadwal $tw1;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        config(['notifikasi.kanal.email.aktif' => true]);
        $this->tw1 = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
        $this->travelKe('2026-03-10 09:00');
    }

    private function travelKe(string $waktu): void
    {
        $this->travelTo(CarbonImmutable::parse($waktu, 'Asia/Makassar'));
    }

    private function antre(User $penerima): NotifikasiPengiriman
    {
        return app(AntrekanNotifikasiEksternal::class)->antre('jendela_dibuka:2026-03-10', $this->tw1, (string) $this->tw1->periode_id,
            [$penerima->id], ['email'], 'Jendela pengisian {{periode}} {{tahun}} dibuka.', ['periode' => 'Triwulan I', 'tahun' => 2026])->firstOrFail();
    }

    private function kirim(NotifikasiPengiriman $row): void
    {
        (new KirimNotifikasiEksternal($row->id))->handle(app(PengirimNotifikasiEksternal::class));
    }

    /** Pegawai PJ satu indikator tambahan pada jendela fixture; hak isi diberikan terpisah. */
    private function picDenganIndikator(string $kode, bool $hakIsi = true): array
    {
        $pic = $this->userWithRole('pegawai');
        $snapshot = $this->indikatorBaru($kode);
        PenugasanIndikator::create(['indikator_id' => $snapshot->indikator_id, 'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        if ($hakIsi) {
            $this->grant($pic, 'pengukuran:create');
            $this->grant($pic, 'pengukuran:update');
        }

        return [$pic, $snapshot];
    }

    private function providerPalsu(HasilKirim $hasil): void
    {
        $this->app->instance(PenyediaKanalNotifikasi::class, new class($hasil) implements PenyediaKanalNotifikasi
        {
            public function __construct(private HasilKirim $hasil) {}

            public function kirim(NotifikasiPengiriman $pengiriman): HasilKirim
            {
                return $this->hasil;
            }
        });
    }

    public function test_pj_dengan_hak_isi_menerima_pesan_pembukaan(): void
    {
        [$pic] = $this->picDenganIndikator('I-BUKA-LAYAK');
        $row = $this->antre($pic);

        $this->kirim($row);

        $this->assertSame(['diterima_provider', null, 'log:'.$row->id], [$row->fresh()->status, $row->fresh()->kode_hasil, $row->fresh()->rujukan_provider]);
    }

    public function test_hari_terakhir_jendela_masih_layak(): void
    {
        [$pic] = $this->picDenganIndikator('I-BUKA-AKHIR');
        $row = $this->antre($pic);
        // Pagi hari terakhir di WITA masih tanggal 14 Maret di UTC.
        $this->travelKe('2026-03-15 07:00');

        $this->kirim($row);

        $this->assertSame('diterima_provider', $row->fresh()->status);
    }

    #[DataProvider('penerimaTidakLayak')]
    public function test_penerima_tidak_layak_dibatalkan_sebelum_provider(string $kasus): void
    {
        [$pic, $snapshot] = $this->picDenganIndikator('I-BUKA-TOLAK', hakIsi: $kasus !== 'tanpa hak isi');
        $row = $this->antre($pic);
        $this->assertSame('antre', $row->status);

        match ($kasus) {
            'akun nonaktif' => $pic->update(['status' => 'nonaktif']),
            'tanpa hak isi' => null,
            'pj dialihkan' => PenugasanIndikator::create(['indikator_id' => $snapshot->indikator_id, 'user_id' => $this->userWithRole('pegawai')->id,
                'tanggal_mulai_berlaku' => '2026-03-05', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]),
            // Pagi setelah tenggat di WITA masih tanggal 15 Maret di UTC.
            'jendela tertutup' => $this->travelKe('2026-03-16 07:00'),
            'kewajiban sudah diajukan' => $this->ajukan($this->headerUntuk($snapshot), []),
        };

        $this->kirim($row);

        $this->assertSame(['dibatalkan', 'penerima_tidak_berhak', null], [$row->fresh()->status, $row->fresh()->kode_hasil, $row->fresh()->rujukan_provider]);
        // Tanpa pengalihan: tidak ada baris baru untuk siapa pun, termasuk PJ pengganti.
        $this->assertDatabaseCount('notifikasi_pengiriman', 1);
    }

    /** @return array<string, array{0: string}> */
    public static function penerimaTidakLayak(): array
    {
        return [
            'akun nonaktif' => ['akun nonaktif'],
            'tanpa hak isi' => ['tanpa hak isi'],
            'pj dialihkan' => ['pj dialihkan'],
            'jendela tertutup' => ['jendela tertutup'],
            'kewajiban sudah diajukan' => ['kewajiban sudah diajukan'],
        ];
    }

    public function test_provider_gagal_sementara_menjadwalkan_coba_lagi(): void
    {
        [$pic] = $this->picDenganIndikator('I-BUKA-SEMENTARA');
        $row = $this->antre($pic);
        $this->providerPalsu(HasilKirim::sementara(120));

        $jeda = app(PengirimNotifikasiEksternal::class)->kirim($row->id, 4);

        $this->assertSame(120, $jeda);
        $this->assertSame(['antre', 'provider_sementara', 1], [$row->fresh()->status, $row->fresh()->kode_hasil, $row->fresh()->percobaan]);
        $this->assertNotNull($row->fresh()->coba_lagi_pada);
    }

    public function test_provider_menolak_permanen_menandai_gagal(): void
    {
        [$pic] = $this->picDenganIndikator('I-BUKA-PERMANEN');
        $row = $this->antre($pic);
        $this->providerPalsu(HasilKirim::permanen());

        $this->kirim($row);

        $this->assertSame(['gagal', 'provider_ditolak'], [$row->fresh()->status, $row->fresh()->kode_hasil]);
    }
}
