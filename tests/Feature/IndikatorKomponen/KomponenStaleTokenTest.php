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
use Tests\Support\SubmitsIndicatorDefinition;
use Tests\TestCase;

class KomponenStaleTokenTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsIndicatorDefinition;

    private User $perencanaan;

    private IndikatorKinerja $indikator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);

        $this->perencanaan = $this->userWithRole('perencanaan');

        $renstra = Renstra::create([
            'kode' => 'RENSTRA-STALE-TOKEN',
            'created_by' => $this->perencanaan->id,
            'nama' => 'Renstra Stale Token',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'SS-STALE',
            'deskripsi' => 'Sasaran Stale Token',
            'urutan' => 1,
        ]);
        $unit = Unit::create([
            'nama' => 'Unit Stale Token',
            'status' => 'aktif',
            'created_by' => $this->perencanaan->id,
        ]);

        $this->indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'IKU-STALE-TOKEN',
            'nama' => 'Indikator Stale Token',
            'satuan' => '%',
            'tipe_perhitungan' => 'rasio_persen',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->perencanaan->id,
            'created_by_role' => 'perencanaan',
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

    private function tokenVersi(): string
    {
        $segar = $this->indikator->fresh();

        return $segar->updated_at?->toISOString() ?? $segar->created_at->toISOString();
    }

    private function buatKomponen(string $kode, string $peran, int $urutan): IndikatorKomponen
    {
        return IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id,
            'kode' => $kode,
            'label' => 'Komponen '.$kode,
            'peran' => $peran,
            'bobot' => '1',
            'urutan' => $urutan,
            'aktif' => true,
            'created_by' => $this->perencanaan->id,
        ]);
    }

    public function test_index_mengekspos_updated_at_iso_induk(): void
    {
        $response = $this->actingAs($this->perencanaan)
            ->get("/indikator/{$this->indikator->id}/komponen")
            ->assertOk();

        $props = $response->original->getData()['page']['props'];
        $this->assertSame($this->tokenVersi(), $props['editor']['revision']);
    }

    public function test_store_token_usang_ditolak_konflik_tanpa_mutasi(): void
    {
        $this->buatKomponen('t', 'penyebut', 2);
        $tokenTabA = $this->tokenVersi();

        // Tab-B menyimpan dengan token segar → versi induk bump.
        $this->actingAs($this->perencanaan)
            ->createKomponen($this->indikator->id, [
                'kode' => 'p1',
                'label' => 'Pembilang Tab B',
                'peran' => 'pembilang',
                'bobot' => '1.0',
                'urutan' => 1,
                'aktif' => true,
                'expected_updated_at' => $tokenTabA,
            ])
            ->assertRedirect("/indikator/{$this->indikator->id}/komponen")
            ->assertSessionHasNoErrors();
        $this->assertNotSame($tokenTabA, $this->tokenVersi());

        // Tab-A menyimpan dengan token lama → 409 konflik tanpa mutasi/audit.
        $this->actingAs($this->perencanaan)
            ->createKomponen($this->indikator->id, [
                'kode' => 'p2',
                'label' => 'Pembilang Tab A Usang',
                'peran' => 'pembilang',
                'bobot' => '1.0',
                'urutan' => 3,
                'aktif' => true,
                'expected_updated_at' => $tokenTabA,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['konflik']);

        $this->assertDatabaseMissing('indikator_komponen', [
            'indikator_id' => $this->indikator->id,
            'kode' => 'p2',
        ]);
        $this->assertSame(1, AuditLog::where('tindakan', 'komponen.buat')->count());
    }

    public function test_update_token_usang_ditolak_konflik_tanpa_mutasi(): void
    {
        $this->buatKomponen('t', 'penyebut', 2);
        $komponen = $this->buatKomponen('n', 'pembilang', 1);
        $tokenTabA = $this->tokenVersi();
        $pesanKonflik = 'Data indikator kinerja telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.';

        $payload = fn (string $label, string $token): array => [
            'kode' => 'n',
            'label' => $label,
            'peran' => 'pembilang',
            'bobot' => '1.0',
            'urutan' => 1,
            'aktif' => true,
            'alasan' => 'Penyesuaian label komponen antar tab.',
            'expected_updated_at' => $token,
        ];

        // Tab-B memperbarui dengan token segar.
        $this->actingAs($this->perencanaan)
            ->updateKomponen($this->indikator->id, $komponen->id, $payload('Label Tab B', $tokenTabA))
            ->assertRedirect("/indikator/{$this->indikator->id}/komponen")
            ->assertSessionHasNoErrors();
        $this->assertSame('Label Tab B', $komponen->fresh()->label);

        // Tab-A web dengan token lama → 302 + konflik, data Tab-B utuh.
        $this->actingAs($this->perencanaan)
            ->updateKomponen($this->indikator->id, $komponen->id, $payload('Label Tab A Usang', $tokenTabA))
            ->assertRedirect()
            ->assertSessionHasErrors(['konflik']);
        $this->assertSame('Label Tab B', $komponen->fresh()->label);

        // Tab-A JSON dengan token lama → 409 + konflik, tanpa mutasi.
        $staleJson = $this->actingAs($this->perencanaan)
            ->updateKomponenJson($this->indikator->id, $komponen->id, $payload('Label Tab A JSON', $tokenTabA));
        $staleJson->assertConflict()->assertJsonValidationErrors(['konflik']);
        $this->assertSame($pesanKonflik, $staleJson->json('errors.konflik.0'));
        $this->assertSame('Label Tab B', $komponen->fresh()->label);
        $this->assertSame(1, AuditLog::where('tindakan', 'komponen.ubah')->count());
    }

    public function test_delete_token_usang_ditolak_konflik_tanpa_mutasi(): void
    {
        $this->buatKomponen('t', 'penyebut', 3);
        $this->buatKomponen('p1', 'pembilang', 1);
        $target = $this->buatKomponen('p2', 'pembilang', 2);
        $tokenTabA = $this->tokenVersi();

        // Tab-B menghapus dengan token segar.
        $this->actingAs($this->perencanaan)
            ->deleteKomponen($this->indikator->id, $target->id, [
                'alasan' => 'Penghapusan komponen tab B yang sah.',
                'expected_updated_at' => $tokenTabA,
            ])
            ->assertRedirect("/indikator/{$this->indikator->id}/komponen")
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('indikator_komponen', ['id' => $target->id]);

        // Tab-A menghapus dengan token lama → konflik, baris tetap ada.
        $korban = IndikatorKomponen::where('indikator_id', $this->indikator->id)->where('kode', 'p1')->firstOrFail();
        $this->actingAs($this->perencanaan)
            ->deleteKomponen($this->indikator->id, $korban->id, [
                'alasan' => 'Penghapusan komponen tab A dengan token usang.',
                'expected_updated_at' => $tokenTabA,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['konflik']);
        $this->assertDatabaseHas('indikator_komponen', ['id' => $korban->id]);
        $this->assertSame(1, AuditLog::where('tindakan', 'komponen.hapus')->count());
    }

    public function test_tanpa_token_atau_format_tak_valid_ditolak_tanpa_mutasi(): void
    {
        $this->buatKomponen('t', 'penyebut', 2);
        $komponen = $this->buatKomponen('n', 'pembilang', 1);
        $auditCount = AuditLog::count();

        // Store tanpa token → 422 expected_updated_at.
        $this->actingAs($this->perencanaan)
            ->createKomponen($this->indikator->id, [
                'kode' => 'p_baru',
                'label' => 'Tanpa Token',
                'peran' => 'pembilang',
                'bobot' => '1.0',
                'urutan' => 3,
                'aktif' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['expected_updated_at']);

        // Store JSON tanpa token → 422 expected_updated_at.
        $this->actingAs($this->perencanaan)
            ->createKomponenJson($this->indikator->id, [
                'kode' => 'p_json',
                'label' => 'Tanpa Token JSON',
                'peran' => 'pembilang',
                'bobot' => '1.0',
                'urutan' => 3,
                'aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expected_updated_at']);

        // Store format tak-valid → 422 expected_updated_at.
        $this->actingAs($this->perencanaan)
            ->createKomponen($this->indikator->id, [
                'kode' => 'p_salah',
                'label' => 'Format Salah',
                'peran' => 'pembilang',
                'bobot' => '1.0',
                'urutan' => 3,
                'aktif' => true,
                'expected_updated_at' => 'bukan-tanggal',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['expected_updated_at']);

        // Update tanpa token → 422 expected_updated_at.
        $this->actingAs($this->perencanaan)
            ->updateKomponen($this->indikator->id, $komponen->id, [
                'kode' => 'n',
                'label' => 'Ubah Tanpa Token',
                'peran' => 'pembilang',
                'bobot' => '1.0',
                'urutan' => 1,
                'aktif' => true,
                'alasan' => 'Percobaan ubah tanpa token versi.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['expected_updated_at']);

        // Delete tanpa token → 422 expected_updated_at.
        $this->actingAs($this->perencanaan)
            ->deleteKomponen($this->indikator->id, $komponen->id, [
                'alasan' => 'Percobaan hapus tanpa token versi.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['expected_updated_at']);

        $this->assertSame('Komponen n', $komponen->fresh()->label);
        $this->assertDatabaseHas('indikator_komponen', ['id' => $komponen->id]);
        $this->assertDatabaseMissing('indikator_komponen', ['indikator_id' => $this->indikator->id, 'kode' => 'p_baru']);
        $this->assertSame($auditCount, AuditLog::count());
    }
}
