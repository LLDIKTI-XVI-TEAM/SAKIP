<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/** Sesi koreksi perencanaan tidak memberi akses PIC melalui perubahan tanggal jendela saja. */
class RencanaAksiKoreksiKunciTanggalTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    /**
     * Kalender sah dua periode: TW I berakhir 31 Maret, TW II mulai 15 April.
     *
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        $fixture = $this->buatFixtureManual();
        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode2']->id)
            ->update(['pengisian_mulai' => '2026-04-15', 'pengisian_selesai' => '2026-04-30']);

        return $fixture;
    }

    /** @param  array<string, mixed>  $fixture */
    private function simpanTarget(array $fixture, RencanaAksi $header, Periode $periode, int $nilai, ?User $aktor = null): TestResponse
    {
        return $this->actingAs($aktor ?? $fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $header->fresh()->versi,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [$this->manual($periode, $nilai)],
        ]);
    }

    public function test_sesi_koreksi_tanpa_pembukaan_jendela_resmi_tidak_membuka_akses_pic(): void
    {
        $fixture = $this->fixture();
        $header = $this->buatDraf($fixture);
        $this->travelTo(now()->setDate(2027, 1, 5)->setTime(9, 0));
        $fixture['jadwal']->update(['koreksi_mulai' => '2027-01-01 00:00:00', 'koreksi_sampai' => '2027-01-31 23:59:59', 'lingkup_koreksi' => [
            'jenis_objek' => ['rencana_aksi'], 'indikator_ids' => [$fixture['indikator']->id], 'periode_ids' => [$fixture['periode1']->id],
        ]]);

        $this->simpanTarget($fixture, $header, $fixture['periode1'], 10)->assertSessionHasErrors('jendela');
        // Kontrol positif: koreksi di luar jendela PIC tetap dapat dilakukan Perencanaan.
        $this->simpanTarget($fixture, $header, $fixture['periode1'], 10, $fixture['perencanaan'])->assertSessionHasNoErrors();

        // Perubahan tanggal saja bukan aksi resmi pembukaan akses PIC.
        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode1']->id)
            ->update(['pengisian_selesai' => '2027-01-10']);
        $this->simpanTarget($fixture, $header, $fixture['periode1'], 12)->assertSessionHasErrors('jendela');
    }

    private function penggunaDenganPeran(string $kode): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $user->roles()->attach(Role::where('kode', $kode)->firstOrFail()->id, [
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
}
