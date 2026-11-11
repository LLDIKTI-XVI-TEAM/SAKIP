<?php

namespace Tests\Feature\Notifikasi;

use App\Jobs\KirimNotifikasiEksternal;
use App\Models\NotifikasiPengiriman;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Notifikasi\AntrekanNotifikasiEksternal;
use App\Services\Notifikasi\PengirimNotifikasiEksternal;
use App\Services\Pengingat\KelayakanPenerimaEws;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Fondasi outbox tujuan `jadwal_periode` dan gerbang kirim prefiks `ews:` (ISS-12.04 fase 2).
 *
 * Producer hanya mencatat tujuan/periode/parameter; hak isi dinilai ulang pada gerbang kirim
 * lewat {@see KelayakanPenerimaEws}. Scheduler dan time-gate `jam_kirim` bukan cakupan berkas ini.
 */
class NotifikasiEwsOutboxTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    private PeriodeJadwal $tw1;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        config(['notifikasi.kanal.email.aktif' => true]);
        $this->tw1 = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
    }

    public function test_constraint_objek_tipe_menerima_jadwal_periode_dan_menolak_nilai_asing(): void
    {
        DB::table('notifikasi_pengiriman')->insert($this->barisMentah('jadwal_periode', 'sah'));
        $this->assertDatabaseHas('notifikasi_pengiriman', [
            'objek_tipe' => 'jadwal_periode',
            'objek_id' => $this->tw1->id,
        ]);

        try {
            DB::transaction(fn () => DB::table('notifikasi_pengiriman')->insert($this->barisMentah('jadwal_periode_lain', 'asing')));
            $this->fail('Constraint objek_tipe harus menolak nilai di luar daftar.');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
    }

    public function test_producer_membuat_baris_jadwal_periode_idempoten_tanpa_menyaring_hak_isi(): void
    {
        $row = $this->antre();
        $this->assertSame('jadwal_periode', $row->objek_tipe);
        $this->assertSame($this->tw1->id, $row->objek_id);
        $this->assertSame($this->tw1->id.':2026-03-15', $row->konteks_periode);
        $this->assertEquals(['h' => 1, 'jatuh_tempo' => '2026-03-15', 'jumlah' => 1], $row->parameter);
        $this->assertSame('antre', $row->status);

        $this->assertSame($row->id, $this->antre()->id);
        $this->assertDatabaseCount('notifikasi_pengiriman', 1);

        // PM review #251 C2: kandidat tanpa hak isi tetap dicatat producer; penyaringan di gerbang kirim.
        $pic = $this->userWithRole('pegawai');
        $this->picDenganIndikator('I-EWS-TANPA-GRANT', $pic);
        $tanpaHak = $this->antre('ews:h1', userId: $pic->id);
        $this->assertSame('antre', $tanpaHak->status);
        $this->assertSame($pic->id, $tanpaHak->penerima_user_id);
    }

    public function test_penerima_layak_menerima_hasil_provider_log(): void
    {
        $this->travelKe('2026-03-14 09:00');
        // Kontrol fixture: pada H-1 PIC ini memang layak menurut evaluator gerbang kirim.
        $this->assertTrue(app(KelayakanPenerimaEws::class)->layak(
            $this->actor,
            $this->tw1,
            1,
            CarbonImmutable::parse('2026-03-14 09:00', 'Asia/Makassar'),
        ));

        $row = $this->antre();
        $this->kirim($row);

        $this->assertSame('diterima_provider', $row->fresh()->status);
        $this->assertNull($row->fresh()->kode_hasil);
        $this->assertSame('log:'.$row->id, $row->fresh()->rujukan_provider);
    }

    public function test_penerima_tanpa_hak_isi_dibatalkan_sebelum_provider(): void
    {
        $this->travelKe('2026-03-14 09:00');
        $pic = $this->userWithRole('pegawai');
        $this->picDenganIndikator('I-EWS-GERBANG', $pic);
        $row = $this->antre(userId: $pic->id);

        $this->kirim($row);

        $this->assertSame('dibatalkan', $row->fresh()->status);
        $this->assertSame('penerima_tidak_berhak', $row->fresh()->kode_hasil);
        $this->assertNull($row->fresh()->rujukan_provider);
    }

    public function test_akun_nonaktif_sebelum_kirim_dibatalkan(): void
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:create');
        $this->picDenganIndikator('I-EWS-NONAKTIF', $pic);
        $row = $this->antre(userId: $pic->id);
        $pic->update(['status' => 'nonaktif']);

        $this->kirim($row);

        $this->assertSame('dibatalkan', $row->fresh()->status);
        $this->assertSame('penerima_tidak_berhak', $row->fresh()->kode_hasil);
    }

    /** @param  array<string, mixed>  $parameter */
    #[DataProvider('hTidakValid')]
    public function test_h_hilang_atau_invalid_fail_closed(array $parameter): void
    {
        $row = $this->antre(parameter: $parameter);

        $this->kirim($row);

        $this->assertSame('dibatalkan', $row->fresh()->status);
        $this->assertSame('penerima_tidak_berhak', $row->fresh()->kode_hasil);
    }

    public static function hTidakValid(): array
    {
        return [
            'hilang' => [['jumlah' => 1]],
            'bukan angka' => [['h' => '1x', 'jumlah' => 1]],
            'negatif' => [['h' => -1, 'jumlah' => 1]],
        ];
    }

    public function test_prefiks_tidak_dikenal_fail_closed_untuk_jadwal_periode(): void
    {
        // Tanggal kontrol sama dengan test_penerima_layak_menerima_hasil_provider_log:
        // penerima memang layak pada instan ini, sehingga satu-satunya alasan
        // dibatalkan adalah cek prefiks `ews:`.
        $this->travelKe('2026-03-14 09:00');
        $row = $this->antre('tak_dikenal:2026-03-15');
        $this->assertSame('antre', $row->status);

        $this->kirim($row);

        $this->assertSame('dibatalkan', $row->fresh()->status);
        $this->assertSame('penerima_tidak_berhak', $row->fresh()->kode_hasil);
    }

    public function test_tenggat_berubah_setelah_antre_dibatalkan_walau_h_sama(): void
    {
        $this->travelKe('2026-03-14 09:00');
        $row = $this->antre();
        $this->assertSame('antre', $row->status);

        // Worker tertahan sehari dan tenggat diperpanjang sehari: H terhadap tenggat baru tetap 1.
        DB::table('jadwal_periode')->where('id', $this->tw1->id)->update(['pengisian_selesai' => '2026-03-16']);
        $this->travelKe('2026-03-15 09:00');

        $this->kirim($row);

        $this->assertSame(['dibatalkan', 'penerima_tidak_berhak'], [$row->fresh()->status, $row->fresh()->kode_hasil]);
    }

    private function travelKe(string $waktu): void
    {
        $this->travelTo(CarbonImmutable::parse($waktu, 'Asia/Makassar'));
    }

    /**
     * @param  array<string, mixed>|null  $parameter
     */
    private function antre(string $kejadian = 'ews:h1', ?array $parameter = null, ?string $userId = null): NotifikasiPengiriman
    {
        return app(AntrekanNotifikasiEksternal::class)->antre(
            $kejadian,
            $this->tw1,
            $this->tw1->id.':2026-03-15',
            [$userId ?? $this->actor->id],
            ['email'],
            'Pengingat tenggat {{jumlah}} indikator. Batas {{jatuh_tempo}}.',
            $parameter ?? ['h' => 1, 'jatuh_tempo' => '2026-03-15', 'jumlah' => 1],
        )->firstOrFail();
    }

    private function kirim(NotifikasiPengiriman $row): void
    {
        (new KirimNotifikasiEksternal($row->id))->handle(app(PengirimNotifikasiEksternal::class));
    }

    private function picDenganIndikator(string $kode, User $pic): void
    {
        $snapshot = $this->indikatorBaru($kode);

        PenugasanIndikator::create([
            'indikator_id' => $snapshot->indikator_id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function barisMentah(string $objekTipe, string $kunci): array
    {
        return [
            'id' => (string) Str::uuid(),
            'kunci_idempoten' => hash('sha256', $kunci),
            'kejadian' => 'ews:h1',
            'objek_tipe' => $objekTipe,
            'objek_id' => $this->tw1->id,
            'konteks_periode' => $this->tw1->id.':2026-03-15',
            'kanal' => 'email',
            'penerima_user_id' => $this->actor->id,
            'alamat' => $this->actor->email,
            'template' => 'Pengingat sintetis.',
            'parameter' => json_encode(['h' => 1], JSON_THROW_ON_ERROR),
            'driver' => 'log',
            'simulasi' => true,
            'status' => 'antre',
            'kode_hasil' => null,
            'percobaan' => 0,
            'percobaan_siklus' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
