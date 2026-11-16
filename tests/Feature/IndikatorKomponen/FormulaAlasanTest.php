<?php

namespace Tests\Feature\IndikatorKomponen;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormulaAlasanTest extends TestCase
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
            'kode' => 'RENSTRA-R703', 'nama' => 'Renstra R703',
            'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
            'created_by' => $this->actor->id,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id, 'kode' => 'SS-R703',
            'deskripsi' => 'Sasaran R703', 'urutan' => 1,
        ]);
        $unit = Unit::create([
            'nama' => 'Unit R703', 'status' => 'aktif', 'created_by' => $this->actor->id,
        ]);
        $this->indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id,
            'kode' => 'IKU-R703', 'nama' => 'Indikator R703', 'satuan' => '%',
            'tipe_perhitungan' => 'rasio_persen', 'arah' => 'naik_baik',
            'presisi' => 2, 'desimal_tampilan' => 2,
            'status' => 'aktif', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan',
        ]);
        IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id, 'kode' => 'n',
            'label' => 'Pembilang R703', 'peran' => 'pembilang', 'bobot' => '1',
            'urutan' => 1, 'aktif' => true,
            'created_by' => $this->actor->id,
        ]);
        IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id, 'kode' => 't',
            'label' => 'Penyebut R703', 'peran' => 'penyebut', 'bobot' => '1',
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

    /** @return list<array<string, mixed>> */
    private function payloadFinalSet(): array
    {
        $n = $this->indikator->komponen()->where('kode', 'n')->firstOrFail();
        $t = $this->indikator->komponen()->where('kode', 't')->firstOrFail();

        return [
            [
                'id' => $n->id, 'kode' => $n->kode, 'label' => 'Pembilang hasil revisi R703',
                'peran' => $n->peran, 'bobot' => '1', 'urutan' => 1, 'aktif' => true,
            ],
            [
                'id' => $t->id, 'kode' => $t->kode, 'label' => $t->label,
                'peran' => $t->peran, 'bobot' => '1', 'urutan' => 2, 'aktif' => true,
            ],
            [
                'kode' => 'c', 'label' => 'Cadangan R703',
                'peran' => 'pembilang', 'bobot' => '1', 'urutan' => 3, 'aktif' => true,
            ],
        ];
    }

    public function test_tanpa_alasan_ditolak_422_tanpa_mutasi_atau_audit(): void
    {
        $before = $this->state();
        $auditCount = AuditLog::count();

        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => $this->payloadFinalSet(),
            'expected_updated_at' => $this->token(),
        ])->assertUnprocessable()->assertJsonValidationErrors('alasan');

        $this->assertSame($before, $this->state());
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertSame('rasio_persen', $this->indikator->fresh()->tipe_perhitungan);
    }

    public function test_alasan_pendek_ditolak_422_tanpa_mutasi_atau_audit(): void
    {
        $before = $this->state();
        $auditCount = AuditLog::count();

        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => $this->payloadFinalSet(),
            'expected_updated_at' => $this->token(),
            'alasan' => 'abc',
        ])->assertUnprocessable()->assertJsonValidationErrors('alasan');

        $this->assertSame($before, $this->state());
        $this->assertSame($auditCount, AuditLog::count());
    }

    public function test_alasan_valid_mencatat_rationale_pada_audit_induk_dan_child(): void
    {
        $alasan = 'Penyesuaian formula triwulan III sesuai arahan pimpinan.';

        $this->actingAs($this->actor)->patch("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => $this->payloadFinalSet(),
            'expected_updated_at' => $this->token(),
            'alasan' => $alasan,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $n = $this->indikator->komponen()->where('kode', 'n')->firstOrFail();
        $this->assertSame('Pembilang hasil revisi R703', $n->label);

        $auditUbah = AuditLog::where('tindakan', 'komponen.ubah')->where('objek_id', $n->id)->firstOrFail();
        $this->assertStringContainsString($alasan, $auditUbah->alasan);

        $baru = $this->indikator->komponen()->where('kode', 'c')->firstOrFail();
        $auditBuat = AuditLog::where('tindakan', 'komponen.buat')->where('objek_id', $baru->id)->firstOrFail();
        $this->assertStringContainsString($alasan, $auditBuat->alasan);

        // Child-only: audit granular cukup; tidak mengarang delta/izin parent.
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'indikator.ubah', 'objek_id' => $this->indikator->id]);
    }
}
