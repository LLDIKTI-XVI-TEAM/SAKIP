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

/** Kunci periode Rencana Aksi bagi PIC mengikuti `pengisian_selesai` hasil revisi resmi (Q41 §41.2 butir 5). */
class RencanaAksiRevisiJendelaTest extends TestCase
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
            ->update(['pengisian_mulai' => '2026-04-15', 'pengisian_selesai' => '2026-04-30', 'reviu_mulai' => '2026-05-01', 'reviu_selesai' => '2026-05-15']);

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

    public function test_perpanjangan_resmi_pengisian_selesai_menggeser_kunci_periode_bagi_pic(): void
    {
        $fixture = $this->fixture();
        $header = $this->buatDraf($fixture);
        $this->travelTo(now()->setDate(2026, 4, 5)->setTime(9, 0));

        $this->simpanTarget($fixture, $header, $fixture['periode1'], 10)->assertSessionHasErrors('jendela');
        // Kontrol positif: periode yang belum terkunci tetap dapat ditulis PIC.
        $this->simpanTarget($fixture, $header, $fixture['periode2'], 20)->assertSessionHasNoErrors();

        $this->actingAs($fixture['perencanaan'])->post("/jadwal/{$fixture['jadwal']->id}/revisi-jendela", [
            'expected_revisi' => $fixture['jadwal']->fresh()->revisi,
            'alasan' => 'Perpanjangan resmi TW I.',
            'periode' => [
                ['periode_id' => $fixture['periode1']->id, 'pengisian_mulai' => '2026-03-01', 'pengisian_selesai' => '2026-04-10', 'reviu_mulai' => '2026-04-11', 'reviu_selesai' => '2026-04-30'],
                ['periode_id' => $fixture['periode2']->id, 'pengisian_mulai' => '2026-04-15', 'pengisian_selesai' => '2026-04-30', 'reviu_mulai' => '2026-05-01', 'reviu_selesai' => '2026-05-15'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect("/jadwal/{$fixture['jadwal']->id}");

        $this->simpanTarget($fixture, $header, $fixture['periode1'], 10)->assertSessionHasNoErrors();
        $this->travelTo(now()->setDate(2026, 4, 11));
        $this->simpanTarget($fixture, $header, $fixture['periode1'], 11)->assertSessionHasErrors('jendela');
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
