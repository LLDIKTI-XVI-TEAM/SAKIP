<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Actions\TargetTahunan\ShowTargetTahunan;
use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\IndikatorKinerja;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\TargetKinerja;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class TargetPkHalamanTest extends TestCase
{
    use RefreshDatabase, TargetTahunanFixtures;

    public function test_perencanaan_menyimpan_target_pk_dari_halaman_pk(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indikator = $this->targetIndicator($actor);
        $pk = $this->targetPk($indikator, $actor);
        $state = $this->actingAs($actor)->getJson($this->editorUrl($pk, $indikator->id))->assertOk()
            ->assertJsonPath('tahun', 2026)->assertJsonPath('baseline_year', 2025)->assertJsonPath('can.update', true)
            ->json('expected_state');

        $this->withHeader('X-Inertia', 'true')
            ->put($this->simpanUrl($pk, $indikator->id), $this->payload($state, ['baseline' => '0', 'target_tahunan' => '94,05']))
            ->assertStatus(303)
            ->assertRedirect(route('perjanjian-kinerja.show', $pk))
            ->assertSessionHas('inertia.flash_data.target_tahunan.changed', true);

        $baris = DB::table('target_kinerjas')->where('indikator_kinerja_id', $indikator->id)->where('tahun', 2026)->get(['baseline', 'target_tahunan']);
        $this->assertCount(1, $baris);
        $this->assertSame('0.000000000000', $baris[0]->baseline);
        $this->assertSame('94.050000000000', $baris[0]->target_tahunan);
    }

    public function test_indikator_renstra_lain_ditolak_404_tanpa_menulis(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $pk = $this->targetPk($this->targetIndicator($actor), $actor);
        $asingSamaRentang = $this->targetIndicator($actor);
        $asingBedaRentang = $this->targetIndicator($actor, Renstra::create(['kode' => 'REN-'.Str::random(8), 'nama' => 'Renstra berikutnya',
            'tahun_mulai' => 2030, 'tahun_selesai' => 2034, 'status' => 'draft', 'dasar_hukum' => 'Kepmen fixture', 'created_by' => $actor->id]));
        $token = app(ShowTargetTahunan::class)->handle($actor, $asingSamaRentang->id, 2026)['expected_state'];

        $this->actingAs($actor)->getJson($this->editorUrl($pk, $asingSamaRentang->id))->assertNotFound();
        $this->putJson($this->simpanUrl($pk, $asingSamaRentang->id), $this->payload($token, ['target_tahunan' => '80']))->assertNotFound();
        $this->putJson($this->simpanUrl($pk, $asingBedaRentang->id), $this->payload(str_repeat('a', 64), ['target_tahunan' => '80']))->assertNotFound();

        $this->assertSame(0, TargetKinerja::count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
    }

    public function test_pegawai_tanpa_target_update_ditolak_403_dan_diaudit_dengan_tahun_pk(): void
    {
        $perencana = $this->calendarActor(['indikator:read', 'target:update']);
        $indikator = $this->targetIndicator($perencana);
        $pk = $this->targetPk($indikator, $perencana);

        $this->actingAs($this->pegawai())
            ->putJson($this->simpanUrl($pk, $indikator->id), $this->payload(str_repeat('a', 64), ['target_tahunan' => '80']))
            ->assertForbidden();

        $this->assertSame(0, TargetKinerja::count());
        $audit = AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->where('objek_id', $indikator->id)->sole();
        $this->assertSame(2026, $audit->nilai_baru['tahun'] ?? null);
        $this->assertSame('izin_ditolak', $audit->nilai_baru['alasan_penolakan'] ?? null);
    }

    public function test_deny_target_update_eksplisit_ditolak_403(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indikator = $this->targetIndicator($actor);
        $pk = $this->targetPk($indikator, $actor);
        $state = $this->actingAs($actor)->getJson($this->editorUrl($pk, $indikator->id))->assertOk()->json('expected_state');
        UserPermissionDeny::create(['user_id' => $actor->id, 'permission_id' => Permission::where('kode', 'target:update')->sole()->id,
            'alasan' => 'Fixture penolakan', 'ditetapkan_oleh' => $actor->id]);

        $this->putJson($this->simpanUrl($pk, $indikator->id), $this->payload($state, ['target_tahunan' => '80']))->assertForbidden();

        $this->assertSame(0, TargetKinerja::count());
    }

    public function test_route_target_pk_menolak_uuid_tidak_valid(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $pk = $this->targetPk($this->targetIndicator($actor), $actor);

        $this->actingAs($actor)->getJson("/perjanjian-kinerja/{$pk->id}/target/bukan-uuid/editor")->assertNotFound();
        $this->putJson("/perjanjian-kinerja/{$pk->id}/target/bukan-uuid", [])->assertNotFound();
    }

    public function test_editor_dan_simpan_target_lama_tidak_lagi_tersedia(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indikator = $this->targetIndicator($actor);
        $pk = $this->targetPk($indikator, $actor);
        $state = $this->actingAs($actor)->getJson($this->editorUrl($pk, $indikator->id))->assertOk()->json('expected_state');

        $this->getJson("/perencanaan/indikator/{$indikator->id}/target-tahunan/2026/editor")->assertNotFound();
        $this->putJson("/perencanaan/indikator/{$indikator->id}/target-tahunan/2026", $this->payload($state, ['target_tahunan' => '80']))->assertNotFound();
        $this->assertSame(0, TargetKinerja::count());

        $this->putJson($this->simpanUrl($pk, $indikator->id), $this->payload($state, ['target_tahunan' => '80']))->assertOk();
        $this->assertSame(1, TargetKinerja::count());
    }

    public function test_halaman_pk_menampilkan_indikator_aktif_per_sasaran(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        ['pk' => $pk, 's1' => $s1, 's2' => $s2, 'iku1' => $iku1, 'iku2' => $iku2] = $this->halamanPk($actor);

        $this->actingAs($actor)->get("/perjanjian-kinerja/{$pk->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('PerjanjianKinerja/Show')
                ->where('pk.nomor_pk', null)->where('pk.tanggal_pk', '2026-04-21')->has('pk.berkas', 1)
                ->has('target_pk', 2)
                ->where('target_pk.0.id', $s1->id)->has('target_pk.0.indikator', 1)->where('target_pk.0.indikator.0.id', $iku1->id)
                ->where('target_pk.1.id', $s2->id)->has('target_pk.1.indikator', 1)->where('target_pk.1.indikator.0.id', $iku2->id));
    }

    public function test_baseline_dikirim_terpisah_dari_target_pk(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $pk = $this->halamanPk($actor)['pk'];

        $this->actingAs($actor)->get("/perjanjian-kinerja/{$pk->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('target_pk.0.indikator.0.baseline', '74.2')->where('target_pk.0.indikator.0.target_tahunan', '94.05')
                ->where('target_pk.1.indikator.0.baseline', null)->where('target_pk.1.indikator.0.target_tahunan', null)
                ->missing('pk.target_pk')->missing('pk.baseline'));
    }

    public function test_pegawai_melihat_target_baca_saja_dan_perencanaan_dapat_mengubah(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $pk = $this->halamanPk($actor)['pk'];

        $this->actingAs($this->pegawai())->get("/perjanjian-kinerja/{$pk->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.update_target', false)
                ->where('target_pk.0.indikator.0.target_tahunan', '94.05'));
        $this->actingAs($actor)->get("/perjanjian-kinerja/{$pk->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.update_target', true));
    }

    public function test_props_target_hanya_memuat_field_yang_diizinkan(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $pk = $this->halamanPk($actor)['pk'];

        $this->actingAs($actor)->get("/perjanjian-kinerja/{$pk->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('target_pk.0', fn (Assert $sasaran) => $sasaran
                ->hasAll(['id', 'kode', 'deskripsi'])
                ->has('indikator.0', fn (Assert $indikator) => $indikator
                    ->hasAll(['id', 'kode', 'nama', 'satuan', 'desimal_tampilan', 'arah', 'baseline', 'target_tahunan', 'target_renstra', 'status_deviasi', 'read_only_reason']))
                ->has('indikator')));
    }

    /** @return array{pk: RenstraPk, s1: SasaranStrategis, s2: SasaranStrategis, iku1: IndikatorKinerja, iku2: IndikatorKinerja} */
    private function halamanPk(User $actor): array
    {
        $renstra = $this->calendarRenstra($actor);
        $unit = Unit::create(['nama' => 'Unit fixture '.Str::random(8), 'status' => 'aktif', 'created_by' => $actor->id]);
        $s2 = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S2', 'deskripsi' => 'Sasaran kedua', 'urutan' => 2]);
        $s1 = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S1', 'deskripsi' => 'Sasaran pertama', 'urutan' => 1]);
        $indikator = fn (SasaranStrategis $sasaran, string $kode, array $lain = []): IndikatorKinerja => IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id, 'kode' => $kode, 'nama' => "Indikator {$kode}", 'satuan' => 'nilai',
            'presisi' => 2, 'desimal_tampilan' => 2, 'tahun_mulai_berlaku' => 2026, 'created_by' => $actor->id, 'created_by_role' => 'perencanaan', ...$lain]);
        $iku1 = $indikator($s1, 'IKU-1');
        $indikator($s1, 'IKU-0-ARSIP', ['status' => IndikatorKinerja::STATUS_ARSIP]);
        $iku2 = $indikator($s2, 'IKU-2');
        $indikator($s2, 'IKU-3-2027', ['tahun_mulai_berlaku' => 2027]);
        TargetKinerja::create(['indikator_kinerja_id' => $iku1->id, 'tahun' => 2026, 'baseline' => '74.2', 'target_tahunan' => '94.05']);
        $pk = $this->targetPk($iku1, $actor);
        Berkas::forceCreate(['id' => (string) Str::uuid(), 'berkasable_type' => 'renstra_pk', 'berkasable_id' => $pk->id, 'mode' => 'tautan',
            'tautan' => 'https://contoh.go.id/pk', 'uploaded_by' => $actor->id]);

        return compact('pk', 's1', 's2', 'iku1', 'iku2');
    }

    private function editorUrl(RenstraPk $pk, string $indikatorId): string
    {
        return "/perjanjian-kinerja/{$pk->id}/target/{$indikatorId}/editor";
    }

    private function simpanUrl(RenstraPk $pk, string $indikatorId): string
    {
        return "/perjanjian-kinerja/{$pk->id}/target/{$indikatorId}";
    }

    /**
     * @param  array<string, mixed>  $nilai
     * @return array<string, mixed>
     */
    private function payload(string $expectedState, array $nilai = []): array
    {
        return ['baseline' => null, 'target_tahunan' => null, 'expected_state' => $expectedState, 'operation_id' => (string) Str::uuid(), ...$nilai];
    }

    private function pegawai(): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $user->roles()->attach(Role::where('kode', 'pegawai')->sole()->id, ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id, 'created_at' => now()]);

        return $user;
    }
}
