<?php

namespace Tests\Feature\IndikatorKomponen;

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
use Tests\Support\SubmitsIndicatorDefinition;
use Tests\TestCase;

/**
 * Kontrak R8-02: kandidat formula tidak dinilai terhadap state DB lama.
 *
 * Swap atomik (existing n→x + baru n) harus lolos karena final-set unik;
 * penegakan unique tetap pada duplikat final-set dan constraint DB.
 * Intent create tunggal tetap menolak kode yang berbenturan dengan state akhir.
 */
class FormulaSwapKodeBaruTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsIndicatorDefinition;

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
            'kode' => 'RENSTRA-SWAPBARU', 'nama' => 'Renstra Swap Baru',
            'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
            'created_by' => $this->actor->id,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id, 'kode' => 'SS-SWAPBARU',
            'deskripsi' => 'Sasaran Swap Baru', 'urutan' => 1,
        ]);
        $unit = Unit::create([
            'nama' => 'Unit Swap Baru', 'status' => 'aktif', 'created_by' => $this->actor->id,
        ]);
        $this->indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id,
            'kode' => 'IKU-SWAPBARU', 'nama' => 'Indikator Swap Baru', 'satuan' => '%',
            'tipe_perhitungan' => 'rasio_persen', 'arah' => 'naik_baik',
            'presisi' => 2, 'desimal_tampilan' => 2,
            'status' => 'aktif', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan',
        ]);
        $this->buatKomponen('n', 'pembilang', 1);
        $this->buatKomponen('t', 'penyebut', 2);
    }

    private function buatKomponen(string $kode, string $peran, int $urutan): IndikatorKomponen
    {
        return IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id, 'kode' => $kode,
            'label' => 'Komponen '.$kode, 'peran' => $peran, 'bobot' => '1',
            'urutan' => $urutan, 'aktif' => true,
            'created_by' => $this->actor->id,
        ]);
    }

    private function token(): string
    {
        return $this->indikator->fresh()->updated_at->toISOString();
    }

    /**
     * @return array<string, mixed>
     */
    private function baris(IndikatorKomponen $komponen): array
    {
        return [
            'id' => $komponen->id,
            'kode' => $komponen->kode, 'label' => $komponen->label,
            'peran' => $komponen->peran, 'bobot' => (string) $komponen->bobot,
            'urutan' => $komponen->urutan, 'aktif' => $komponen->aktif,
        ];
    }

    public function test_swap_membebaskan_kode_lama_untuk_baris_baru(): void
    {
        $n = $this->indikator->komponen()->where('kode', 'n')->firstOrFail();
        $t = $this->indikator->komponen()->where('kode', 't')->firstOrFail();

        $barisN = array_merge($this->baris($n), ['kode' => 'x']);
        $barisT = $this->baris($t);
        $barisBaru = [
            'kode' => 'n', 'label' => 'Komponen n baru', 'peran' => 'pembilang',
            'bobot' => '1', 'urutan' => 3, 'aktif' => true,
        ];

        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => [$barisN, $barisT, $barisBaru],
            'expected_updated_at' => $this->token(),
            'alasan' => 'Menukar kode n ke x lalu memakai ulang kode n yang dibebaskan.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('indikator_komponen', ['id' => $n->id, 'kode' => 'x', 'peran' => 'pembilang']);
        $this->assertDatabaseHas('indikator_komponen', ['id' => $t->id, 'kode' => 't', 'peran' => 'penyebut']);
        $this->assertDatabaseHas('indikator_komponen', [
            'indikator_id' => $this->indikator->id, 'kode' => 'n', 'label' => 'Komponen n baru',
        ]);
        $this->assertSame(
            ['n', 't', 'x'],
            $this->indikator->komponen()->pluck('kode')->sort()->values()->all()
        );
    }

    public function test_duplikat_final_set_tetap_ditolak_tanpa_mutasi(): void
    {
        $t = $this->indikator->komponen()->where('kode', 't')->firstOrFail();
        $sebelum = $this->indikator->komponen()->orderBy('id')->pluck('kode')->all();

        // Payload hanya memuat t + baru n; existing n yang dihilangkan tetap
        // dihitung pada final-set sehingga kode n ganda dan wajib 422.
        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen',
            'komponen' => [
                $this->baris($t),
                [
                    'kode' => 'n', 'label' => 'Komponen n ganda', 'peran' => 'pembilang',
                    'bobot' => '1', 'urutan' => 3, 'aktif' => true,
                ],
            ],
            'expected_updated_at' => $this->token(),
            'alasan' => 'Kontrol duplikat final-set pada transisi formula.',
        ])->assertUnprocessable()->assertJsonValidationErrors('kode');

        $this->assertSame($sebelum, $this->indikator->komponen()->orderBy('id')->pluck('kode')->all());
        $this->assertSame(2, $this->indikator->komponen()->count());
    }

    public function test_store_normal_duplikat_vs_db_tetap_ditolak(): void
    {
        $this->actingAs($this->actor)
            ->createKomponen($this->indikator->id, [
                'kode' => 'n', 'label' => 'Duplikat Normal',
                'peran' => 'pembilang', 'bobot' => '1', 'urutan' => 3, 'aktif' => true,
                'expected_updated_at' => $this->token(),
            ])
            ->assertSessionHasErrors(['kode']);

        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertSame(
            'Kode komponen sudah digunakan pada indikator ini.',
            (string) $errors->get('kode')[0]
        );
        $this->assertSame(2, $this->indikator->komponen()->count());
    }
}
