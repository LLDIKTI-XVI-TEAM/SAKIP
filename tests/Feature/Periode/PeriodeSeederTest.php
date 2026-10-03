<?php

namespace Tests\Feature\Periode;

use App\Models\Periode;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PeriodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class PeriodeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_bootstraps_four_quarters_and_repeat_preserves_ids(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame([
            ['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false],
            ['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false],
            ['nama' => 'Triwulan III', 'urutan' => 3, 'aktif' => true, 'is_nilai_akhir' => false],
            ['nama' => 'Triwulan IV', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true],
        ], Periode::orderBy('urutan')->get(['nama', 'urutan', 'aktif', 'is_nilai_akhir'])->toArray());
        $before = Periode::orderBy('id')->get()->toArray();

        $this->seed(PeriodeSeeder::class);

        $this->assertSame($before, Periode::orderBy('id')->get()->toArray());
        $this->assertSame([1], Periode::distinct()->pluck('revisi')->all());
    }

    public function test_existing_custom_configuration_is_preserved_and_reported(): void
    {
        Periode::create(['nama' => 'Semester akhir', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => true]);
        Periode::create(['nama' => 'Periode lama', 'urutan' => 9, 'aktif' => false, 'is_nilai_akhir' => false]);
        $before = Periode::orderBy('id')->get()->toArray();

        $this->artisan('db:seed', ['--class' => PeriodeSeeder::class, '--force' => true])
            ->expectsOutputToContain('Seed periode dilewati: konfigurasi yang sudah ada dipertahankan. Kelola perubahan melalui halaman Master Periode.')
            ->assertSuccessful();

        $this->assertSame($before, Periode::orderBy('id')->get()->toArray());
    }

    public function test_failure_during_bootstrap_rolls_back_all_periods(): void
    {
        Event::listen('eloquent.creating: '.Periode::class, function (Periode $periode): void {
            if ($periode->urutan === 3) {
                throw new RuntimeException('Gagal menyimpan periode ketiga.');
            }
        });

        try {
            app(PeriodeSeeder::class)->run();
            $this->fail('Seed yang gagal harus membatalkan seluruh bootstrap.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Gagal menyimpan periode ketiga.', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.Periode::class);
        }

        $this->assertDatabaseCount('periode', 0);
    }
}
