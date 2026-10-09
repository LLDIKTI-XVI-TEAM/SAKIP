<?php

namespace Tests\Concerns;

use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\JenisBerkas;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fixture bukti rencana aksi: jadwal aktif dengan jendela penyusunan yang
 * sedang berjalan, aktor superadmin sebagai PJ awal, dan helper untuk
 * membentuk PIC pegawai (grant unit + penugasan efektif).
 */
trait CreatesRencanaAksiFixture
{
    protected User $actor;

    protected Unit $unit;

    protected IndikatorKinerja $indikator;

    protected JadwalTahunan $jadwal;

    protected JadwalSnapshot $snapshot;

    protected RencanaAksi $rencanaAksi;

    protected function setUpRencanaAksiFixture(): void
    {
        $this->seed(AccessCatalogSeeder::class);

        $this->actor = $this->createUserWithRole('superadmin');
        $this->unit = Unit::create(['nama' => 'Unit Rencana Aksi', 'created_by' => $this->actor->id]);

        $renstra = Renstra::create([
            'kode' => 'R-RA',
            'nama' => 'Renstra Rencana Aksi',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
            'created_by' => $this->actor->id,
        ]);

        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'S-RA',
            'deskripsi' => 'Sasaran Strategis RA',
        ]);

        $this->indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $this->unit->id,
            'kode' => 'IKU-RA-01',
            'nama' => 'Indikator Kinerja RA',
            'satuan' => 'persen',
            'tipe_perhitungan' => 'manual',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->actor->id,
            'created_by_role' => 'superadmin',
        ]);

        $pk = RenstraPk::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'nomor_pk' => 'PK-RA-2026',
            'tanggal_pk' => '2026-01-02',
            'created_by' => $this->actor->id,
        ]);

        $periode = Periode::create([
            'nama' => 'Triwulan I',
            'urutan' => 1,
            'aktif' => true,
            'is_nilai_akhir' => false,
        ]);

        $hariIni = today(config('app.business_timezone'));
        $this->jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'renstra_pk_id' => $pk->id,
            'rencana_aksi_mulai' => $hariIni->copy()->subDay()->toDateString(),
            'rencana_aksi_selesai' => $hariIni->copy()->addDay()->toDateString(),
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);

        PeriodeJadwal::create([
            'jadwal_id' => $this->jadwal->id,
            'periode_id' => $periode->id,
            'pengisian_mulai' => '2026-01-01',
            'pengisian_selesai' => '2026-03-31',
            'reviu_mulai' => '2026-04-01',
            'reviu_selesai' => '2026-04-15',
        ]);

        $this->snapshot = JadwalSnapshot::create([
            'jadwal_id' => $this->jadwal->id,
            'indikator_id' => $this->indikator->id,
            'periode_mulai_id' => $periode->id,
            'unit_id' => $this->unit->id,
            'nama' => $this->indikator->nama,
            'definisi' => 'Definisi operasional beku.',
            'satuan' => 'persen',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 85,
        ]);

        PenugasanIndikator::create([
            'indikator_id' => $this->indikator->id,
            'user_id' => $this->actor->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        $this->rencanaAksi = RencanaAksi::create([
            'indikator_id' => $this->indikator->id,
            'tahun' => 2026,
            'unit_id' => $this->unit->id,
            'jadwal_tahunan_id' => $this->jadwal->id,
            'penanggung_jawab_id' => $this->actor->id,
            'uraian' => 'Rencana Aksi Pengujian Bukti Dukung',
            'status_alur' => RencanaAksi::STATUS_DRAFT,
            'versi' => 1,
            'created_by' => $this->actor->id,
        ]);
    }

    protected function createUserWithRole(string $kode): User
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

    /**
     * PIC operasional (Q32.2): pegawai + grant `rencana_aksi:update` pada unit
     * header + penugasan PJ yang efektif hari ini.
     */
    protected function createPicPegawai(?string $unitId = null): User
    {
        $pic = $this->createUserWithRole('pegawai');
        $this->grantUnitPermission($pic, 'rencana_aksi:update', $unitId);
        PenugasanIndikator::create([
            'indikator_id' => $this->indikator->id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => today(config('app.business_timezone'))->toDateString(),
            'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);

        return $pic;
    }

    protected function grantUnitPermission(User $user, string $permissionCode, ?string $unitId = null): void
    {
        $permId = Permission::where('kode', $permissionCode)->value('id');
        DB::table('user_permission_granted')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'permission_id' => $permId,
            'unit_id' => $unitId ?? $this->unit->id,
            'alasan' => 'Pengujian unit permission',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);
    }

    protected function denyPermission(User $user, string $permissionCode, ?string $unitId = null): void
    {
        $permId = Permission::where('kode', $permissionCode)->value('id');
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'permission_id' => $permId,
            'unit_id' => $unitId,
            'alasan' => 'Pengujian deny permission',
            'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createJenisBerkas(array $attributes = []): JenisBerkas
    {
        return JenisBerkas::create(array_merge([
            'nama' => 'Dokumen Kerangka Acuan Kerja',
            'tahap' => 'rencana_aksi',
            'indikator_id' => null,
            'wajib' => true,
            'semua_mode_wajib' => false,
            'izinkan_file' => true,
            'izinkan_tautan' => true,
            'izinkan_teks' => true,
            'aktif' => true,
            'urutan' => 1,
            'created_by' => $this->actor->id,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createBuktiDukung(array $attributes = []): BuktiDukung
    {
        return BuktiDukung::create(array_merge([
            'berkasable_type' => 'rencana_aksi',
            'berkasable_id' => $this->rencanaAksi->id,
            'mode' => 'tautan',
            'tautan' => 'https://contoh.lldikti16.kemdikbud.go.id/dokumen-ra.pdf',
            'uploaded_by' => $this->actor->id,
            'created_at' => now(),
        ], $attributes));
    }
}
