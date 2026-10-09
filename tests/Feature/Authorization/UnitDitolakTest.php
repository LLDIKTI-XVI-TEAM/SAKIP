<?php

namespace Tests\Feature\Authorization;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Deny ber-unit hanya menyembunyikan baris unit itu di daftar yang
 * memakai `PermissionResolver::unitDitolak`; unit lain tetap tampil.
 */
class UnitDitolakTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_unit_ditolak_hanya_deny_ber_unit_milik_pengguna_dan_izin(): void
    {
        $unitB = Unit::create(['nama' => 'Unit B', 'created_by' => $this->actor->id]);
        $lain = $this->userWithRole('superadmin');
        $this->deny($this->actor, 'pengukuran:read', $this->unit->id);
        $this->deny($this->actor, 'pengukuran:read', null);
        $this->deny($this->actor, 'dashboard:read', $unitB->id);
        $this->deny($lain, 'pengukuran:read', $unitB->id);

        $unit = app(PermissionResolver::class)->unitDitolak($this->actor, 'pengukuran:read')->pluck('unit_id')->all();

        $this->assertSame([(string) $this->unit->id], array_map('strval', $unit));
    }

    public function test_dashboard_menyaring_deny_unit_secara_presisi(): void
    {
        $unitB = $this->pengukuranUnitLain();

        $this->deny($this->actor, 'pengukuran:read', $unitB->id);
        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->where('stats.total', 2)
            ->where('pengukurans', fn ($rows) => collect($rows)->mapWithKeys(fn ($row) => [$row['unit']['nama'] => $row['action'] !== null])->sortKeys()->all() === ['Unit B' => false, 'Unit Pengujian' => true]));

        $this->deny($this->actor, 'dashboard:read', $this->unit->id);
        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->where('stats.total', 1)
            ->has('pengukurans', 1)
            ->where('pengukurans.0.unit.nama', 'Unit B'));
    }

    public function test_daftar_pengukuran_menyaring_deny_unit_secara_presisi(): void
    {
        $this->pengukuranUnitLain();

        $this->actingAs($this->actor)->get('/pengukuran')->assertOk()->assertInertia(fn ($page) => $page->has('pengukurans', 2));

        $this->deny($this->actor, 'pengukuran:read', $this->unit->id);
        $this->actingAs($this->actor)->get('/pengukuran')->assertOk()->assertInertia(fn ($page) => $page
            ->has('pengukurans', 1)
            ->whereNot('pengukurans.0.id', $this->pengukuran->id));
    }

    /**
     * Pengukuran kedua pada jadwal yang sama dengan konteks beku di unit B.
     */
    private function pengukuranUnitLain(): Unit
    {
        $unitB = Unit::create(['nama' => 'Unit B', 'created_by' => $this->actor->id]);
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $this->pengukuran->indikator->sasaran_strategis_id, 'unit_id' => $unitB->id, 'kode' => 'I-UJI-B', 'nama' => 'Indikator Unit B',
            'satuan' => 'poin', 'tipe_perhitungan' => 'manual', 'status' => 'aktif', 'tahun_mulai_berlaku' => 2025, 'created_by' => $this->actor->id, 'created_by_role' => 'superadmin']);
        $konteks = JadwalSnapshot::create(['jadwal_id' => $this->jadwal->id, 'indikator_id' => $indikator->id, 'periode_mulai_id' => $this->pengukuran->periode_id, 'unit_id' => $unitB->id,
            'nama' => 'Indikator Unit B', 'definisi' => 'Konteks unit B.', 'satuan' => 'poin', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 70]);
        PenugasanIndikator::create(['indikator_id' => $indikator->id, 'user_id' => $this->actor->id, 'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        PengukuranKinerja::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id, 'jadwal_snapshot_id' => $konteks->id, 'sumber_nilai' => 'manual', 'created_by' => $this->actor->id]);

        return $unitB;
    }

    private function deny(User $user, string $kode, ?string $unitId): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $kode)->value('id'),
            'unit_id' => $unitId, 'alasan' => 'Deny pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }
}
