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

class FormulaReadGateTest extends TestCase
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
            'kode' => 'RENSTRA-R704', 'nama' => 'Renstra R704',
            'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
            'created_by' => $this->actor->id,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id, 'kode' => 'SS-R704',
            'deskripsi' => 'Sasaran R704', 'urutan' => 1,
        ]);
        $unit = Unit::create([
            'nama' => 'Unit R704', 'status' => 'aktif', 'created_by' => $this->actor->id,
        ]);
        $this->indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id,
            'kode' => 'IKU-R704', 'nama' => 'Indikator R704', 'satuan' => '%',
            'tipe_perhitungan' => 'rasio_persen', 'arah' => 'naik_baik',
            'presisi' => 2, 'desimal_tampilan' => 2,
            'status' => 'aktif', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan',
        ]);
        IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id, 'kode' => 'n',
            'label' => 'Pembilang R704', 'peran' => 'pembilang', 'bobot' => '1',
            'urutan' => 1, 'aktif' => true,
            'created_by' => $this->actor->id,
        ]);
        IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id, 'kode' => 't',
            'label' => 'Penyebut R704', 'peran' => 'penyebut', 'bobot' => '1',
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
            'unit_id' => null, 'alasan' => 'Penolakan eksplisit untuk R7-04.',
            'ditetapkan_oleh' => $this->actor->id,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function payloadCampur(): array
    {
        $n = $this->indikator->komponen()->where('kode', 'n')->firstOrFail();
        $t = $this->indikator->komponen()->where('kode', 't')->firstOrFail();

        return [
            [
                'id' => $n->id, 'kode' => $n->kode, 'label' => 'Pembilang hasil revisi R704',
                'peran' => $n->peran, 'bobot' => '1', 'urutan' => 1, 'aktif' => true,
            ],
            [
                'id' => $t->id, 'kode' => $t->kode, 'label' => $t->label,
                'peran' => $t->peran, 'bobot' => '1', 'urutan' => 2, 'aktif' => true,
            ],
            [
                'kode' => 'c', 'label' => 'Cadangan R704',
                'peran' => 'pembilang', 'bobot' => '1', 'urutan' => 3, 'aktif' => true,
            ],
        ];
    }

    public function test_deny_read_dengan_final_set_manual_kosong_ditolak_403_tanpa_mutasi(): void
    {
        $this->deny('komponen:read');

        $childIds = $this->indikator->komponen()->pluck('id')->all();
        $before = $this->state();
        $auditCount = AuditLog::count();

        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'manual',
            'komponen' => [],
            'expected_updated_at' => $this->token(),
            'alasan' => 'Uji gate baca pada final-set manual kosong.',
        ])->assertForbidden();

        $this->assertSame($before, $this->state());
        $this->assertSame($auditCount + 1, AuditLog::count());

        $audit = AuditLog::where('tindakan', 'indikator.ubah_ditolak')->firstOrFail();
        $this->assertSame('komponen:read', $audit->dasar_izin['permission']);
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
        foreach ($childIds as $childId) {
            $this->assertStringNotContainsString((string) $childId, (string) $audit->alasan);
        }

        $this->assertDatabaseMissing('audit_log', [
            'tindakan' => 'indikator.ubah',
            'objek_id' => $this->indikator->id,
        ]);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'komponen.ubah']);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'komponen.buat']);
    }

    public function test_deny_read_dengan_final_set_campur_ditolak_403_tanpa_mutasi(): void
    {
        $this->deny('komponen:read');

        $childIds = $this->indikator->komponen()->pluck('id')->all();
        $before = $this->state();
        $auditCount = AuditLog::count();

        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => $this->payloadCampur(),
            'expected_updated_at' => $this->token(),
            'alasan' => 'Uji gate baca pada final-set campur ubah dan tambah.',
        ])->assertForbidden();

        $this->assertSame($before, $this->state());
        $this->assertSame($auditCount + 1, AuditLog::count());

        $audit = AuditLog::where('tindakan', 'indikator.ubah_ditolak')->firstOrFail();
        $this->assertSame('komponen:read', $audit->dasar_izin['permission']);
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
        foreach ($childIds as $childId) {
            $this->assertStringNotContainsString((string) $childId, (string) $audit->alasan);
        }

        $this->assertDatabaseMissing('audit_log', [
            'tindakan' => 'indikator.ubah',
            'objek_id' => $this->indikator->id,
        ]);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'komponen.ubah']);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'komponen.buat']);
    }

    public function test_allow_semua_dengan_final_set_campur_tetap_sukses(): void
    {
        $alasan = 'Penyesuaian formula triwulan III sesuai arahan pimpinan.';

        $this->actingAs($this->actor)->patch("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => $this->payloadCampur(),
            'expected_updated_at' => $this->token(),
            'alasan' => $alasan,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $n = $this->indikator->komponen()->where('kode', 'n')->firstOrFail();
        $this->assertSame('Pembilang hasil revisi R704', $n->label);

        $baru = $this->indikator->komponen()->where('kode', 'c')->firstOrFail();
        $this->assertSame('Cadangan R704', $baru->label);

        $auditUbah = AuditLog::where('tindakan', 'komponen.ubah')->where('objek_id', $n->id)->firstOrFail();
        $this->assertStringContainsString($alasan, $auditUbah->alasan);

        $auditBuat = AuditLog::where('tindakan', 'komponen.buat')->where('objek_id', $baru->id)->firstOrFail();
        $this->assertStringContainsString($alasan, $auditBuat->alasan);

        // Child-only: audit granular cukup; tidak mengarang delta/izin parent.
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'indikator.ubah', 'objek_id' => $this->indikator->id]);
    }
}
