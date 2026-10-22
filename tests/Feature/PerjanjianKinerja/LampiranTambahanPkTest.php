<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Actions\Jadwal\ActivateJadwal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

class LampiranTambahanPkTest extends TestCase
{
    use JadwalActivationFixtures, RefreshDatabase;

    public function test_lampiran_tambahan_setelah_jadwal_aktif_tidak_mengubah_target_maupun_snapshot(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 10:00:00', 'Asia/Makassar'));
        $this->readyJadwal();
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $pk = $this->ready['pk']->fresh();
        $target = $this->barisTarget();
        $snapshot = $this->snapshotFingerprint();
        $this->assertNotEmpty($target);
        $this->assertNotEmpty($snapshot[0]);

        $this->actingAs($this->activator(['pk:update', 'berkas:upload']))
            ->put("/perjanjian-kinerja/{$pk->id}", [
                'nomor_pk' => $pk->nomor_pk,
                'tanggal_pk' => $pk->tanggal_pk->toDateString(),
                'alasan' => 'Unggah PK perubahan anggaran',
                'expected_updated_at' => $pk->updated_at?->toISOString(),
                'lampiran' => [['mode' => 'tautan', 'tautan' => 'https://contoh.go.id/pk-perubahan', 'keterangan' => 'PK perubahan anggaran']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('PK perubahan anggaran', DB::table('berkas')->where('berkasable_id', $pk->id)->whereNotNull('keterangan')->value('keterangan'));
        $this->assertEquals($target, $this->barisTarget());
        $this->assertEquals($snapshot, $this->snapshotFingerprint());
    }

    /** @return list<array<string, mixed>> */
    private function barisTarget(): array
    {
        return DB::table('target_kinerjas')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }
}
