<?php

namespace Tests\Feature\IndikatorKomponen;

use App\Models\IndikatorKinerja;
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

class KomponenSyntaxUnifiedTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsIndicatorDefinition;

    private User $perencanaan;

    private Renstra $renstra;

    private Unit $unit;

    private int $urutanSasaran = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);

        $this->perencanaan = $this->userWithRole('perencanaan');

        $this->renstra = Renstra::create([
            'kode' => 'RENSTRA-SINTAKS',
            'nama' => 'Renstra Sintaks Unified',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $this->unit = Unit::create([
            'nama' => 'Unit Sintaks Unified',
            'status' => 'aktif',
            'created_by' => $this->perencanaan->id,
        ]);
    }

    private function userWithRole(string $kode): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', $kode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function buatIndikator(string $kode, string $tipe): IndikatorKinerja
    {
        $this->urutanSasaran++;

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-'.$kode,
            'deskripsi' => 'Sasaran '.$kode,
            'urutan' => $this->urutanSasaran,
        ]);

        return IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $this->unit->id,
            'kode' => $kode,
            'nama' => 'Indikator '.$kode,
            'satuan' => 'poin',
            'tipe_perhitungan' => $tipe,
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);
    }

    private function tokenVersi(IndikatorKinerja $indikator): string
    {
        $segar = $indikator->fresh();
        assert($segar instanceof IndikatorKinerja);

        return $segar->updated_at?->toISOString() ?? $segar->created_at->toISOString();
    }

    private function pesanError(string $key): string
    {
        $errors = session('errors');
        $this->assertNotNull($errors);
        $daftar = $errors->get($key);
        $this->assertNotEmpty($daftar, "Error key {$key} kosong.");

        return (string) $daftar[0];
    }

    /**
     * Membandingkan pesan sintaks via POST indikator atomik dan PATCH formula.
     *
     * @param  array<string, mixed>  $itemPost
     * @param  array<string, mixed>  $itemPatch
     */
    private function assertPesanIdentikKeduaJalur(
        array $itemPost,
        array $itemPatch,
        string $keyPost,
        string $keyPatch,
        string $tipePatch = 'penjumlahan'
    ): void {
        $keyPost = 'komponen.0.'.$keyPost;
        $indikatorPost = $this->buatIndikator('IKU-SIN-'.Str::upper(Str::random(6)).'-A', 'penjumlahan');
        $indikatorPatch = $this->buatIndikator('IKU-SIN-'.Str::upper(Str::random(6)).'-B', $tipePatch);

        $this->actingAs($this->perencanaan)
            ->post('/perencanaan/indikator', array_merge($indikatorPost->only(['sasaran_strategis_id', 'unit_id', 'nama', 'satuan', 'arah', 'tipe_perhitungan']), [
                'komponen' => [$itemPost],
            ]))
            ->assertSessionHasErrors([$keyPost]);
        $pesanPost = $this->pesanError($keyPost);

        $this->actingAs($this->perencanaan)
            ->patch("/perencanaan/indikator/{$indikatorPatch->id}/formula", [
                'tipe_perhitungan' => $tipePatch,
                'komponen' => [$itemPatch],
                'expected_updated_at' => $this->tokenVersi($indikatorPatch),
                'alasan' => 'Kontrol pesan sintaks pada jalur formula.',
            ])
            ->assertSessionHasErrors([$keyPatch]);
        $pesanPatch = $this->pesanError($keyPatch);

        $this->assertSame($pesanPost, $pesanPatch);
    }

    public function test_kode_regex_pesan_identik_kedua_jalur(): void
    {
        $item = [
            'kode' => 'kode-salah!',
            'label' => 'Label Valid',
            'peran' => 'penjumlah',
            'bobot' => '1.0',
            'urutan' => 1,
            'aktif' => true,
        ];

        $this->assertPesanIdentikKeduaJalur($item, $item, 'kode', 'komponen.0.kode');
    }

    public function test_label_melebihi_255_pesan_identik_kedua_jalur(): void
    {
        $panjang = str_repeat('a', 256);
        $item = [
            'kode' => 'label_panjang',
            'label' => $panjang,
            'peran' => 'penjumlah',
            'bobot' => '1.0',
            'urutan' => 1,
            'aktif' => true,
        ];

        $this->assertPesanIdentikKeduaJalur($item, $item, 'label', 'komponen.0.label');
    }

    public function test_peran_asing_pesan_identik_kedua_jalur(): void
    {
        $item = [
            'kode' => 'peran_asing',
            'label' => 'Label Valid',
            'peran' => 'asing',
            'bobot' => '1.0',
            'urutan' => 1,
            'aktif' => true,
        ];

        $this->assertPesanIdentikKeduaJalur($item, $item, 'peran', 'komponen.0.peran');
    }

    public function test_bobot_desimal_lebih_12_pesan_identik_kedua_jalur(): void
    {
        $item = [
            'kode' => 'bobot_presisi',
            'label' => 'Label Valid',
            'peran' => 'penjumlah',
            'bobot' => '0.0000000000001',
            'urutan' => 1,
            'aktif' => true,
        ];

        $this->assertPesanIdentikKeduaJalur($item, $item, 'bobot', 'komponen.0.bobot');
    }

    public function test_urutan_nol_pesan_identik_kedua_jalur(): void
    {
        $item = [
            'kode' => 'urutan_nol',
            'label' => 'Label Valid',
            'peran' => 'penjumlah',
            'bobot' => '1.0',
            'urutan' => 0,
            'aktif' => true,
        ];

        $this->assertPesanIdentikKeduaJalur($item, $item, 'urutan', 'komponen.0.urutan');
    }

    public function test_penyebut_bobot_nol_pesan_identik_kedua_jalur(): void
    {
        $itemPost = [
            'kode' => 't_nol_unified',
            'label' => 'Penyebut Nol',
            'peran' => 'penyebut',
            'bobot' => '0',
            'urutan' => 1,
            'aktif' => true,
        ];
        $itemPatch = [
            'kode' => 't_nol_unified',
            'label' => 'Penyebut Nol',
            'peran' => 'penyebut',
            'bobot' => '0',
            'urutan' => 2,
            'aktif' => true,
        ];
        $pembilang = [
            'kode' => 'n_unified',
            'label' => 'Pembilang',
            'peran' => 'pembilang',
            'bobot' => '1.0',
            'urutan' => 1,
            'aktif' => true,
        ];

        $indikatorPost = $this->buatIndikator('IKU-SIN-PENY-A', 'rasio_persen');
        $indikatorPatch = $this->buatIndikator('IKU-SIN-PENY-B', 'rasio_persen');

        $this->actingAs($this->perencanaan)
            ->post('/perencanaan/indikator', array_merge($indikatorPost->only(['sasaran_strategis_id', 'unit_id', 'nama', 'satuan', 'arah', 'tipe_perhitungan']), [
                'komponen' => [$itemPost],
            ]))
            ->assertSessionHasErrors(['komponen.0.bobot']);
        $pesanPost = $this->pesanError('komponen.0.bobot');

        $this->actingAs($this->perencanaan)
            ->patch("/perencanaan/indikator/{$indikatorPatch->id}/formula", [
                'tipe_perhitungan' => 'rasio_persen',
                'komponen' => [$pembilang, $itemPatch],
                'expected_updated_at' => $this->tokenVersi($indikatorPatch),
                'alasan' => 'Kontrol pesan penyebut nol pada jalur formula.',
            ])
            ->assertSessionHasErrors(['komponen.1.bobot']);
        $pesanPatch = $this->pesanError('komponen.1.bobot');

        $this->assertSame($pesanPost, $pesanPatch);
        $this->assertSame('Bobot untuk komponen dengan peran penyebut wajib lebih besar dari 0.', $pesanPost);
    }
}
