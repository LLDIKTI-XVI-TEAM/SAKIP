<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\JadwalSnapshot;
use App\Models\RencanaAksi;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Database\Seeders\IndikatorKomponenFixtureSeeder;
use Database\Seeders\PeriodeSeeder;
use Database\Seeders\RencanaAksiFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RencanaAksiFixtureSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Header fixture harus berdiri di atas jadwal aktif dengan snapshot beku,
     * karena header tanpa snapshot ditolak fail-closed. Snapshot pada jadwal
     * aktif selalu berkomposisi final, sama seperti hasil aktivasi. Urutan
     * seed mengikuti pemakaian QA, dan seeder RA dijalankan dua kali.
     */
    public function test_header_fixture_dapat_dibuka_dan_disunting_pic(): void
    {
        $this->seed([AccessCatalogSeeder::class, PeriodeSeeder::class, RencanaAksiFixtureSeeder::class, RencanaAksiFixtureSeeder::class, IndikatorKomponenFixtureSeeder::class]);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $header = RencanaAksi::sole();
        $snapshot = JadwalSnapshot::where('indikator_id', $header->indikator_id)->sole();
        $pic = User::where('email', 'perencanaan@sakip.local')->sole();

        $this->assertSame((string) $snapshot->id, (string) $header->snapshot_draf_id);
        $this->assertTrue($snapshot->komposisi_final);
        $this->actingAs($pic)->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.expected_snapshot_id', (string) $snapshot->id)
                ->where('rencanaAksi.can.update', true));
    }
}
