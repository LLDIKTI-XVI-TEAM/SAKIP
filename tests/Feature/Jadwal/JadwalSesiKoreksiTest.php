<?php

namespace Tests\Feature\Jadwal;

use App\Actions\Jadwal\ActivateJadwal;
use App\Actions\Jadwal\BukaKembaliJadwal;
use App\Models\AuditLog;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

class JadwalSesiKoreksiTest extends TestCase
{
    use JadwalActivationFixtures, RefreshDatabase;

    private JadwalTahunan $jadwal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(9, 0));
        $this->readyJadwal();
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $this->jadwal = $this->ready['jadwal']->fresh();
        $this->actingAs($this->activator(['jadwal:tutup']))->post("/jadwal/{$this->jadwal->id}/tutup", [
            'expected_revisi' => $this->jadwal->revisi, 'alasan' => 'Penutupan untuk koreksi fixture',
        ])->assertSessionHasNoErrors();
        $this->jadwal->refresh();
    }

    private function payload(array $overrides = []): array
    {
        return [...[
            'expected_revisi' => $this->jadwal->revisi, 'alasan' => 'Koreksi salah input resmi',
            'koreksi_mulai' => '2026-11-01T08:00', 'koreksi_sampai' => '2026-11-02T17:00',
            'lingkup_koreksi' => ['jenis_objek' => ['rencana_aksi', 'pengukuran'],
                'indikator_ids' => [JadwalSnapshot::where('jadwal_id', $this->jadwal->id)->value('indikator_id')],
                'periode_ids' => [$this->ready['periode']->id]],
        ], ...$overrides];
    }

    private function buka(User $actor, array $overrides = []): TestResponse
    {
        return $this->actingAs($actor)->post("/jadwal/{$this->jadwal->id}/buka-kembali", $this->payload($overrides));
    }

    public function test_buka_kembali_menyimpan_sesi_dan_audit_tanpa_mengubah_konteks_beku(): void
    {
        $actor = $this->activator(['jadwal:buka_kembali']);
        $before = $this->jadwal->only(['penutupan', 'activated_at', 'closed_at', 'revisi']);
        $snapshots = $this->snapshotFingerprint();
        $this->eligibleIndicator(nama: 'Indikator baru tidak otomatis diterbitkan');

        $this->buka($actor)->assertSessionHasNoErrors()->assertRedirect("/jadwal/{$this->jadwal->id}");

        $fresh = $this->jadwal->fresh();
        $this->assertSame('aktif', $fresh->status);
        $this->assertNull($fresh->closed_at);
        $this->assertSame($before['activated_at'], $fresh->activated_at);
        $this->assertEquals($before['penutupan'], $fresh->penutupan);
        $this->assertSame($before['revisi'] + 1, $fresh->revisi);
        $this->assertSame('2026-11-01 00:00:00', $fresh->koreksi_mulai->format('Y-m-d H:i:s'));
        $this->assertEquals($this->payload()['lingkup_koreksi'], $fresh->lingkup_koreksi);
        $this->assertSame($snapshots, $this->snapshotFingerprint());
        $this->assertTrue($fresh->tahunDitutup(), 'Pembukaan lebih awal tidak membuka mutasi normal.');
        $audit = AuditLog::where('tindakan', 'jadwal.buka_kembali')->sole();
        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertSame('jadwal:buka_kembali', $audit->dasar_izin['permission']);
        $this->assertSame('Koreksi salah input resmi', $audit->alasan);
        $this->assertSame($before['closed_at'], $audit->nilai_lama['closed_at']);
        $this->assertSame('ditutup', $audit->nilai_lama['status']);
        $this->assertSame('aktif', $audit->nilai_baru['status']);
        $this->actingAs($actor)->get("/jadwal/{$fresh->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.reopen', false)->where('koreksi.status', 'berjalan'));
    }

    public static function inputTidakValid(): array
    {
        return [
            'alasan kosong' => [['alasan' => '   '], 'alasan'],
            'waktu sama' => [['koreksi_sampai' => '2026-11-01T08:00'], 'koreksi_sampai'],
            'waktu terbalik' => [['koreksi_sampai' => '2026-10-31T08:00'], 'koreksi_sampai'],
            'jenis kosong' => [['lingkup_koreksi' => ['jenis_objek' => [], 'indikator_ids' => [], 'periode_ids' => []]], 'lingkup_koreksi.jenis_objek'],
            'field asing' => [['status' => 'aktif'], 'jadwal'],
            'revisi basi' => [['expected_revisi' => 1], 'jadwal'],
        ];
    }

    #[DataProvider('inputTidakValid')]
    public function test_input_tidak_valid_ditolak_tanpa_mutasi_dan_diaudit(array $overrides, string $field): void
    {
        $before = $this->jadwal->getRawOriginal();
        $this->buka($this->activator(['jadwal:buka_kembali']), $overrides)->assertSessionHasErrors($field);
        $this->assertSame($before, $this->jadwal->fresh()->getRawOriginal());
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.buka_kembali_ditolak')->count());
    }

    public function test_scope_asing_ditolak_di_server_dan_jadwal_aktif_tidak_dapat_dibuka_ulang(): void
    {
        $actor = $this->activator(['jadwal:buka_kembali']);
        foreach (['periode_ids', 'indikator_ids'] as $key) {
            $scope = $this->payload()['lingkup_koreksi'];
            $scope[$key] = [(string) Str::uuid()];
            $this->buka($actor, ['lingkup_koreksi' => $scope])->assertSessionHasErrors("lingkup_koreksi.$key");
            $this->assertSame('ditutup', $this->jadwal->fresh()->status);
        }
        $this->buka($actor)->assertSessionHasNoErrors();
        $this->buka($actor)->assertSessionHasErrors('jadwal');
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.buka_kembali')->count());
    }

    public function test_tanpa_izin_deny_dan_akun_nonaktif_ditolak_tanpa_mutasi(): void
    {
        $this->buka($this->activator(['jadwal:tutup']))->assertForbidden();
        $denied = $this->activator(['jadwal:buka_kembali']);
        $this->denyPermission($denied, 'jadwal:buka_kembali');
        $this->buka($denied)->assertForbidden();
        $inactive = $this->activator(['jadwal:buka_kembali']);
        $inactive->update(['status' => 'nonaktif']);
        $this->withHeader('X-Inertia', 'true');
        $this->buka($inactive)->assertForbidden();
        $this->assertSame('ditutup', $this->jadwal->fresh()->status);
        $this->assertSame(3, AuditLog::where('tindakan', 'jadwal.buka_kembali_ditolak')->count());
    }

    public static function visibilitasIndikatorSesi(): array
    {
        return [
            'jadwal ditutup dengan deny' => ['ditutup', true],
            'sesi aktif dengan deny' => ['aktif', true],
            'jadwal ditutup ulang dengan deny' => ['ditutup ulang', true],
            'jadwal ditutup dengan izin baca' => ['ditutup', false],
            'sesi aktif dengan izin baca' => ['aktif', false],
            'jadwal ditutup ulang dengan izin baca' => ['ditutup ulang', false],
        ];
    }

    #[DataProvider('visibilitasIndikatorSesi')]
    public function test_nama_indikator_sesi_memerlukan_izin_baca(string $status, bool $deny): void
    {
        $actor = $this->activator(['jadwal:buka_kembali', 'indikator:read']);
        if ($deny) {
            $this->denyPermission($actor, 'indikator:read');
        }
        if ($status !== 'ditutup') {
            $this->buka($actor)->assertSessionHasNoErrors();
        }
        if ($status === 'ditutup ulang') {
            $this->actingAs($this->activator(['jadwal:tutup']))->post("/jadwal/{$this->jadwal->id}/tutup", [
                'expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => 'Tutup ulang fixture',
            ])->assertSessionHasNoErrors();
        }
        $snapshot = JadwalSnapshot::where('jadwal_id', $this->jadwal->id)->sole();
        $expected = $deny ? [] : [['id' => $snapshot->indikator_id, 'nama' => $snapshot->nama]];

        $response = $this->actingAs($actor)->get("/jadwal/{$this->jadwal->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('koreksi_indikator_options', $expected));
        if ($deny) {
            $response->assertDontSee($snapshot->nama);
        }
    }

    public function test_tanpa_izin_baca_indikator_nama_tidak_dikirim(): void
    {
        $actor = $this->activator(['jadwal:buka_kembali']);
        $snapshot = JadwalSnapshot::where('jadwal_id', $this->jadwal->id)->sole();
        $this->actingAs($actor)->get("/jadwal/{$this->jadwal->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('koreksi_indikator_options', []))
            ->assertDontSee($snapshot->nama);
    }

    public function test_sesi_terjadwal_dan_kedaluwarsa_tetap_mengunci_mutasi_normal(): void
    {
        $actor = $this->activator(['jadwal:buka_kembali']);
        $this->buka($actor, ['koreksi_mulai' => '2026-11-02T08:00'])->assertSessionHasNoErrors();
        $this->actingAs($actor)->get("/jadwal/{$this->jadwal->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('koreksi.status', 'terjadwal'));
        $this->travelTo(now()->addDays(3));
        $this->assertTrue($this->jadwal->fresh()->tahunDitutup());
        $this->actingAs($actor)->get("/jadwal/{$this->jadwal->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('koreksi.status', 'berakhir'));
    }

    public function test_constraint_waktu_menolak_batas_parsial_sama_dan_terbalik_di_postgresql(): void
    {
        foreach ([[null, '2026-11-02 00:00:00'], ['2026-11-02 00:00:00', null],
            ['2026-11-02 00:00:00', '2026-11-02 00:00:00'], ['2026-11-03 00:00:00', '2026-11-02 00:00:00']] as [$mulai, $sampai]) {
            try {
                DB::transaction(fn () => DB::table('jadwal_tahunan')->where('id', $this->jadwal->id)->update(['koreksi_mulai' => $mulai, 'koreksi_sampai' => $sampai]));
                $this->fail('Constraint harus menolak waktu sesi tidak valid.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->getCode());
            }
        }
        $this->assertNull($this->jadwal->fresh()->koreksi_mulai);
    }

    public function test_kegagalan_audit_membatalkan_pembukaan_sesi_seluruhnya(): void
    {
        $before = $this->jadwal->getRawOriginal();
        $actor = $this->activator(['jadwal:buka_kembali']);
        $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andThrow(new RuntimeException('Audit gagal untuk pengujian'));
        try {
            app(BukaKembaliJadwal::class)->handle($actor, $this->jadwal->id, $this->payload());
            $this->fail('Kegagalan audit harus diteruskan.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit gagal untuk pengujian', $exception->getMessage());
        }
        $this->assertSame($before, $this->jadwal->fresh()->getRawOriginal());
        $this->assertSame(0, AuditLog::where('tindakan', 'jadwal.buka_kembali')->count());
    }
}
