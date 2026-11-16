<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Role;
use App\Models\User;
use App\Services\RencanaAksi\PeriodeTanpaTarget;
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

    public function test_perpanjangan_resmi_pengisian_selesai_menggeser_kunci_periode_bagi_pic(): void
    {
        $fixture = $this->fixture();
        $header = $this->buatDraf($fixture);
        $this->travelTo(now()->setDate(2026, 4, 5)->setTime(9, 0));

        $this->simpanTarget($fixture, $header, $fixture['periode1'], 10)->assertSessionHasErrors('jendela');
        // Kontrol positif: periode yang belum terkunci tetap dapat ditulis PIC.
        $this->simpanTarget($fixture, $header, $fixture['periode2'], 20)->assertSessionHasNoErrors();

        $this->revisiTwI($fixture, '2026-04-10')
            ->assertSessionHasNoErrors()->assertRedirect("/jadwal/{$fixture['jadwal']->id}");

        $this->simpanTarget($fixture, $header, $fixture['periode1'], 10)->assertSessionHasNoErrors();
        $this->travelTo(now()->setDate(2026, 4, 11));
        $this->simpanTarget($fixture, $header, $fixture['periode1'], 11)->assertSessionHasErrors('jendela');
    }

    /**
     * Status lampau 2026 dinilai dari `pengisian_selesai` yang berlaku: perpanjangan resmi
     * melewati tanggal aktivasi menjadikan TW I periode normal (target wajib, PIC dapat menulis).
     */
    public function test_perpanjangan_periode_lampau_2026_melewati_tanggal_aktivasi_menjadikannya_periode_normal(): void
    {
        $fixture = $this->fixture();
        $fixture['jadwal']->forceFill(['activated_at' => '2026-04-05 01:00:00'])->save(); // 09:00 WITA
        $header = $this->buatDraf($fixture);
        $this->travelTo(now()->setDate(2026, 4, 8)->setTime(9, 0));
        $tw1 = fn () => PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode1']->id)->sole();
        // Kontrol pra-revisi: TW I (batas 31 Maret) lampau dan terkunci bagi PIC.
        $this->assertTrue(PeriodeTanpaTarget::isPeriodeLampau($fixture['jadwal']->fresh(), $tw1()));
        $this->simpanTarget($fixture, $header, $fixture['periode1'], 10)->assertSessionHasErrors('jendela');

        $this->revisiTwI($fixture, '2026-04-10')->assertSessionHasNoErrors();

        $this->assertFalse(PeriodeTanpaTarget::isPeriodeLampau($fixture['jadwal']->fresh(), $tw1()));
        $snapshot = $fixture['snapshot']->fresh();
        $this->assertFalse(PeriodeTanpaTarget::bolehTanpaTarget($snapshot, $tw1(), $snapshot->jadwal->periode()->with('periode')->get()));
        $this->simpanTarget($fixture, $header, $fixture['periode1'], 10)->assertSessionHasNoErrors();
    }

    public function test_perpanjangan_periode_lampau_2026_sebelum_tanggal_aktivasi_tetap_lampau(): void
    {
        $fixture = $this->fixture();
        $fixture['jadwal']->forceFill(['activated_at' => '2026-04-05 01:00:00'])->save();
        $header = $this->buatDraf($fixture);
        $this->travelTo(now()->setDate(2026, 4, 8)->setTime(9, 0));

        $this->revisiTwI($fixture, '2026-04-03')->assertSessionHasNoErrors();

        $tw1 = PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode1']->id)->sole();
        // Revisi tanpa perubahan juga lolos tanpa error; pastikan batas baru benar-benar tersimpan.
        $this->assertSame('2026-04-03', $tw1->pengisian_selesai->toDateString());
        $this->assertTrue(PeriodeTanpaTarget::isPeriodeLampau($fixture['jadwal']->fresh(), $tw1));
        $this->simpanTarget($fixture, $header, $fixture['periode1'], 10)->assertSessionHasErrors('jendela');
    }

    /** Status normal tidak membuka kunci PIC: batas baru 6 April sudah lewat pada 8 April. */
    public function test_perpanjangan_melewati_tanggal_aktivasi_tetapi_sudah_lewat_tetap_terkunci_bagi_pic(): void
    {
        $fixture = $this->fixture();
        $fixture['jadwal']->forceFill(['activated_at' => '2026-04-05 01:00:00'])->save();
        $header = $this->buatDraf($fixture);
        $this->travelTo(now()->setDate(2026, 4, 8)->setTime(9, 0));

        $this->revisiTwI($fixture, '2026-04-06')->assertSessionHasNoErrors();

        $tw1 = PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode1']->id)->sole();
        $this->assertSame('2026-04-06', $tw1->pengisian_selesai->toDateString());
        $this->assertFalse(PeriodeTanpaTarget::isPeriodeLampau($fixture['jadwal']->fresh(), $tw1));
        $snapshot = $fixture['snapshot']->fresh();
        $this->assertFalse(PeriodeTanpaTarget::bolehTanpaTarget($snapshot, $tw1, $snapshot->jadwal->periode()->with('periode')->get()));
        $this->simpanTarget($fixture, $header, $fixture['periode1'], 10)->assertSessionHasErrors('jendela');
    }

    /**
     * Revisi resmi jendela oleh Perencanaan: hanya TW I yang digeser, TW II tetap
     * 15–30 April (reviu 1–15 Mei).
     *
     * @param  array<string, mixed>  $fixture
     */
    private function revisiTwI(array $fixture, string $pengisianSelesai): TestResponse
    {
        return $this->actingAs($fixture['perencanaan'])->post("/jadwal/{$fixture['jadwal']->id}/revisi-jendela", [
            'expected_revisi' => $fixture['jadwal']->fresh()->revisi,
            'alasan' => 'Perpanjangan resmi TW I.',
            'periode' => [
                ['periode_id' => $fixture['periode1']->id, 'pengisian_mulai' => '2026-03-01', 'pengisian_selesai' => $pengisianSelesai],
                ['periode_id' => $fixture['periode2']->id, 'pengisian_mulai' => '2026-04-15', 'pengisian_selesai' => '2026-04-30'],
            ],
        ]);
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
