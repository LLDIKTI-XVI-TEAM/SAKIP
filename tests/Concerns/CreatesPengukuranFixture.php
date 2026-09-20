<?php

namespace Tests\Concerns;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait CreatesPengukuranFixture
{
    protected User $actor;

    protected Unit $unit;

    protected PengukuranKinerja $pengukuran;

    protected JadwalTahunan $jadwal;

    protected JadwalSnapshot $context;

    protected RencanaAksi $plan;

    protected RencanaAksiVersi $planVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(9, 0));
        $this->seed(AccessCatalogSeeder::class);
        $this->actor = $this->userWithRole('superadmin');
        $this->unit = Unit::create(['nama' => 'Unit Pengujian', 'created_by' => $this->actor->id]);
        $renstra = Renstra::create(['kode' => 'R-UJI', 'nama' => 'Renstra Uji', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI', 'deskripsi' => 'Sasaran Uji']);
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $sasaran->id, 'unit_id' => $this->unit->id, 'kode' => 'I-UJI', 'nama' => 'Indikator Uji', 'satuan' => 'poin', 'tipe_perhitungan' => 'manual']);
        $pk = RenstraPk::create(['renstra_id' => $renstra->id, 'tahun' => 2026, 'nomor_pk' => 'PK-UJI', 'tanggal_pk' => '2026-01-01', 'created_by' => $this->actor->id]);
        $periode = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $this->jadwal = JadwalTahunan::create(['renstra_id' => $renstra->id, 'tahun' => 2026, 'renstra_pk_id' => $pk->id, 'penutupan' => '2026-12-31', 'status' => 'aktif', 'activated_at' => now()]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $periode->id, 'pengisian_mulai' => '2026-03-01', 'pengisian_selesai' => '2026-03-15', 'reviu_mulai' => '2026-03-15', 'reviu_selesai' => '2026-04-15']);
        $this->context = JadwalSnapshot::create(['jadwal_id' => $this->jadwal->id, 'indikator_id' => $indikator->id, 'periode_mulai_id' => $periode->id, 'unit_id' => $this->unit->id,
            'nama' => 'Indikator Uji', 'definisi' => 'Definisi operasional beku.', 'satuan' => 'poin', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 70]);
        PenugasanIndikator::create(['indikator_id' => $indikator->id, 'user_id' => $this->actor->id, 'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        // Prasyarat sah sintetis hanya fixture pengujian; runtime tidak membuat atau mengesahkan RA otomatis.
        $this->plan = RencanaAksi::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'unit_id' => $this->unit->id, 'jadwal_tahunan_id' => $this->jadwal->id, 'jadwal_snapshot_id' => $this->context->id,
            'penanggung_jawab_id' => $this->actor->id, 'created_by' => $this->actor->id, 'status_alur' => 'disahkan', 'disahkan_by' => $this->actor->id, 'disahkan_at' => now()]);
        $this->planVersion = RencanaAksiVersi::create(['rencana_aksi_id' => $this->plan->id, 'jadwal_snapshot_id' => $this->context->id, 'nomor' => 1, 'diajukan_by' => $this->actor->id, 'diajukan_at' => now(),
            'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'], 'snapshot' => ['target_periode' => [['periode_id' => $periode->id, 'nilai' => 70, 'status_perhitungan' => 'terhitung', 'komponen' => []]]],
            'disahkan_by' => $this->actor->id, 'disahkan_at' => now()]);
        $this->pengukuran = PengukuranKinerja::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'periode_id' => $periode->id, 'jadwal_snapshot_id' => $this->context->id, 'sumber_nilai' => 'manual', 'created_by' => $this->actor->id]);
    }

    protected function userWithRole(string $kode): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('kode', $kode)->firstOrFail();
        foreach (Permission::whereIn('kode', RolePermissionPresets::forRole($kode))->get() as $permission) {
            $role->permissions()->syncWithoutDetaching([$permission->id => ['id' => (string) Str::uuid(), 'created_at' => now()]]);
        }
        $user->roles()->attach($role->id, ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);

        return $user;
    }

    protected function grant(User $user, string $permission): void
    {
        DB::table('user_permission_granted')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $permission)->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Fixture pengujian', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
    }
}
