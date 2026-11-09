<?php

namespace Tests\Feature\Pengingat;

use App\Models\PeriodeJadwal;
use App\Services\Notifikasi\AntrekanNotifikasiEksternal;
use Carbon\CarbonImmutable;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Command EWS tipis: guard jam kirim/template, lalu serahkan kanal dan idempotensi
 * ke producer outbox. Producer di-mock karena tujuan jadwal_periode menunggu fondasi
 * bersama; test ini mengunci kontrak pemanggilan command.
 */
class KirimPengingatEwsTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->seed(PengaturanSeeder::class);
    }

    private function aturJamKirim(string $nilai): void
    {
        DB::table('pengaturan')->where('kunci', 'notifikasi.jam_kirim')->update(['nilai' => $nilai]);
    }

    public function test_jam_kirim_kosong_tidak_mengantrekan(): void
    {
        $this->aturJamKirim('');
        $this->mock(AntrekanNotifikasiEksternal::class, fn (MockInterface $mock) => $mock->shouldReceive('antre')->never());

        $this->artisan('notifikasi:ews')
            ->expectsOutputToContain('Jam kirim belum ditetapkan')
            ->assertSuccessful();
    }

    public function test_template_kosong_tidak_mengantrekan(): void
    {
        $this->aturJamKirim('08:00');
        config(['notifikasi.template_ews' => '']);
        $this->mock(AntrekanNotifikasiEksternal::class, fn (MockInterface $mock) => $mock->shouldReceive('antre')->never());

        $this->artisan('notifikasi:ews')
            ->expectsOutputToContain('Template EWS belum ditetapkan')
            ->assertSuccessful();
    }

    public function test_h_minus_mengantrekan_satu_pesan_per_pic(): void
    {
        $this->aturJamKirim('08:00');
        $this->travelTo(CarbonImmutable::parse('2026-03-14 09:00', 'Asia/Makassar'));
        $tw1 = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
        $template = (string) config('notifikasi.template_ews');
        $aktorId = $this->actor->id;

        $this->mock(AntrekanNotifikasiEksternal::class, function (MockInterface $mock) use ($tw1, $template, $aktorId): void {
            $mock->shouldReceive('antre')->once()->withArgs(function (string $kejadian, $tujuan, string $periode, array $penerima, array $kanal, string $isi, array $parameter) use ($tw1, $template, $aktorId): bool {
                return $kejadian === 'ews:h1'
                    && $tujuan instanceof PeriodeJadwal && $tujuan->is($tw1)
                    && $periode === $tw1->id.':2026-03-15'
                    && $penerima === [$aktorId]
                    && $kanal === ['email', 'whatsapp']
                    && $isi === $template
                    && $parameter === ['h' => 1, 'jatuh_tempo' => '2026-03-15', 'jumlah' => 1];
            });
        });

        $this->artisan('notifikasi:ews')
            ->expectsOutputToContain('1 pesan, 1 diantrekan')
            ->assertSuccessful();
    }

    public function test_tanggal_selain_h_minus_tidak_mengantrekan(): void
    {
        $this->aturJamKirim('08:00');
        $this->travelTo(CarbonImmutable::parse('2026-03-13 09:00', 'Asia/Makassar'));
        $this->mock(AntrekanNotifikasiEksternal::class, fn (MockInterface $mock) => $mock->shouldReceive('antre')->never());

        $this->artisan('notifikasi:ews')
            ->expectsOutputToContain('tidak ada kandidat')
            ->assertSuccessful();
    }
}
