<?php

namespace Tests\Feature\Periode;

use App\Actions\Periode\ReplaceFinalPeriode;
use App\Actions\Periode\SavePeriode;
use App\Models\AuditLog;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\JadwalFixtures;
use Tests\TestCase;

class PeriodeManagementTest extends TestCase
{
    use JadwalFixtures, RefreshDatabase;

    public function test_first_final_and_atomic_replacement(): void
    {
        $actor = $this->calendarActor();
        $this->actingAs($actor)->post('/periode', ['nama' => 'Awal', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false])->assertSessionHasErrors('is_nilai_akhir');
        $first = app(SavePeriode::class)->handle($actor, ['nama' => ' Final ', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true]);
        $next = app(SavePeriode::class)->handle($actor, ['nama' => 'Pengganti', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => false]);
        $this->actingAs($actor)->put('/periode/'.$first->id, ['nama' => 'Final', 'urutan' => 4, 'aktif' => false, 'is_nilai_akhir' => true, 'revisi' => 1])->assertSessionHasErrors('is_nilai_akhir');
        $data = ['periode_lama_id' => $first->id, 'revisi_lama' => 1, 'periode_pengganti_id' => $next->id, 'revisi_pengganti' => 1];
        app(ReplaceFinalPeriode::class)->handle($actor, $data);
        $this->assertFalse($first->fresh()->aktif);
        $this->assertTrue($first->fresh()->is_nilai_akhir);
        $this->assertTrue($next->fresh()->is_nilai_akhir);
        $this->assertSame(2, $first->fresh()->revisi);
        $this->assertSame(1, Periode::where('aktif', true)->where('is_nilai_akhir', true)->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'periode.ganti_nilai_akhir')->count());
        $this->expectException(ValidationException::class);
        app(ReplaceFinalPeriode::class)->handle($actor, $data);
    }

    public function test_locked_metadata_allows_name_correction_but_rejects_swap_candidate(): void
    {
        $actor = $this->calendarActor();
        $final = $this->calendarMaster();
        $master = $this->calendarMaster(1, false);
        $renstra = $this->calendarRenstra($actor);
        $jadwal = JadwalTahunan::create(['renstra_id' => $renstra->id, 'tahun' => 2026, 'status' => 'ditutup', 'penutupan' => '2027-01-19']);
        PeriodeJadwal::create(['jadwal_id' => $jadwal->id, ...$this->calendarPayload($renstra, $master)['periode'][0]]);
        $data = ['nama' => 'Nama koreksi', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false, 'revisi' => 1];
        app(SavePeriode::class)->handle($actor, $data, $master);
        $this->assertSame('Nama koreksi', $master->fresh()->nama);
        $this->actingAs($actor)->put('/periode/'.$master->id, [...$data, 'urutan' => 2, 'revisi' => 2])->assertSessionHasErrors('urutan');
        $this->actingAs($actor)->post('/periode/ganti-nilai-akhir', ['periode_lama_id' => $final->id, 'revisi_lama' => 1, 'periode_pengganti_id' => $master->id, 'revisi_pengganti' => 2])->assertSessionHasErrors('periode_pengganti_id');
        $this->assertSame(1, $master->fresh()->urutan);
    }

    public function test_master_order_change_revalidates_referencing_drafts(): void
    {
        $actor = $this->calendarActor();
        $final = $this->calendarMaster();
        $early = $this->calendarMaster(1, false);
        $renstra = $this->calendarRenstra($actor);
        $payload = $this->calendarPayload($renstra, $final);
        $jadwal = JadwalTahunan::create(array_diff_key($payload, ['periode' => true]));
        PeriodeJadwal::create(['jadwal_id' => $jadwal->id, ...$payload['periode'][0]]);
        PeriodeJadwal::create(['jadwal_id' => $jadwal->id, 'periode_id' => $early->id, 'pengisian_mulai' => '2026-04-01', 'pengisian_selesai' => '2026-04-07', 'reviu_mulai' => '2026-04-08', 'reviu_selesai' => '2026-04-15']);
        foreach ([1, 0] as $order) {
            $this->actingAs($actor)->put('/periode/'.$final->id, ['nama' => $final->nama, 'urutan' => $order, 'aktif' => true, 'is_nilai_akhir' => true, 'revisi' => 1])->assertSessionHasErrors('urutan');
        }
        $this->assertSame(4, $final->fresh()->urutan);
        $this->assertSame(2, PeriodeJadwal::where('jadwal_id', $jadwal->id)->count());
    }

    public function test_audit_failure_rolls_back_both_final_rows(): void
    {
        $actor = $this->calendarActor();
        $first = $this->calendarMaster();
        $next = $this->calendarMaster(5, false);
        $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        try {
            app(ReplaceFinalPeriode::class)->handle($actor, ['periode_lama_id' => $first->id, 'revisi_lama' => 1, 'periode_pengganti_id' => $next->id, 'revisi_pengganti' => 1]);
            $this->fail('Audit failure harus menggagalkan transaksi.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertTrue($first->fresh()->aktif);
        $this->assertFalse($next->fresh()->is_nilai_akhir);
    }

    public function test_master_reorder_only_revalidates_affected_metadata_for_existing_nullable_ra(): void
    {
        $actor = $this->calendarActor();
        $master = $this->calendarMaster();
        $renstra = $this->calendarRenstra($actor);
        $data = $this->calendarPayload($renstra, $master);
        $jadwal = JadwalTahunan::create(['renstra_id' => $renstra->id, 'tahun' => 2026, 'penutupan' => '2027-01-19']);
        PeriodeJadwal::create(['jadwal_id' => $jadwal->id, ...$data['periode'][0]]);
        $this->actingAs($actor)->put('/periode/'.$master->id, ['nama' => $master->nama, 'urutan' => 5, 'aktif' => true, 'is_nilai_akhir' => true, 'revisi' => 1])->assertSessionDoesntHaveErrors();
        $this->assertSame(5, $master->fresh()->urutan);
        $this->assertNull($jadwal->fresh()->rencana_aksi_mulai);
    }

    public function test_master_denied_intent_rejects_before_target_lookup_and_validation(): void
    {
        $actor = $this->calendarActor(['periode:create']);
        $master = $this->calendarMaster();
        foreach ([$master->id, (string) Str::uuid()] as $id) {
            $this->actingAs($actor)->put('/periode/'.$id, ['nama' => []])->assertForbidden();
        }
        $this->assertSame(2, AuditLog::where('tindakan', 'periode.ubah_ditolak')->count());
        $this->assertNull(AuditLog::where('tindakan', 'periode.ubah_ditolak')->firstOrFail()->nilai_lama);
    }

    public function test_master_form_validation_has_readable_indonesian_messages(): void
    {
        $actor = $this->calendarActor();
        $this->actingAs($actor)->post('/periode', ['nama' => '', 'urutan' => 2147483648, 'aktif' => true, 'is_nilai_akhir' => true])
            ->assertSessionHasErrors(['nama' => 'Nama periode wajib diisi.', 'urutan' => 'Urutan harus berada antara -2147483648 dan 2147483647.']);
        $this->post('/periode', ['nama' => 'Triwulan', 'urutan' => 1.5, 'aktif' => true, 'is_nilai_akhir' => true])
            ->assertSessionHasErrors(['urutan' => 'Urutan harus berupa bilangan bulat.']);
        $this->assertSame(0, Periode::count());
    }
}
