<?php

namespace Tests\Feature\IndikatorKomponen;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormulaGranularBeforeValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private IndikatorKinerja $indikator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->actor = User::factory()->create(['status' => 'aktif']);
        $this->actor->roles()->attach(Role::where('kode', 'perencanaan')->firstOrFail()->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);
        $renstra = Renstra::create([
            'kode' => 'RENSTRA-R702', 'nama' => 'Renstra R702',
            'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
            'created_by' => $this->actor->id,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id, 'kode' => 'SS-R702',
            'deskripsi' => 'Sasaran R702', 'urutan' => 1,
        ]);
        $unit = Unit::create([
            'nama' => 'Unit R702', 'status' => 'aktif', 'created_by' => $this->actor->id,
        ]);
        $this->indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id,
            'kode' => 'IKU-R702', 'nama' => 'Indikator R702', 'satuan' => '%',
            'tipe_perhitungan' => 'rasio_persen', 'arah' => 'naik_baik',
            'presisi' => 2, 'desimal_tampilan' => 2,
            'status' => 'aktif', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan',
        ]);
        IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id, 'kode' => 'n',
            'label' => 'Pembilang R702', 'peran' => 'pembilang', 'bobot' => '1',
            'urutan' => 1, 'aktif' => true,
            'created_by' => $this->actor->id,
        ]);
        IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id, 'kode' => 't',
            'label' => 'Penyebut R702', 'peran' => 'penyebut', 'bobot' => '1',
            'urutan' => 2, 'aktif' => true,
            'created_by' => $this->actor->id,
        ]);
    }

    private function token(): string
    {
        return $this->indikator->fresh()->updated_at->toISOString();
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        return [
            'parent' => $this->indikator->fresh()->getAttributes(),
            'children' => $this->indikator->komponen()->orderBy('id')->get()->map->getAttributes()->all(),
        ];
    }

    private function deny(string $permission): void
    {
        UserPermissionDeny::create([
            'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', $permission)->firstOrFail()->id,
            'unit_id' => null, 'alasan' => 'Penolakan eksplisit untuk R7-02.',
            'ditetapkan_oleh' => $this->actor->id,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function payloadInvalidDenganDeltaUpdate(): array
    {
        $n = $this->indikator->komponen()->where('kode', 'n')->firstOrFail();
        $t = $this->indikator->komponen()->where('kode', 't')->firstOrFail();

        return [
            [
                'id' => $n->id, 'kode' => $n->kode, 'label' => $n->label,
                'peran' => $n->peran, 'bobot' => '1', 'urutan' => 1, 'aktif' => true,
            ],
            [
                'id' => $t->id, 'kode' => $t->kode, 'label' => 'Penyebut diubah tanpa izin',
                'peran' => 'pembilang', 'bobot' => '1', 'urutan' => 2, 'aktif' => true,
            ],
        ];
    }

    public function test_deny_update_dengan_payload_invalid_ditolak_403_sebelum_validasi(): void
    {
        $this->deny('komponen:update');

        $before = $this->state();
        $auditCount = AuditLog::count();

        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => $this->payloadInvalidDenganDeltaUpdate(),
            'expected_updated_at' => $this->token(),
            'alasan' => 'Uji penolakan granular sebelum validasi formula.',
        ])->assertForbidden();

        $this->assertSame($before, $this->state());
        $this->assertSame($auditCount + 1, AuditLog::count());

        $audit = AuditLog::where('tindakan', 'indikator.ubah_ditolak')->firstOrFail();
        $this->assertSame('komponen:update', $audit->dasar_izin['permission']);
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);

        $this->assertDatabaseMissing('audit_log', [
            'tindakan' => 'indikator.ubah',
            'objek_id' => $this->indikator->id,
        ]);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'komponen.ubah']);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'komponen.buat']);
    }

    public function test_allow_dengan_payload_invalid_yang_sama_tetap_422(): void
    {
        $before = $this->state();
        $auditCount = AuditLog::count();

        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => $this->payloadInvalidDenganDeltaUpdate(),
            'expected_updated_at' => $this->token(),
            'alasan' => 'Kontrol payload invalid yang sama pada aktor berizin.',
        ])->assertUnprocessable()->assertJsonValidationErrors('tipe_perhitungan');

        $this->assertSame($before, $this->state());
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertSame('rasio_persen', $this->indikator->fresh()->tipe_perhitungan);
    }
}
