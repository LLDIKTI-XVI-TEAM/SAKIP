<?php

namespace Tests\Feature\Jadwal;

use App\Actions\Jadwal\ActivateJadwal;
use App\Models\AuditLog;
use App\Models\JadwalTahunan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

class JadwalPenutupanTest extends TestCase
{
    use JadwalActivationFixtures, RefreshDatabase;

    private const ALASAN = 'Seluruh pengukuran tahun ini sudah disahkan.';

    private JadwalTahunan $jadwal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->readyJadwal();
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $this->jadwal = $this->ready['jadwal']->fresh();
    }

    /** @param  array<string, mixed>  $overrides */
    private function tutup(User $actor, array $overrides = [], ?string $jadwalId = null): TestResponse
    {
        return $this->actingAs($actor)->post('/jadwal/'.($jadwalId ?? $this->jadwal->id).'/tutup',
            [...['expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => self::ALASAN], ...$overrides]);
    }

    private function penolakan(string $alasan): int
    {
        return AuditLog::where('tindakan', 'jadwal.tutup_ditolak')->where('nilai_baru->alasan_penolakan', $alasan)->count();
    }

    public function test_penutupan_mengisi_status_dan_closed_at_tanpa_mengubah_penutupan_dan_snapshot(): void
    {
        $closer = $this->activator(['jadwal:tutup']);
        $penutupan = $this->jadwal->penutupan->toDateString();
        $snapshot = $this->snapshotFingerprint();

        $this->tutup($closer)->assertSessionHasNoErrors()->assertRedirect("/jadwal/{$this->jadwal->id}");

        $fresh = $this->jadwal->fresh();
        $this->assertSame('ditutup', $fresh->status);
        $this->assertNotNull($fresh->closed_at);
        $this->assertSame($penutupan, $fresh->penutupan->toDateString());
        $this->assertSame($this->jadwal->revisi + 1, $fresh->revisi);
        $this->assertSame($snapshot, $this->snapshotFingerprint());

        $audit = AuditLog::where('tindakan', 'jadwal.tutup')->sole();
        $this->assertSame($closer->id, $audit->actor_id);
        $this->assertSame('jadwal:tutup', $audit->dasar_izin['permission']);
        $this->assertSame(self::ALASAN, $audit->alasan);
        $this->assertSame('aktif', $audit->nilai_lama['status']);
        $this->assertSame('ditutup', $audit->nilai_baru['status']);
        $this->assertSame($penutupan, $audit->nilai_baru['penutupan']);
    }

    public function test_jadwal_draft_dan_jadwal_ditutup_ditolak_dengan_audit(): void
    {
        $closer = $this->activator(['jadwal:tutup']);
        $draft = JadwalTahunan::create(['renstra_id' => $this->ready['renstra']->id, 'tahun' => 2027, 'penutupan' => '2028-01-31', 'status' => 'draft']);

        $this->tutup($closer, ['expected_revisi' => 1], $draft->id)->assertSessionHasErrors(['jadwal' => 'Hanya jadwal berstatus aktif yang dapat ditutup.']);
        $this->tutup($closer)->assertSessionHasNoErrors();
        $this->tutup($closer)->assertSessionHasErrors(['jadwal' => 'Hanya jadwal berstatus aktif yang dapat ditutup.']);

        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.tutup')->count());
        $this->assertSame(2, $this->penolakan('aturan_penutupan'));
    }

    public function test_tanpa_izin_dan_deny_ditolak_403_dengan_audit(): void
    {
        $this->tutup($this->activator(['jadwal:aktivasi']))->assertForbidden();
        $denied = $this->activator(['jadwal:tutup']);
        $this->denyPermission($denied, 'jadwal:tutup');
        $this->tutup($denied)->assertForbidden();

        $this->assertSame('aktif', $this->jadwal->fresh()->status);
        $this->assertSame(2, $this->penolakan('izin_ditolak'));
        // Kontrol positif: pemegang izin yang sah tetap dapat menutup.
        $this->tutup($this->activator(['jadwal:tutup']))->assertSessionHasNoErrors();
    }

    public function test_revisi_basi_alasan_kosong_dan_field_asing_ditolak(): void
    {
        $closer = $this->activator(['jadwal:tutup']);

        $this->tutup($closer, ['expected_revisi' => $this->jadwal->revisi - 1])->assertSessionHasErrors('jadwal');
        $this->tutup($closer, ['alasan' => '   '])->assertSessionHasErrors('alasan');
        $this->tutup($closer, ['status' => 'aktif'])->assertSessionHasErrors('jadwal');

        $this->assertSame('aktif', $this->jadwal->fresh()->status);
        $this->assertSame(0, AuditLog::where('tindakan', 'jadwal.tutup')->count());
    }

    public function test_editor_menawarkan_tutup_hanya_untuk_pemegang_izin_pada_jadwal_aktif(): void
    {
        $closer = $this->activator(['jadwal:tutup']);
        $url = "/jadwal/{$this->jadwal->id}";

        $this->actingAs($closer)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.close', true));
        $this->actingAs($this->activator(['jadwal:aktivasi']))->get($url)->assertInertia(fn (Assert $page) => $page->where('can.close', false));
        $this->tutup($closer);
        $this->actingAs($closer)->get($url)->assertInertia(fn (Assert $page) => $page->where('can.close', false)->where('jadwal.status', 'ditutup'));
    }
}
