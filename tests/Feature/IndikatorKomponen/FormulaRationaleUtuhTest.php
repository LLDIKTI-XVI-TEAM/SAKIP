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

class FormulaRationaleUtuhTest extends TestCase
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
            'kode' => 'RENSTRA-R803', 'nama' => 'Renstra R803',
            'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
            'created_by' => $this->actor->id,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id, 'kode' => 'SS-R803',
            'deskripsi' => 'Sasaran R803', 'urutan' => 1,
        ]);
        $unit = Unit::create([
            'nama' => 'Unit R803', 'status' => 'aktif', 'created_by' => $this->actor->id,
        ]);
        $this->indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id,
            'kode' => 'IKU-R803', 'nama' => 'Indikator R803', 'satuan' => '%',
            'tipe_perhitungan' => 'rasio_persen', 'arah' => 'naik_baik',
            'presisi' => 2, 'desimal_tampilan' => 2,
            'status' => 'aktif', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan',
        ]);
        IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id, 'kode' => 'n',
            'label' => 'Pembilang R803', 'peran' => 'pembilang', 'bobot' => '1',
            'urutan' => 1, 'aktif' => true,
            'created_by' => $this->actor->id,
        ]);
        IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id, 'kode' => 't',
            'label' => 'Penyebut R803', 'peran' => 'penyebut', 'bobot' => '1',
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
                'id' => $n->id, 'kode' => $n->kode, 'label' => 'Pembilang hasil revisi R803',
                'peran' => $n->peran, 'bobot' => '1', 'urutan' => 1, 'aktif' => true,
            ],
            [
                'id' => $t->id, 'kode' => $t->kode, 'label' => $t->label,
                'peran' => $t->peran, 'bobot' => '1', 'urutan' => 2, 'aktif' => true,
            ],
            [
                'kode' => 'c', 'label' => 'Cadangan R803',
                'peran' => 'pembilang', 'bobot' => '1', 'urutan' => 3, 'aktif' => true,
            ],
        ];
    }

    public function test_alasan_tepat_batas_tercatat_utuh_pada_audit_child(): void
    {
        // Ekor penanda membuktikan tidak ada pemangkasan di audit child.
        $alasan = str_repeat('r', 990).'-EKOR-UTUH';
        $this->assertSame(1000, mb_strlen($alasan, 'UTF-8'));

        $this->actingAs($this->actor)->patch("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => $this->payloadFinalSet(),
            'expected_updated_at' => $this->token(),
            'alasan' => $alasan,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $n = $this->indikator->komponen()->where('kode', 'n')->firstOrFail();
        $auditUbah = AuditLog::where('tindakan', 'komponen.ubah')->where('objek_id', $n->id)->firstOrFail();
        $this->assertSame($alasan, $auditUbah->alasan);
        $this->assertStringEndsWith('-EKOR-UTUH', $auditUbah->alasan);

        $baru = $this->indikator->komponen()->where('kode', 'c')->firstOrFail();
        $auditBuat = AuditLog::where('tindakan', 'komponen.buat')->where('objek_id', $baru->id)->firstOrFail();
        $this->assertSame($alasan, $auditBuat->alasan);
        $this->assertStringEndsWith('-EKOR-UTUH', $auditBuat->alasan);

        // Child-only: audit granular cukup; tidak mengarang delta/izin parent.
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'indikator.ubah', 'objek_id' => $this->indikator->id]);
    }

    public function test_alasan_lewat_batas_ditolak_422_tanpa_mutasi_atau_audit(): void
    {
        $before = $this->state();
        $auditCount = AuditLog::count();

        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => $this->payloadFinalSet(),
            'expected_updated_at' => $this->token(),
            'alasan' => str_repeat('r', 1001),
        ])->assertUnprocessable()->assertJsonValidationErrors('alasan');

        $this->assertSame($before, $this->state());
        $this->assertSame($auditCount, AuditLog::count());
    }
}
