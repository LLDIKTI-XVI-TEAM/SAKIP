<?php

namespace Tests\Support;

use App\Actions\Jadwal\SaveJadwalDraft;
use App\Models\Berkas;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalTahunan;
use App\Models\Pengaturan;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\TargetKinerja;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Skenario sintetis jadwal 2026 yang siap diaktifkan; setiap test merusak satu prasyarat secara eksplisit. */
trait JadwalActivationFixtures
{
    use JadwalFixtures;

    /** @var array{owner: User, renstra: Renstra, periode: Periode, jadwal: JadwalTahunan, sasaran: SasaranStrategis, unit: Unit, pk: RenstraPk} */
    private array $ready;

    /** @return array{owner: User, renstra: Renstra, periode: Periode, jadwal: JadwalTahunan, sasaran: SasaranStrategis, unit: Unit, pk: RenstraPk} */
    private function readyJadwal(int $year = 2026, bool $withIndicator = true): array
    {
        $owner = $this->calendarActor(['jadwal:create', 'jadwal:update']);
        $renstra = Renstra::create(['kode' => 'REN-'.Str::random(8), 'nama' => 'Renstra Aktivasi', 'tahun_mulai' => $year - 1,
            'tahun_selesai' => $year + 3, 'status' => 'aktif', 'dasar_hukum' => 'Kepmen fixture', 'created_by' => $owner->id]);
        $periode = $this->calendarMaster();
        $jadwal = app(SaveJadwalDraft::class)->handle($owner, $this->calendarPayload($renstra, $periode, $year));
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'SS-'.Str::random(6), 'deskripsi' => 'Sasaran fixture']);
        $unit = Unit::create(['nama' => 'Unit fixture '.Str::random(8), 'status' => 'aktif', 'created_by' => $owner->id]);
        $pk = RenstraPk::create(['renstra_id' => $renstra->id, 'tahun' => $year, 'nomor_pk' => 'PK-'.Str::random(6), 'tanggal_pk' => "$year-01-02", 'created_by' => $owner->id]);
        $this->ready = compact('owner', 'renstra', 'periode', 'jadwal', 'sasaran', 'unit', 'pk');
        $this->pkAttachment();
        if ($withIndicator) {
            $this->eligibleIndicator(target: '76.25');
        }

        return $this->ready;
    }

    /** @param  list<array{kode: string, peran: string, bobot?: string}>  $komponen */
    private function eligibleIndicator(?string $target = '1', ?string $baseline = null, bool $withTarget = true, string $tipe = 'manual',
        array $komponen = [], string $status = 'aktif', int $mulai = 2026, string $nama = 'Indikator fixture'): IndikatorKinerja
    {
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $this->ready['sasaran']->id, 'unit_id' => $this->ready['unit']->id,
            'kode' => 'IKU-'.Str::random(6), 'nama' => $nama, 'definisi_operasional' => 'Definisi operasional fixture', 'satuan' => 'persen',
            'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => $tipe, 'status' => $status,
            'tahun_mulai_berlaku' => $mulai, 'created_by' => $this->ready['owner']->id, 'created_by_role' => 'perencanaan']);
        foreach ($komponen as $index => $row) {
            IndikatorKomponen::create(['indikator_id' => $indikator->id, 'kode' => $row['kode'], 'label' => 'Komponen '.$row['kode'],
                'peran' => $row['peran'], 'bobot' => $row['bobot'] ?? '1', 'urutan' => $index + 1, 'aktif' => true, 'created_by' => $this->ready['owner']->id]);
        }
        if ($withTarget) {
            TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => $this->ready['jadwal']->tahun, 'target_tahunan' => $target, 'baseline' => $baseline]);
        }

        return $indikator;
    }

    private function pkAttachment(string $mode = 'tautan'): Berkas
    {
        return Berkas::forceCreate(['id' => (string) Str::uuid(), 'berkasable_type' => 'renstra_pk', 'berkasable_id' => $this->ready['pk']->id,
            'mode' => $mode, 'tautan' => $mode === 'tautan' ? 'https://example.test/pk' : null, 'isi_teks' => $mode === 'teks' ? 'Isi fixture' : null,
            'uploaded_by' => $this->ready['owner']->id]);
    }

    private function uploadSetting(?string $value): void
    {
        Pengaturan::where('kunci', 'berkas.unggahan_aktif')->delete();
        if ($value !== null) {
            Pengaturan::forceCreate(['id' => (string) Str::uuid(), 'kunci' => 'berkas.unggahan_aktif', 'nilai' => $value, 'tipe' => 'boolean', 'grup' => 'berkas']);
        }
    }

    /** Grant global per pengguna; role perencanaan milik owner fixture tidak ikut diubah. */
    private function activator(array $permissions = ['jadwal:aktivasi']): User
    {
        $actor = User::factory()->create(['status' => 'aktif']);
        // Resolver mensyaratkan role resmi aktif; role pegawai tidak membawa izin jadwal.
        $actor->roles()->attach(Role::where('kode', 'pegawai')->value('id'), ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);
        foreach ($permissions as $permission) {
            DB::table('user_permission_granted')->insert(['id' => (string) Str::uuid(), 'user_id' => $actor->id,
                'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => null, 'alasan' => 'Fixture aktivasi',
                'diberikan_oleh' => $this->ready['owner']->id, 'created_at' => now()]);
        }

        return $actor;
    }

    private function denyPermission(User $actor, string $permission): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $actor->id,
            'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => null, 'alasan' => 'Fixture deny',
            'ditetapkan_oleh' => $this->ready['owner']->id, 'created_at' => now()]);
    }

    /** Sidik jari baris snapshot dan anaknya untuk membuktikan data lama tidak tersentuh. */
    private function snapshotFingerprint(): array
    {
        return [DB::table('jadwal_snapshot')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('jadwal_snapshot_komponen')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()];
    }

    /**
     * Sidik jari tanpa kolom `komposisi_final`. Aktivasi memang memfinalkan snapshot
     * terbit, sehingga konten beku yang wajib identik adalah seluruh
     * kolom selain flag itu.
     *
     * @param  array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}  $fingerprint
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function tanpaFlagFinalisasi(array $fingerprint): array
    {
        $strip = fn (array $rows): array => array_map(fn (array $row): array => array_diff_key($row, ['komposisi_final' => true]), $rows);

        return [$strip($fingerprint[0]), $strip($fingerprint[1])];
    }

    /** @return array<string, mixed> */
    private function activationPayload(array $overrides = []): array
    {
        return [...['expected_revisi' => $this->ready['jadwal']->fresh()->revisi, 'operation_id' => (string) Str::uuid(), 'alasan' => 'PK dan target tahunan sudah disahkan.'], ...$overrides];
    }
}
