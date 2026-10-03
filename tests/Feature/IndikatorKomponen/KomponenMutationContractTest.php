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

class KomponenMutationContractTest extends TestCase
{
    use RefreshDatabase;

    private User $perencanaan;

    private Renstra $renstra;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);

        $this->perencanaan = $this->userWithRole('perencanaan');

        $this->renstra = Renstra::create([
            'kode' => 'RENSTRA-KONTRAK',
            'nama' => 'Renstra Kontrak Mutasi',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);

        $this->unit = Unit::create([
            'nama' => 'Unit Kontrak Mutasi',
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
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $this->renstra->id,
            'kode' => 'SS-'.$kode,
            'deskripsi' => 'Sasaran '.$kode,
            'urutan' => 1,
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

    /**
     * Payload valid yang sama via POST normal dan PATCH formula menghasilkan
     * bentuk tersimpan dan audit komponen.buat yang identik.
     */
    public function test_payload_valid_menghasilkan_persisted_dan_audit_identik(): void
    {
        $exactBobot = '123456789.123456789012';
        $indikatorPost = $this->buatIndikator('IKU-KONTRAK-VALID-A', 'penjumlahan');
        $indikatorPatch = $this->buatIndikator('IKU-KONTRAK-VALID-B', 'penjumlahan');

        $this->actingAs($this->perencanaan)
            ->post("/indikator/{$indikatorPost->id}/komponen", [
                'kode' => 'jml_valid',
                'label' => '  Penjumlah Valid  ',
                'peran' => 'penjumlah',
                'bobot' => $exactBobot,
                'urutan' => 1,
                'satuan' => 'poin',
                'aktif' => true,
                'expected_updated_at' => $this->tokenVersi($indikatorPost),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect("/indikator/{$indikatorPost->id}/komponen");

        $this->actingAs($this->perencanaan)
            ->patch("/perencanaan/indikator/{$indikatorPatch->id}/formula", [
                'tipe_perhitungan' => 'penjumlahan',
                'komponen' => [
                    [
                        'kode' => 'jml_valid',
                        'label' => '  Penjumlah Valid  ',
                        'peran' => 'penjumlah',
                        'bobot' => $exactBobot,
                        'urutan' => 1,
                        'satuan' => 'poin',
                        'aktif' => true,
                    ],
                ],
                'expected_updated_at' => $this->tokenVersi($indikatorPatch),
                'alasan' => 'Penambahan penjumlah valid via transisi formula atomik.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        foreach ([$indikatorPost, $indikatorPatch] as $indikator) {
            $this->assertDatabaseHas('indikator_komponen', [
                'indikator_id' => $indikator->id,
                'kode' => 'jml_valid',
                'peran' => 'penjumlah',
                'urutan' => 1,
            ]);
        }

        $komponenPost = IndikatorKomponen::where('indikator_id', $indikatorPost->id)->where('kode', 'jml_valid')->firstOrFail();
        $komponenPatch = IndikatorKomponen::where('indikator_id', $indikatorPatch->id)->where('kode', 'jml_valid')->firstOrFail();

        // Normalisasi bersama: label di-trim pada kedua jalur.
        $this->assertSame('Penjumlah Valid', $komponenPost->label);
        $this->assertSame('Penjumlah Valid', $komponenPatch->label);

        $auditPost = AuditLog::where('tindakan', 'komponen.buat')->where('objek_id', $komponenPost->id)->firstOrFail();
        $auditPatch = AuditLog::where('tindakan', 'komponen.buat')->where('objek_id', $komponenPatch->id)->firstOrFail();

        // Snapshot audit bobot eksak identik pada kedua jalur.
        $this->assertSame($exactBobot, (string) $auditPost->nilai_baru['bobot']);
        $this->assertSame($exactBobot, (string) $auditPatch->nilai_baru['bobot']);
        $this->assertSame($auditPost->nilai_baru['kode'], $auditPatch->nilai_baru['kode']);
        $this->assertSame($auditPost->nilai_baru['peran'], $auditPatch->nilai_baru['peran']);
    }

    /**
     * Kode duplikat ditolak kedua jalur dengan pesan yang sama.
     */
    public function test_kode_duplikat_pesan_identik_kedua_jalur(): void
    {
        $indikatorPost = $this->buatIndikator('IKU-KONTRAK-DUP-A', 'rasio_persen');
        $indikatorPatch = $this->buatIndikator('IKU-KONTRAK-DUP-B', 'rasio_persen');
        $pesan = 'Kode komponen sudah digunakan pada indikator ini.';

        foreach ([$indikatorPost, $indikatorPatch] as $indikator) {
            IndikatorKomponen::create([
                'indikator_id' => $indikator->id,
                'kode' => 'dup_kode',
                'label' => 'Awal',
                'peran' => 'pembilang',
                'bobot' => 1.0,
                'urutan' => 1,
                'aktif' => true,
                'created_by' => $this->perencanaan->id,
            ]);
        }

        $this->actingAs($this->perencanaan)
            ->post("/indikator/{$indikatorPost->id}/komponen", [
                'kode' => 'dup_kode',
                'label' => 'Duplikat Normal',
                'peran' => 'pembilang',
                'bobot' => 1.0,
                'urutan' => 2,
                'aktif' => true,
                'expected_updated_at' => $this->tokenVersi($indikatorPost),
            ])
            ->assertSessionHasErrors(['kode']);

        $this->assertSame($pesan, $this->pesanErrorPertama('kode'));

        $this->actingAs($this->perencanaan)
            ->patch("/perencanaan/indikator/{$indikatorPatch->id}/formula", [
                'tipe_perhitungan' => 'rasio_persen',
                'komponen' => [
                    ['kode' => 'n', 'label' => 'Pembilang', 'peran' => 'pembilang', 'bobot' => 1.0, 'urutan' => 1, 'aktif' => true],
                    ['kode' => 't', 'label' => 'Penyebut', 'peran' => 'penyebut', 'bobot' => 1.0, 'urutan' => 2, 'aktif' => true],
                    ['kode' => 'dup_kode', 'label' => 'Duplikat Atomik', 'peran' => 'pembilang', 'bobot' => 1.0, 'urutan' => 3, 'aktif' => true],
                ],
                'expected_updated_at' => $this->tokenVersi($indikatorPatch),
                'alasan' => 'Kontrol pesan kode duplikat pada jalur formula.',
            ])
            ->assertSessionHasErrors();

        $this->assertStringContainsString($pesan, $this->pesanErrorPatch());
    }

    /**
     * Penyebut berbobot nol ditolak kedua jalur dengan pesan yang sama.
     */
    public function test_penyebut_bobot_nol_pesan_identik_kedua_jalur(): void
    {
        $indikatorPost = $this->buatIndikator('IKU-KONTRAK-NOL-A', 'rasio_persen');
        $indikatorPatch = $this->buatIndikator('IKU-KONTRAK-NOL-B', 'manual');
        $pesan = 'Bobot untuk komponen dengan peran penyebut wajib lebih besar dari 0.';

        $this->actingAs($this->perencanaan)
            ->post("/indikator/{$indikatorPost->id}/komponen", [
                'kode' => 't_nol',
                'label' => 'Penyebut Nol',
                'peran' => 'penyebut',
                'bobot' => '0',
                'urutan' => 1,
                'aktif' => true,
                'expected_updated_at' => $this->tokenVersi($indikatorPost),
            ])
            ->assertSessionHasErrors(['bobot']);

        $this->assertSame($pesan, $this->pesanErrorPertama('bobot'));

        $this->actingAs($this->perencanaan)
            ->patch("/perencanaan/indikator/{$indikatorPatch->id}/formula", [
                'tipe_perhitungan' => 'rasio_persen',
                'komponen' => [
                    ['kode' => 'n', 'label' => 'Pembilang', 'peran' => 'pembilang', 'bobot' => 1.0, 'urutan' => 1, 'aktif' => true],
                    ['kode' => 't_nol', 'label' => 'Penyebut Nol', 'peran' => 'penyebut', 'bobot' => '0', 'urutan' => 2, 'aktif' => true],
                ],
                'expected_updated_at' => $this->tokenVersi($indikatorPatch),
                'alasan' => 'Kontrol pesan penyebut nol pada jalur formula.',
            ])
            ->assertSessionHasErrors();

        $this->assertStringContainsString($pesan, $this->pesanErrorPatch());
    }

    /**
     * Peran campur pada rasio memakai penentu akhir yang sama di kedua jalur.
     */
    public function test_peran_campur_memakai_penentu_akhir_yang_sama(): void
    {
        $indikatorPost = $this->buatIndikator('IKU-KONTRAK-CAMPUR-A', 'rasio_persen');
        $indikatorPatch = $this->buatIndikator('IKU-KONTRAK-CAMPUR-B', 'manual');

        // Susun komposisi awal valid yang sama pada jalur normal agar komposisi
        // akhir apple-to-apple dengan kandidat atomik (pembilang + penyebut).
        foreach ([
            ['kode' => 'n', 'label' => 'Pembilang', 'peran' => 'pembilang', 'urutan' => 1],
            ['kode' => 't', 'label' => 'Penyebut', 'peran' => 'penyebut', 'urutan' => 2],
        ] as $awal) {
            IndikatorKomponen::create(array_merge($awal, [
                'indikator_id' => $indikatorPost->id,
                'created_by' => $this->perencanaan->id,
                'bobot' => 1.0,
                'aktif' => true,
            ]));
        }

        $beforeChildren = $indikatorPost->komponen()->get()->map->getAttributes()->all();
        $beforeParent = $indikatorPost->fresh()->getAttributes();
        $beforeAudits = AuditLog::count();
        // Jalur normal menambah satu penjumlah; komposisi akhir menjadi invalid
        // menurut penentu yang sama.
        $this->actingAs($this->perencanaan)
            ->post("/indikator/{$indikatorPost->id}/komponen", [
                'kode' => 'jml_campur',
                'label' => 'Penjumlah Campur',
                'peran' => 'penjumlah',
                'bobot' => 1.0,
                'urutan' => 3,
                'aktif' => true,
                'expected_updated_at' => $this->tokenVersi($indikatorPost),
            ])
            ->assertSessionHasErrors('komponen');

        $pesanPost = session('errors')->get('komponen');
        $this->assertSame($beforeChildren, $indikatorPost->komponen()->get()->map->getAttributes()->all());
        $this->assertSame($beforeParent, $indikatorPost->fresh()->getAttributes());
        $this->assertSame($beforeAudits, AuditLog::count());

        $this->actingAs($this->perencanaan)
            ->patch("/perencanaan/indikator/{$indikatorPatch->id}/formula", [
                'tipe_perhitungan' => 'rasio_persen',
                'komponen' => [
                    ['kode' => 'n', 'label' => 'Pembilang', 'peran' => 'pembilang', 'bobot' => 1.0, 'urutan' => 1, 'aktif' => true],
                    ['kode' => 't', 'label' => 'Penyebut', 'peran' => 'penyebut', 'bobot' => 1.0, 'urutan' => 2, 'aktif' => true],
                    ['kode' => 'jml_campur', 'label' => 'Penjumlah Campur', 'peran' => 'penjumlah', 'bobot' => 1.0, 'urutan' => 3, 'aktif' => true],
                ],
                'expected_updated_at' => $this->tokenVersi($indikatorPatch),
                'alasan' => 'Kontrol penentu peran campur pada jalur formula.',
            ])
            ->assertSessionHasErrors(['tipe_perhitungan']);

        $pesanPatch = session('errors')?->get('tipe_perhitungan') ?? [];

        $this->assertNotEmpty($pesanPost);
        $this->assertNotEmpty($pesanPatch);
        $this->assertSame($pesanPost, $pesanPatch);
        $this->assertStringContainsString(
            'tidak boleh memiliki komponen berperan penjumlah',
            implode(' ', $pesanPatch)
        );
    }

    /**
     * Rasio tanpa penyebut memakai penentu akhir yang sama di kedua jalur.
     */
    public function test_tanpa_penyebut_memakai_penentu_akhir_yang_sama(): void
    {
        $indikatorPost = $this->buatIndikator('IKU-KONTRAK-TANPA-A', 'rasio_persen');
        $indikatorPatch = $this->buatIndikator('IKU-KONTRAK-TANPA-B', 'manual');

        $this->actingAs($this->perencanaan)
            ->post("/indikator/{$indikatorPost->id}/komponen", [
                'kode' => 'n_saja',
                'label' => 'Pembilang Saja',
                'peran' => 'pembilang',
                'bobot' => 1.0,
                'urutan' => 1,
                'aktif' => true,
                'expected_updated_at' => $this->tokenVersi($indikatorPost),
            ])
            ->assertSessionHasErrors('komponen');

        $pesanPost = session('errors')->get('komponen');
        $this->assertSame(0, $indikatorPost->komponen()->count());
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'komponen.buat']);

        $this->actingAs($this->perencanaan)
            ->patch("/perencanaan/indikator/{$indikatorPatch->id}/formula", [
                'tipe_perhitungan' => 'rasio_persen',
                'komponen' => [
                    ['kode' => 'n_saja', 'label' => 'Pembilang Saja', 'peran' => 'pembilang', 'bobot' => 1.0, 'urutan' => 1, 'aktif' => true],
                ],
                'expected_updated_at' => $this->tokenVersi($indikatorPatch),
                'alasan' => 'Kontrol rasio tanpa penyebut pada jalur formula.',
            ])
            ->assertSessionHasErrors(['tipe_perhitungan']);

        $pesanPatch = session('errors')?->get('tipe_perhitungan') ?? [];

        $this->assertNotEmpty($pesanPost);
        $this->assertNotEmpty($pesanPatch);
        $this->assertSame($pesanPost, $pesanPatch);
        $this->assertStringContainsString('penyebut', implode(' ', $pesanPatch));
    }

    private function pesanErrorPertama(string $key): string
    {
        $errors = session('errors');
        $this->assertNotNull($errors);

        $daftar = $errors->get($key);
        $this->assertNotEmpty($daftar);

        return (string) $daftar[0];
    }

    private function pesanErrorPatch(): string
    {
        $errors = session('errors');
        $this->assertNotNull($errors);

        $semua = [];
        foreach ($errors->getBags() as $bag) {
            foreach ($bag->all() as $pesan) {
                $semua[] = $pesan;
            }
        }

        return implode(' ', $semua);
    }
}
