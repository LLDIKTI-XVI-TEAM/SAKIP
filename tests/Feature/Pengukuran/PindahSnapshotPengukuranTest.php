<?php

namespace Tests\Feature\Pengukuran;

use App\Actions\Jadwal\BukaKembaliJadwal;
use App\Actions\Jadwal\TutupJadwal;
use App\Actions\Pengukuran\IndexPengukuran;
use App\Actions\Pengukuran\PresentPengukuran;
use App\Actions\Pengukuran\SubmitPengukuran;
use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\Kegiatan;
use App\Models\KinerjaSnapshot;
use App\Models\KlaimKegiatan;
use App\Models\PengukuranKinerja;
use App\Models\PengukuranKomponen;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\RiwayatPengukuran;
use App\Models\StatusCapaian;
use App\Models\Unit;
use App\Models\User;
use App\Policies\PengukuranKinerjaPolicy;
use App\Services\Authorization\PermissionResolver;
use App\Services\RencanaAksi\PeriodeTanpaTarget;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class PindahSnapshotPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private array $komponen = [];

    private Periode $tw2;

    /** Rantai terpisah agar snapshot manual bawaan yang sudah dijepit tidak dimutasi. */
    private function rantai(string $tipe = 'penjumlahan', array $peran = ['A' => 'penjumlah', 'B' => 'penjumlah'], bool $versiRa = true): void
    {
        $this->tw2 = Periode::firstOrCreate(['urutan' => 2], [
            'nama' => 'Triwulan II', 'aktif' => true, 'is_nilai_akhir' => false,
            'bulan_mulai' => 4, 'hari_mulai' => 1, 'bulan_selesai' => 6, 'hari_selesai' => 30,
        ]);
        PeriodeJadwal::firstOrCreate(['jadwal_id' => $this->jadwal->id, 'periode_id' => $this->tw2->id],
            ['pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-30']);
        $this->jadwal->update(['activated_at' => '2026-01-01 00:00:00']);
        $indikator = IndikatorKinerja::create([
            ...$this->pengukuran->indikator->only(['sasaran_strategis_id', 'unit_id', 'satuan', 'created_by_role']),
            'kode' => 'I-PINDAH-'.IndikatorKinerja::count(), 'nama' => 'Indikator pemindahan',
            'tipe_perhitungan' => $tipe, 'status' => 'aktif', 'tahun_mulai_berlaku' => 2026, 'created_by' => $this->actor->id,
        ]);
        $this->komponen = [];
        foreach (['A', 'B', 'C'] as $urutan => $kode) {
            $this->komponen[$kode] = IndikatorKomponen::create([
                'indikator_id' => $indikator->id, 'kode' => $kode, 'label' => 'Komponen '.$kode,
                'peran' => $peran[$kode] ?? 'penjumlah', 'bobot' => 1, 'urutan' => $urutan + 1,
                'aktif' => true, 'created_by' => $this->actor->id,
            ]);
        }
        $this->context = JadwalSnapshot::create([
            ...$this->context->only(['jadwal_id', 'periode_mulai_id', 'unit_id', 'definisi', 'satuan', 'arah']),
            'indikator_id' => $indikator->id, 'nama' => $indikator->nama, 'nomor_versi' => 1,
            'tipe_perhitungan' => $tipe, 'presisi' => 2, 'desimal_tampilan' => 2, 'target' => 70, 'komposisi_final' => false,
        ]);
        $this->isiDefinisi($this->context, $tipe === 'manual' ? [] : $peran);
        $this->plan = RencanaAksi::create([
            'indikator_id' => $indikator->id, 'tahun' => 2026, 'unit_id' => $this->unit->id,
            'jadwal_tahunan_id' => $this->jadwal->id, 'snapshot_draf_id' => $this->context->id,
            'penanggung_jawab_id' => $this->actor->id, 'created_by' => $this->actor->id,
            'status_alur' => 'disahkan', 'disahkan_by' => $this->actor->id, 'disahkan_at' => now(),
        ]);
        $target = $tipe === 'manual' ? 70 : ($tipe === 'penjumlahan' ? 8 : 60);
        $targetKomponen = $this->context->komponen->map(fn ($k) => [
            'komponen_id' => $k->komponen_id, 'nilai' => $k->kode === 'A' ? 3 : 5,
        ])->all();
        if ($versiRa) {
            $this->planVersion = RencanaAksiVersi::create([
                'rencana_aksi_id' => $this->plan->id, 'jadwal_snapshot_id' => $this->context->id, 'nomor' => 1,
                'diajukan_by' => $this->actor->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan',
                'dasar_izin_pengajuan' => ['fixture' => 'sintetis'], 'disahkan_by' => $this->actor->id, 'disahkan_at' => now(),
                'snapshot' => ['target_periode' => array_map(fn ($id) => [
                    'periode_id' => $id, 'nilai' => $target, 'status_perhitungan' => 'terhitung', 'komponen' => $targetKomponen,
                ], [$this->context->periode_mulai_id, $this->tw2->id])],
            ]);
        }
        $this->pengukuran = PengukuranKinerja::create([
            ...$this->pengukuran->only(['progres_kegiatan', 'kendala_masalah', 'strategi_tindak_lanjut', 'created_by']),
            'indikator_id' => $indikator->id, 'tahun' => 2026, 'periode_id' => $this->tw2->id,
            'jadwal_snapshot_id' => $this->context->id, 'sumber_nilai' => $tipe === 'manual' ? 'manual' : 'komponen',
            'nilai' => $tipe === 'manual' ? '87.46' : $target, 'status_perhitungan' => 'terhitung',
            'status_alur' => 'draft', 'versi' => 1, 'catatan' => 'Catatan yang dipertahankan.',
        ]);
        foreach ($targetKomponen as $nilai) {
            PengukuranKomponen::create([...$nilai, 'pengukuran_id' => $this->pengukuran->id,
                'updated_by' => $this->actor->id, 'updated_at' => now()]);
        }
    }

    private function isiDefinisi(JadwalSnapshot $snapshot, array $peran): void
    {
        foreach ($peran as $kode => $role) {
            $komponen = $this->komponen[$kode];
            JadwalSnapshotKomponen::create([
                'jadwal_snapshot_id' => $snapshot->id, 'komponen_id' => $komponen->id, 'kode' => $kode,
                'label' => $komponen->label, 'peran' => $role, 'bobot' => 1, 'urutan' => $komponen->urutan,
            ]);
        }
        $snapshot->update(['komposisi_final' => true]);
    }

    private function koreksi(array $atribut = [], ?array $peran = null, ?JadwalSnapshot $lama = null): JadwalSnapshot
    {
        $lama ??= $this->context;
        $snapshot = JadwalSnapshot::create([
            ...$lama->only(['jadwal_id', 'indikator_id', 'unit_id', 'nama', 'definisi', 'satuan', 'arah',
                'tipe_perhitungan', 'presisi', 'desimal_tampilan', 'target']),
            'nomor_versi' => $lama->nomor_versi + 1, 'menggantikan_id' => $lama->id, 'periode_mulai_id' => $this->tw2->id,
            'alasan_koreksi' => 'Koreksi fixture.', 'rujukan_koreksi' => 'Nota uji.', 'komposisi_final' => false, ...$atribut,
        ]);
        $this->isiDefinisi($snapshot, $peran ?? $lama->komponen->pluck('peran', 'kode')->all());

        return $snapshot;
    }

    private function payload(JadwalSnapshot $tujuan): array
    {
        return ['versi' => $this->pengukuran->fresh()->versi, 'expected_snapshot_id' => $tujuan->id,
            'expected_snapshot_versi' => $tujuan->nomor_versi];
    }

    private function pindah(JadwalSnapshot $tujuan, array $data = [], ?User $actor = null): TestResponse
    {
        return $this->actingAs($actor ?? $this->actor)->postJson('/pengukuran/'.$this->pengukuran->id.'/pindah-snapshot',
            [...$this->payload($tujuan), ...$data]);
    }

    private function versiPengajuan(string $status = 'dikembalikan'): KinerjaSnapshot
    {
        $versi = KinerjaSnapshot::create([
            'pengukuran_id' => $this->pengukuran->id, 'rencana_aksi_versi_id' => $this->planVersion->id,
            'jadwal_snapshot_id' => $this->context->id, 'nomor' => 1, 'diajukan_by' => $this->actor->id,
            'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'],
            'snapshot' => ['nilai' => $this->pengukuran->nilai, 'target' => 70, 'tanpa_target_periode' => false],
            'disahkan_by' => $status === 'disahkan' ? $this->actor->id : null,
            'disahkan_at' => $status === 'disahkan' ? now() : null,
        ]);
        $this->pengukuran->update(['status_alur' => $status]);

        return $versi;
    }

    private function dataTetap(): array
    {
        return [
            'narasi' => $this->pengukuran->fresh()->only([...PengukuranKinerja::NARASI, 'catatan']),
            'bukti' => DB::table('berkas')->where('berkasable_id', $this->pengukuran->id)->orderBy('id')->get()->all(),
            'klaim' => DB::table('klaim_kegiatan')->where('rencana_aksi_id', $this->plan->id)->orderBy('id')->get()->all(),
            'riwayat' => DB::table('riwayat_pengukurans')->where('pengukuran_kinerja_id', $this->pengukuran->id)->orderBy('id')->get()->all(),
            'versi' => DB::table('pengukuran_versi')->where('pengukuran_id', $this->pengukuran->id)->orderBy('id')->get()->all(),
            'capaian' => DB::table('status_capaian')->where('pengukuran_id', $this->pengukuran->id)->orderBy('id')->get()->all(),
        ];
    }

    private function jejakPendukung(): void
    {
        BuktiDukung::create(['berkasable_type' => 'pengukuran', 'berkasable_id' => $this->pengukuran->id,
            'mode' => 'teks', 'isi_teks' => 'Bukti pengukuran tersimpan.', 'uploaded_by' => $this->actor->id, 'created_at' => now()]);
        $kegiatan = Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->tw2->id,
            'nama' => 'Kegiatan uji', 'tujuan' => 'Tujuan kegiatan.', 'created_by' => $this->actor->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $this->plan->id, 'kegiatan_id' => $kegiatan->id,
            'komponen_id' => $this->komponen['B']->id, 'sumber_klaim' => 'pengukuran', 'pengukuran_id' => $this->pengukuran->id,
            'created_by' => $this->actor->id, 'created_at' => now()]);
        RiwayatPengukuran::create(['pengukuran_kinerja_id' => $this->pengukuran->id, 'user_id' => $this->actor->id,
            'status_dari' => 'diajukan', 'status_ke' => 'dikembalikan', 'catatan' => 'Riwayat pengembalian.']);
    }

    private function assertRekonsiliasi(JadwalSnapshot $tujuan, string $status, int $versi, array $sebelum): void
    {
        $header = $this->pengukuran->fresh();
        $this->assertSame([$tujuan->id, $status, $versi + 1, 'belum_diisi', null],
            [$header->jadwal_snapshot_id, $header->status_alur, $header->versi, $header->status_perhitungan, $header->nilai]);
        $nilai = $header->komponen()->pluck('nilai', 'komponen_id')->all();
        $this->assertCount(2, $nilai);
        $this->assertEquals(3, $nilai[$this->komponen['A']->id]);
        $this->assertArrayNotHasKey($this->komponen['B']->id, $nilai);
        $this->assertNull($nilai[$this->komponen['C']->id]);
        $this->assertEquals($sebelum, $this->dataTetap());
        $audit = AuditLog::where('tindakan', 'pengukuran.pindah_snapshot')->where('objek_id', $header->id)->sole();
        $this->assertSame('pengukuran:update', $audit->dasar_izin['permission']);
        $this->assertSame('allow', $audit->dasar_izin['reason']);
        $this->assertEquals($this->context->only(['id', 'nomor_versi', 'unit_id', 'tipe_perhitungan', 'presisi', 'target']),
            $audit->nilai_lama['jadwal_snapshot']);
        $this->assertEquals($tujuan->only(['id', 'nomor_versi', 'unit_id', 'tipe_perhitungan', 'presisi', 'target']),
            $audit->nilai_baru['jadwal_snapshot']);
        $this->assertEquals(5, collect($audit->nilai_lama['komponen'])->firstWhere('komponen_id', $this->komponen['B']->id)['nilai']);
    }

    public function test_draf_dipindahkan_dengan_rekonsiliasi_dan_alasan_baku_tanpa_mengubah_jejak(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi(['target' => 90], ['A' => 'penjumlah', 'C' => 'penjumlah']);
        $this->versiPengajuan();
        $this->pengukuran->update(['status_alur' => 'draft']);
        $this->jejakPendukung();
        $sebelum = $this->dataTetap();
        $versi = $this->pengukuran->fresh()->versi;

        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id.'/pindah-snapshot', $this->payload($tujuan))
            ->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.success', 'Pengukuran dipindahkan ke versi 2.');

        $this->assertRekonsiliasi($tujuan, 'draft', $versi, $sebelum);
        $this->assertSame('Memindahkan pengukuran ke versi snapshot 2.',
            AuditLog::where('tindakan', 'pengukuran.pindah_snapshot')->sole()->alasan);
    }

    public function test_pengukuran_dikembalikan_mempertahankan_status_dan_menyimpan_alasan_tersanitasi(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi([], ['A' => 'penjumlah', 'C' => 'penjumlah']);
        $this->versiPengajuan();
        $this->jejakPendukung();
        $sebelum = $this->dataTetap();
        $versi = $this->pengukuran->fresh()->versi;

        $this->pindah($tujuan, ['alasan' => '  Menyesuaikan definisi komponen.  '])->assertRedirect();

        $this->assertRekonsiliasi($tujuan, 'dikembalikan', $versi, $sebelum);
        $this->assertSame('Menyesuaikan definisi komponen.',
            AuditLog::where('tindakan', 'pengukuran.pindah_snapshot')->sole()->alasan);
    }

    public function test_hasil_buka_kembali_dapat_dipindahkan_tanpa_mengubah_status_capaian_versi_resmi(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi([], ['A' => 'penjumlah', 'C' => 'penjumlah']);
        $resmi = $this->versiPengajuan('disahkan');
        StatusCapaian::create(['pengukuran_id' => $this->pengukuran->id, 'pengukuran_versi_id' => $resmi->id,
            'status' => 'tercapai', 'sumber' => 'manual', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->actingAs($this->actor)->post('/verifikasi/'.$this->pengukuran->id.'/buka-kembali',
            ['versi' => 1, 'alasan' => 'Koreksi resmi.'])->assertSessionHasNoErrors();
        $sebelum = $this->dataTetap();
        $versi = $this->pengukuran->fresh()->versi;

        $this->pindah($tujuan)->assertRedirect();

        $this->assertRekonsiliasi($tujuan, 'dikembalikan', $versi, $sebelum);
        $this->assertCount(1, $sebelum['capaian']);
    }

    public function test_perubahan_penjumlahan_ke_rasio_mengosongkan_nilai_komponen_dengan_peran_baru(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi(['tipe_perhitungan' => 'rasio_persen'], ['A' => 'pembilang', 'B' => 'penyebut']);

        $this->pindah($tujuan)->assertRedirect();

        $this->assertSame([null, null], $this->pengukuran->komponen()->orderBy('komponen_id')->pluck('nilai')->all());
        $this->assertSame('belum_diisi', $this->pengukuran->fresh()->status_perhitungan);
        $this->assertNull($this->pengukuran->fresh()->nilai);
    }

    public function test_pertukaran_peran_rasio_dengan_id_tetap_mengosongkan_kedua_nilai(): void
    {
        $this->rantai('rasio_persen', ['A' => 'pembilang', 'B' => 'penyebut']);
        $tujuan = $this->koreksi([], ['A' => 'penyebut', 'B' => 'pembilang']);

        $this->pindah($tujuan)->assertRedirect();

        $this->assertSame([null, null], $this->pengukuran->komponen()->orderBy('komponen_id')->pluck('nilai')->all());
        $this->assertSame('belum_diisi', $this->pengukuran->fresh()->status_perhitungan);
    }

    public function test_hasil_hitung_komponen_tidak_dibawa_sebagai_nilai_manual(): void
    {
        $this->rantai();
        $this->assertEquals(8, $this->pengukuran->nilai);
        $tujuan = $this->koreksi(['tipe_perhitungan' => 'manual'], []);

        $this->pindah($tujuan)->assertRedirect();

        $header = $this->pengukuran->fresh();
        $this->assertSame(['manual', 'belum_diisi', null], [$header->sumber_nilai, $header->status_perhitungan, $header->nilai]);
        $this->assertSame(0, $header->komponen()->count());
    }

    public function test_nilai_manual_dibulatkan_ulang_saat_presisi_turun(): void
    {
        $this->rantai('manual', []);
        $tujuan = $this->koreksi(['presisi' => 1]);

        $this->pindah($tujuan)->assertRedirect();

        $this->assertEquals(87.5, $this->pengukuran->fresh()->nilai);
        $this->assertSame('terhitung', $this->pengukuran->fresh()->status_perhitungan);
    }

    private function belumDapatDiukur(): void
    {
        $this->pengukuran->update(['nilai' => null, 'status_perhitungan' => 'belum_dapat_diukur']);
        $this->pengukuran->komponen()->update(['nilai' => null]);
    }

    public function test_belum_dapat_diukur_tetap_saat_tipe_tidak_berubah(): void
    {
        $this->rantai();
        $this->belumDapatDiukur();
        $tujuan = $this->koreksi();

        $this->pindah($tujuan)->assertRedirect();

        $this->assertSame('belum_dapat_diukur', $this->pengukuran->fresh()->status_perhitungan);
        $this->assertNull($this->pengukuran->fresh()->nilai);
    }

    public function test_belum_dapat_diukur_tetap_saat_tipe_berubah(): void
    {
        $this->rantai();
        $this->belumDapatDiukur();
        $tujuan = $this->koreksi(['tipe_perhitungan' => 'manual'], []);

        $this->pindah($tujuan)->assertRedirect();

        $this->assertSame('belum_dapat_diukur', $this->pengukuran->fresh()->status_perhitungan);
        $this->assertNull($this->pengukuran->fresh()->nilai);
        $this->assertSame('manual', $this->pengukuran->fresh()->sumber_nilai);
    }

    public function test_status_diajukan_diverifikasi_dan_disahkan_ditolak_tanpa_mutasi(): void
    {
        foreach (['diajukan', 'diverifikasi', 'disahkan'] as $status) {
            $this->rantai();
            $tujuan = $this->koreksi();
            $this->versiPengajuan($status);
            $sebelum = DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->sole();
            $jejak = $this->dataTetap();

            $this->pindah($tujuan)->assertUnprocessable()->assertJsonValidationErrors('pindah_snapshot')
                ->assertJsonPath('errors.pindah_snapshot.0', 'Status pengukuran tidak sesuai untuk tindakan ini.');

            $this->assertEquals($sebelum, DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->sole());
            $this->assertEquals($jejak, $this->dataTetap());
            $audit = AuditLog::where('tindakan', 'pengukuran.ditolak')->where('objek_id', $this->pengukuran->id)->sole();
            $this->assertSame('pindah_snapshot', $audit->nilai_baru['tindakan_diminta']);
            $this->assertSame('validasi_bisnis', $audit->nilai_baru['jenis_penolakan']);
        }
        $this->assertSame(0, AuditLog::where('tindakan', 'pengukuran.pindah_snapshot')->count());
    }

    private function pic(): User
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        return $pic;
    }

    public function test_pic_dengan_grant_dan_penugasan_tetap_ditolak(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi();
        $pic = $this->pic();

        $this->pindah($tujuan, actor: $pic)->assertForbidden();

        $audit = AuditLog::where('tindakan', 'pengukuran.ditolak')->sole();
        $this->assertSame([], $audit->dasar_izin['roles']);
        $this->assertSame('otorisasi', $audit->nilai_baru['jenis_penolakan']);
        $this->assertSame('pindah_snapshot', $audit->nilai_baru['tindakan_diminta']);
        $this->assertSame($this->context->id, $this->pengukuran->fresh()->jadwal_snapshot_id);
    }

    private function unitTujuan(): Unit
    {
        return Unit::create(['nama' => 'Unit tujuan', 'created_by' => $this->actor->id]);
    }

    public function test_deny_unit_tujuan_menang_dan_dasar_penolakan_dicatat(): void
    {
        $this->rantai();
        $unit = $this->unitTujuan();
        $tujuan = $this->koreksi(['unit_id' => $unit->id]);
        $this->plan->update(['unit_id' => $unit->id]);
        $deny = (string) Str::uuid();
        DB::table('user_permission_denied')->insert(['id' => $deny, 'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', 'pengukuran:update')->value('id'), 'unit_id' => $unit->id,
            'alasan' => 'Pembatasan fixture.', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        $this->pindah($tujuan)->assertForbidden();

        $audit = AuditLog::where('tindakan', 'pengukuran.ditolak')->sole();
        $this->assertSame('explicit_deny', $audit->dasar_izin['reason']);
        $this->assertSame([$deny], $audit->dasar_izin['denies']);
        $this->assertSame('pindah_snapshot', $audit->nilai_baru['tindakan_diminta']);
        $this->assertSame($this->context->id, $this->pengukuran->fresh()->jadwal_snapshot_id);
    }

    public function test_lintas_unit_mencatat_dasar_izin_tujuan_dan_dasar_unit_lama_secara_terpisah(): void
    {
        $this->rantai();
        $unit = $this->unitTujuan();
        $tujuan = $this->koreksi(['unit_id' => $unit->id]);
        $this->plan->update(['unit_id' => $unit->id]);
        $perencana = $this->userWithRole('perencanaan');
        $grant = (string) Str::uuid();
        DB::table('user_permission_granted')->insert(['id' => $grant, 'user_id' => $perencana->id,
            'permission_id' => Permission::where('kode', 'pengukuran:update')->value('id'), 'unit_id' => $unit->id,
            'alasan' => 'Pembeda dasar izin fixture.', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        $resolver = app(PermissionResolver::class);
        $lama = $resolver->decide($perencana, 'pengukuran:update', $this->unit->id);
        $baru = $resolver->decide($perencana, 'pengukuran:update', $unit->id);
        $this->assertNotEquals($lama, $baru);

        $this->pindah($tujuan, actor: $perencana)->assertRedirect();

        $audit = AuditLog::where('tindakan', 'pengukuran.pindah_snapshot')->sole();
        $this->assertEquals($baru, $audit->dasar_izin);
        $this->assertEquals($lama, $audit->nilai_baru['dasar_izin_unit_lama']);
        $this->assertSame([$grant], $audit->dasar_izin['grants']);
        $this->assertSame($unit->id, $this->pengukuran->fresh()->targetUnitId());
    }

    public function test_unit_rencana_aksi_yang_berbeda_ditolak_dan_diaudit(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi(['unit_id' => $this->unitTujuan()->id]);

        $this->pindah($tujuan)->assertUnprocessable()->assertJsonPath('errors.pindah_snapshot.0',
            'Unit pada versi terbaru berbeda dengan unit Rencana Aksi tahun ini.');

        $audit = AuditLog::where('tindakan', 'pengukuran.ditolak')->sole();
        $this->assertSame('pindah_snapshot', $audit->nilai_baru['tindakan_diminta']);
        $this->assertSame($this->context->id, $this->pengukuran->fresh()->jadwal_snapshot_id);
    }

    public function test_unit_tujuan_nonaktif_ditolak_sebagai_aturan_bisnis(): void
    {
        $this->rantai();
        $unit = $this->unitTujuan();
        $tujuan = $this->koreksi(['unit_id' => $unit->id]);
        $this->plan->update(['unit_id' => $unit->id]);
        $unit->update(['status' => 'nonaktif']);

        $this->pindah($tujuan)->assertUnprocessable()->assertJsonPath('errors.pindah_snapshot.0',
            'Unit organisasi pengukuran berstatus nonaktif.');

        $this->assertSame($this->context->id, $this->pengukuran->fresh()->jadwal_snapshot_id);
    }

    public function test_unit_lama_nonaktif_tetap_dapat_dipindahkan_ke_unit_tujuan_aktif(): void
    {
        $this->rantai();
        $unit = $this->unitTujuan();
        $tujuan = $this->koreksi(['unit_id' => $unit->id]);
        $this->plan->update(['unit_id' => $unit->id]);
        $this->unit->update(['status' => 'nonaktif']);

        $this->pindah($tujuan)->assertRedirect();

        $this->assertSame($tujuan->id, $this->pengukuran->fresh()->jadwal_snapshot_id);
        $this->assertSame($unit->id, $this->pengukuran->fresh()->targetUnitId());
    }

    public function test_koreksi_mulai_tw2_tidak_dapat_dipakai_untuk_memindahkan_tw1(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi();
        $this->pengukuran->update(['periode_id' => $this->context->periode_mulai_id]);

        $this->pindah($this->context)->assertUnprocessable()->assertJsonPath('errors.pindah_snapshot.0',
            'Tidak ada versi yang lebih baru untuk periode pengukuran ini.');

        $this->assertNotSame($tujuan->id, $this->pengukuran->fresh()->jadwal_snapshot_id);
    }

    public function test_field_tujuan_liar_dan_alasan_terlalu_panjang_ditolak_form_request(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi();
        foreach (['jadwal_snapshot_id' => $tujuan->id, 'status_alur' => 'disahkan',
            'komponen' => [['komponen_id' => $this->komponen['A']->id, 'nilai' => 99]], 'nilai' => 99,
            'alasan' => str_repeat('a', 1001)] as $field => $value) {
            $response = $this->pindah($tujuan, [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
            if ($field === 'alasan') {
                $response->assertJsonPath('errors.alasan.0', 'Alasan maksimal 1000 karakter.');
            }
        }
        $this->assertSame(5, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
        $this->assertSame($this->context->id, $this->pengukuran->fresh()->jadwal_snapshot_id);
        $this->assertSame(1, $this->pengukuran->fresh()->versi);
    }

    public function test_token_header_usang_ditolak_409_sebelum_guard_tidak_ada_versi_baru(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi();
        $payload = $this->payload($tujuan);
        $this->pindah($tujuan)->assertRedirect();

        $this->pindah($tujuan, $payload)->assertStatus(409)->assertJsonPath('errors.versi.0',
            'Data telah berubah. Muat ulang sebelum mengulangi tindakan.');

        $this->assertSame(2, $this->pengukuran->fresh()->versi);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.pindah_snapshot')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    public function test_versi_tujuan_baru_setelah_halaman_dibuka_menghasilkan_409(): void
    {
        $this->rantai();
        $v2 = $this->koreksi();
        $this->koreksi(['target' => 80], lama: $v2);

        $this->pindah($v2)->assertStatus(409)->assertJsonPath('errors.expected_snapshot_id.0',
            'Versi terbaru indikator berubah. Muat ulang halaman sebelum memindahkan.');

        $this->assertSame($this->context->id, $this->pengukuran->fresh()->jadwal_snapshot_id);
        $this->assertSame(1, $this->pengukuran->fresh()->versi);
    }

    public function test_uuid_huruf_besar_dinormalisasi_dan_id_tak_dikenal_tidak_membuat_audit(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi();
        $this->actingAs($this->actor)->postJson('/pengukuran/'.strtoupper($this->pengukuran->id).'/pindah-snapshot',
            [...$this->payload($tujuan), 'expected_snapshot_id' => strtoupper($tujuan->id)])->assertRedirect();
        $this->actingAs($this->actor)->postJson('/pengukuran/'.Str::uuid().'/pindah-snapshot', $this->payload($tujuan))->assertNotFound();

        $this->assertSame($tujuan->id, $this->pengukuran->fresh()->jadwal_snapshot_id);
        $this->assertSame(0, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    private function tutupTahun(): void
    {
        $this->travelTo(Carbon::parse('2027-01-05 09:00:00', 'Asia/Makassar'));
        app(TutupJadwal::class)->handle($this->actor, $this->jadwal->id,
            ['expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => 'Tahun ditutup.']);
    }

    private function bukaSesi(string $mulai = '2027-01-05T08:00', ?string $periodeId = null): void
    {
        app(BukaKembaliJadwal::class)->handle($this->actor, $this->jadwal->id, [
            'expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => 'Koreksi pengukuran.',
            'koreksi_mulai' => $mulai, 'koreksi_sampai' => '2027-01-08T17:00',
            'lingkup_koreksi' => ['jenis_objek' => ['pengukuran'], 'indikator_ids' => [$this->pengukuran->indikator_id],
                'periode_ids' => [$periodeId ?? $this->tw2->id]],
        ]);
    }

    public function test_tahun_ditutup_tanpa_sesi_menolak_pemindahan(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi();
        $this->tutupTahun();

        $this->pindah($tujuan)->assertUnprocessable()->assertJsonValidationErrors('pindah_snapshot');

        $this->assertSame($this->context->id, $this->pengukuran->fresh()->jadwal_snapshot_id);
    }

    public function test_sesi_belum_mulai_kedaluwarsa_atau_salah_lingkup_menolak_pemindahan(): void
    {
        foreach (['belum_mulai', 'kedaluwarsa', 'salah_lingkup'] as $kasus) {
            $this->rantai();
            $tujuan = $this->koreksi();
            $this->tutupTahun();
            $this->bukaSesi($kasus === 'belum_mulai' ? '2027-01-06T08:00' : '2027-01-05T08:00',
                $kasus === 'salah_lingkup' ? $this->context->periode_mulai_id : null);
            if ($kasus === 'kedaluwarsa') {
                $this->travelTo(Carbon::parse('2027-01-09 09:00:00', 'Asia/Makassar'));
            }

            $this->pindah($tujuan)->assertUnprocessable()->assertJsonPath('errors.pindah_snapshot.0',
                'Tahun sudah ditutup; diperlukan sesi koreksi resmi yang mencakup pengukuran ini.');

            $this->assertSame($this->context->id, $this->pengukuran->fresh()->jadwal_snapshot_id, $kasus);
        }
    }

    public function test_sesi_aktif_dalam_lingkup_membolehkan_pemindahan_perencanaan(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi();
        $this->tutupTahun();
        $this->bukaSesi();

        $this->pindah($tujuan)->assertRedirect();

        $this->assertSame($tujuan->id, $this->pengukuran->fresh()->jadwal_snapshot_id);
    }

    public function test_pic_dengan_jendela_koreksi_resmi_tetap_tidak_boleh_memindahkan(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi();
        $pic = $this->pic();
        $this->tutupTahun();
        $this->bukaSesi();
        $this->actingAs($this->actor)->post('/jadwal/'.$this->jadwal->id.'/revisi-jendela', [
            'expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => 'Membuka jendela PIC koreksi.',
            'periode' => [
                ['periode_id' => $this->context->periode_mulai_id, 'pengisian_mulai' => '2026-03-01', 'pengisian_selesai' => '2026-03-15'],
                ['periode_id' => $this->tw2->id, 'pengisian_mulai' => '2027-01-05', 'pengisian_selesai' => '2027-01-08'],
            ],
        ])->assertSessionHasNoErrors();
        $this->actingAs($pic)->post('/pengukuran/'.$this->pengukuran->id, [
            'versi' => 1, 'action' => 'draft', 'komponen' => $this->inputKomponen(['A' => 3, 'B' => 5]),
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->pengukuran->fresh()->versi);

        $this->pindah($tujuan, actor: $pic)->assertForbidden();

        $this->assertSame($this->context->id, $this->pengukuran->fresh()->jadwal_snapshot_id);
    }

    private function barisPengukuran(?User $actor = null): array
    {
        $actor ??= $this->actor;
        $query = $actor->id === $this->actor->id
            ? ['jadwal_id' => $this->jadwal->id, 'periode_id' => $this->pengukuran->fresh()->periode_id] : [];
        $props = app(IndexPengukuran::class)->handle($actor, $query);

        return collect($props['pengukurans'])->firstWhere('pengukuran.id', $this->pengukuran->id);
    }

    public function test_props_versi_berlaku_dan_capability_perencanaan_atau_pic_pada_daftar_dan_detail(): void
    {
        $this->rantai();
        $this->travelTo(Carbon::parse('2026-06-15 09:00:00', 'Asia/Makassar'));
        $tujuan = $this->koreksi();
        $pic = $this->pic();
        foreach (['draft', 'dikembalikan'] as $status) {
            $this->pengukuran->update(['status_alur' => $status]);
            foreach ([$this->actor, $pic] as $actor) {
                $baris = $this->barisPengukuran($actor);
                $detail = app(PresentPengukuran::class)->handle($this->pengukuran->fresh(), $actor, detail: true);
                $expected = ['id' => $tujuan->id, 'nomor' => 2];
                $this->assertSame($expected, $baris['versi_berlaku']);
                $this->assertSame($expected, $detail['versi_berlaku']);
                $this->assertSame($actor->id === $this->actor->id, $baris['can']['pindahSnapshot']);
                $this->assertSame($actor->id === $this->actor->id, $detail['can']['pindahSnapshot']);
                $this->assertSame(1, $baris['pengukuran']['versi']);
            }
        }
    }

    public function test_props_tanpa_label_setelah_dipindahkan_dan_sesudah_diajukan(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi();
        $this->pindah($tujuan)->assertRedirect();
        $baris = $this->barisPengukuran();
        $detail = app(PresentPengukuran::class)->handle($this->pengukuran->fresh(), $this->actor, detail: true);
        $this->assertNull($baris['versi_berlaku']);
        $this->assertFalse($baris['can']['pindahSnapshot']);
        $this->assertNull($detail['versi_berlaku']);
        $this->assertFalse($detail['can']['pindahSnapshot']);

        $this->rantai();
        app(SubmitPengukuran::class)->handle($this->actor, $this->pengukuran->id,
            ['versi' => 1, 'komponen' => $this->inputKomponen(['A' => 3, 'B' => 5])]);
        $this->koreksi();
        $baris = $this->barisPengukuran();
        $detail = app(PresentPengukuran::class)->handle($this->pengukuran->fresh(), $this->actor, detail: true);
        $this->assertNull($baris['versi_berlaku']);
        $this->assertFalse($baris['can']['pindahSnapshot']);
        $this->assertNull($detail['versi_berlaku']);
        $this->assertFalse($detail['can']['pindahSnapshot']);
    }

    public function test_baris_sebelum_periode_efektif_tidak_diberi_label_versi_lama(): void
    {
        $this->rantai('manual', []);
        $tw1 = $this->context->periode_mulai_id;
        $indikator = IndikatorKinerja::create([
            ...$this->pengukuran->indikator->only(['sasaran_strategis_id', 'unit_id', 'nama', 'satuan', 'tipe_perhitungan', 'created_by_role']),
            'kode' => 'I-MULAI-TW2', 'status' => 'aktif', 'tahun_mulai_berlaku' => 2026, 'created_by' => $this->actor->id,
        ]);
        $this->context = JadwalSnapshot::create([
            ...$this->context->only(['jadwal_id', 'unit_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'indikator_id' => $indikator->id, 'periode_mulai_id' => $this->tw2->id, 'nomor_versi' => 1,
        ]);
        $v2 = $this->koreksi();
        $this->pengukuran = PengukuranKinerja::create(['indikator_id' => $indikator->id, 'tahun' => 2026,
            'periode_id' => $tw1, 'jadwal_snapshot_id' => $v2->id, 'sumber_nilai' => 'manual', 'created_by' => $this->actor->id]);

        $baris = $this->barisPengukuran();

        $this->assertNull($baris['versi_berlaku']);
        $this->assertFalse($baris['can']['pindahSnapshot']);
    }

    public function test_policy_memeriksa_salinan_tanpa_mengubah_header_dan_relasi_aslinya(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi();
        $header = $this->pengukuran->fresh(['jadwalSnapshot.jadwal']);
        $snapshot = $header->jadwalSnapshot;
        $jadwal = $snapshot->jadwal;
        $sebelum = $header->getAttributes();
        $sebelumSnapshot = $snapshot->getAttributes();
        $sebelumJadwal = $jadwal->getAttributes();

        $this->assertTrue(Gate::forUser($this->actor)->allows('pindahSnapshot', $header));

        $this->assertSame($sebelum, $header->getAttributes());
        $this->assertSame($snapshot, $header->jadwalSnapshot);
        $this->assertSame($sebelumSnapshot, $snapshot->getAttributes());
        $this->assertSame($jadwal, $snapshot->jadwal);
        $this->assertSame($sebelumJadwal, $jadwal->getAttributes());
        $this->assertFalse($header->isDirty());
        $policy = app(PengukuranKinerjaPolicy::class);
        foreach ([null, $snapshot] as $tidakBaru) {
            $this->assertSame(['Tidak ada versi yang lebih baru untuk periode pengukuran ini.'],
                $policy->pindahSnapshotErrors($this->actor, $header, $tidakBaru));
        }
        $header->jadwal_snapshot_id = $tujuan->id;
        $header->setRelation('jadwalSnapshot', $tujuan);
        $this->assertSame(['Tidak ada versi yang lebih baru untuk periode pengukuran ini.'],
            $policy->pindahSnapshotErrors($this->actor, $header, $snapshot));
    }

    private function versiRencanaAksi(JadwalSnapshot $snapshot, int $nomor, array $targetPeriode, bool $sah = true): RencanaAksiVersi
    {
        return RencanaAksiVersi::create(['rencana_aksi_id' => $this->plan->id, 'jadwal_snapshot_id' => $snapshot->id,
            'nomor' => $nomor, 'diajukan_by' => $this->actor->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan',
            'dasar_izin_pengajuan' => ['fixture' => 'sintetis'], 'snapshot' => ['target_periode' => $targetPeriode],
            'disahkan_by' => $sah ? $this->actor->id : null, 'disahkan_at' => $sah ? now() : null]);
    }

    /** Ubah peta kode komponen → nilai menjadi baris `komponen` yang dikirim ke form/Action. */
    private function inputKomponen(array $nilai): array
    {
        return array_map(fn (string $kode, $value): array => ['komponen_id' => $this->komponen[$kode]->id, 'nilai' => $value],
            array_keys($nilai), $nilai);
    }

    /** Target sintetis penjumlahan tetap lengkap dan konsisten dengan nilai komponennya. */
    private function targetKomponen(string $periodeId, array $nilai): array
    {
        return ['periode_id' => $periodeId, 'nilai' => array_sum($nilai), 'status_perhitungan' => 'terhitung',
            'komponen' => $this->inputKomponen($nilai)];
    }

    private function ajukan(array $nilai = ['A' => 3, 'B' => 5]): TestResponse
    {
        return $this->actingAs($this->actor)->postJson('/pengukuran/'.$this->pengukuran->id,
            ['action' => 'ajukan', 'versi' => $this->pengukuran->fresh()->versi, 'komponen' => $this->inputKomponen($nilai)]);
    }

    private function klaimKomponenC(string $periodeId): KlaimKegiatan
    {
        $kegiatan = Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $periodeId,
            'nama' => 'Kegiatan komponen baru', 'tujuan' => 'Tujuan kegiatan.', 'created_by' => $this->actor->id]);

        return KlaimKegiatan::create(['rencana_aksi_id' => $this->plan->id, 'kegiatan_id' => $kegiatan->id,
            'komponen_id' => $this->komponen['C']->id, 'sumber_klaim' => 'rencana_aksi',
            'created_by' => $this->actor->id, 'created_at' => now()]);
    }

    public function test_pemindahan_menunggu_ra_sah_bersnapshot_tujuan_sebelum_pengajuan(): void
    {
        $this->rantai();
        $tujuan = $this->koreksi([], ['A' => 'penjumlah', 'C' => 'penjumlah']);
        $this->pindah($tujuan)->assertRedirect();

        $this->ajukan(['A' => 3, 'C' => 7])->assertUnprocessable()->assertJsonPath('errors.pengajuan.0',
            'Belum ada versi rencana aksi sah untuk snapshot pengukuran ini.');
        $this->assertSame(0, $this->pengukuran->versions()->count());
        $versi = $this->versiRencanaAksi($tujuan, 2, [$this->targetKomponen($this->tw2->id, ['A' => 20, 'C' => 7])]);

        $this->ajukan(['A' => 3, 'C' => 7])->assertRedirect();

        $beku = $this->pengukuran->fresh()->latestVersion;
        $this->assertSame($versi->id, $beku->rencana_aksi_versi_id);
        $this->assertSame($tujuan->id, $beku->jadwal_snapshot_id);
        $nilai = array_column($beku->snapshot['komponen'], 'nilai', 'komponen_id');
        $this->assertEquals(7, $nilai[$this->komponen['C']->id] ?? null);
        $this->assertArrayNotHasKey($this->komponen['B']->id, $nilai);
    }

    public function test_pengukuran_dikembalikan_diajukan_ulang_dengan_komponen_baru_tanpa_mengubah_versi_lama(): void
    {
        $this->rantai();
        $lama = $this->versiPengajuan();
        $tujuan = $this->koreksi([], ['A' => 'penjumlah', 'C' => 'penjumlah']);
        $ra2 = $this->versiRencanaAksi($tujuan, 2, [$this->targetKomponen($this->tw2->id, ['A' => 20, 'C' => 7])]);
        $this->pindah($tujuan)->assertRedirect();

        $this->ajukan(['A' => 3, 'C' => 7])->assertRedirect();

        $this->assertSame($this->context->id, DB::table('pengukuran_versi')->where('id', $lama->id)->value('jadwal_snapshot_id'));
        $this->assertSame(2, $this->pengukuran->versions()->count());
        $baru = $this->pengukuran->versions()->where('nomor', 2)->sole();
        $this->assertSame($tujuan->id, $baru->jadwal_snapshot_id);
        $this->assertSame($ra2->id, $baru->rencana_aksi_versi_id);
        $nilai = array_column($baru->snapshot['komponen'], 'nilai', 'komponen_id');
        $this->assertEquals(7, $nilai[$this->komponen['C']->id] ?? null);
        $this->assertArrayNotHasKey($this->komponen['B']->id, $nilai);
    }

    public function test_tw1_memakai_ra_sah_terbesar_untuk_snapshot_lama_dan_mengabaikan_klaim_periode_lain(): void
    {
        $this->rantai();
        $tw1 = $this->context->periode_mulai_id;
        $ra2 = $this->versiRencanaAksi($this->context, 2, [
            $this->targetKomponen($tw1, ['A' => 20, 'B' => 5]),
            $this->targetKomponen($this->tw2->id, ['A' => 30, 'B' => 5]),
        ]);
        $v2 = $this->koreksi([], ['A' => 'penjumlah', 'C' => 'penjumlah']);
        $kontrolTw2 = $this->ajukan();
        $this->versiRencanaAksi($this->context, 3, [
            $this->targetKomponen($tw1, ['A' => 99, 'B' => 5]),
            $this->targetKomponen($this->tw2->id, ['A' => 99, 'B' => 5]),
        ], sah: false);
        $this->versiRencanaAksi($v2, 4, [$this->targetKomponen($this->tw2->id, ['A' => 20, 'C' => 7])]);
        $klaim = $this->klaimKomponenC($this->tw2->id);
        $this->pengukuran = PengukuranKinerja::create([
            ...$this->pengukuran->only(['indikator_id', 'tahun', 'jadwal_snapshot_id', 'sumber_nilai',
                'progres_kegiatan', 'kendala_masalah', 'strategi_tindak_lanjut', 'created_by']),
            'periode_id' => $tw1, 'status_alur' => 'dikembalikan',
        ]);
        $presenter = app(PresentPengukuran::class);
        $ringkasan = $this->pengukuran->fresh();
        $presenter->prepareSummary(new EloquentCollection([$ringkasan]));
        $targetRingkasan = $presenter->handle($ringkasan, $this->actor)['target'];
        $targetDaftar = $this->barisPengukuran()['pengukuran']['target'];

        $this->ajukan()->assertRedirect();

        $kontrolTw2->assertUnprocessable()->assertJsonPath('errors.pengajuan.0',
            'Pengukuran ini memakai versi indikator lama. Tim Perencanaan perlu memindahkannya ke versi terbaru sebelum diajukan.');
        $this->assertEquals(25, $targetRingkasan);
        $this->assertEquals(25, $targetDaftar);
        $this->assertSame(1, $this->pengukuran->versions()->count());
        $beku = DB::table('pengukuran_versi')->where('pengukuran_id', $this->pengukuran->id)->sole();
        $payload = json_decode($beku->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($ra2->id, $beku->rencana_aksi_versi_id);
        $this->assertSame($this->context->id, $beku->jadwal_snapshot_id);
        $this->assertEquals(25, $payload['target']);
        $this->assertFalse($payload['tanpa_target_periode']);
        $this->assertSame([], $payload['klaim']);
        $this->assertTrue(KlaimKegiatan::whereKey($klaim->id)->exists());
    }

    public function test_tw2_dengan_versi_lama_ditolak_sebelum_ra_koreksi_disahkan(): void
    {
        $this->rantai();
        $this->versiRencanaAksi($this->context, 2, [
            $this->targetKomponen($this->context->periode_mulai_id, ['A' => 20, 'B' => 5]),
            $this->targetKomponen($this->tw2->id, ['A' => 30, 'B' => 5]),
        ]);
        $this->koreksi([], ['A' => 'penjumlah', 'C' => 'penjumlah']);

        $this->ajukan()->assertUnprocessable()->assertJsonPath('errors.pengajuan.0',
            'Pengukuran ini memakai versi indikator lama. Tim Perencanaan perlu memindahkannya ke versi terbaru sebelum diajukan.');

        $this->assertSame(0, $this->pengukuran->versions()->count());
    }

    public function test_tw2_dengan_versi_lama_ditolak_setelah_ra_koreksi_disahkan(): void
    {
        $this->rantai();
        $v2 = $this->koreksi([], ['A' => 'penjumlah', 'C' => 'penjumlah']);
        $this->versiRencanaAksi($v2, 4, [$this->targetKomponen($this->tw2->id, ['A' => 20, 'C' => 7])]);

        $this->ajukan()->assertUnprocessable()->assertJsonPath('errors.pengajuan.0',
            'Pengukuran ini memakai versi indikator lama. Tim Perencanaan perlu memindahkannya ke versi terbaru sebelum diajukan.');

        $this->assertSame(0, $this->pengukuran->versions()->count());
    }

    public function test_klaim_komponen_di_luar_snapshot_pada_periode_yang_sama_tetap_ditolak(): void
    {
        $this->rantai();
        $tw1 = $this->context->periode_mulai_id;
        $this->versiRencanaAksi($this->context, 2, [
            $this->targetKomponen($tw1, ['A' => 20, 'B' => 5]),
            $this->targetKomponen($this->tw2->id, ['A' => 30, 'B' => 5]),
        ]);
        $this->koreksi([], ['A' => 'penjumlah', 'C' => 'penjumlah']);
        $this->pengukuran->update(['periode_id' => $tw1]);
        $this->klaimKomponenC($tw1);

        $this->ajukan()->assertUnprocessable()->assertJsonPath('errors.pengajuan.0',
            'Klaim kegiatan tidak cocok dengan konteks indikator/tahun/unit.');

        $this->assertSame(0, $this->pengukuran->versions()->count());
    }

    public function test_tw1_tanpa_ra_sah_bersnapshot_lama_ditolak_dengan_pesan_khusus(): void
    {
        $this->rantai(versiRa: false);
        $v2 = $this->koreksi([], ['A' => 'penjumlah', 'C' => 'penjumlah']);
        $this->versiRencanaAksi($v2, 4, [$this->targetKomponen($this->tw2->id, ['A' => 20, 'C' => 7])]);
        $this->pengukuran->update(['periode_id' => $this->context->periode_mulai_id]);

        $this->ajukan()->assertUnprocessable()->assertJsonPath('errors.pengajuan.0',
            'Belum ada versi rencana aksi sah untuk snapshot pengukuran ini.');

        $this->assertSame(0, $this->pengukuran->versions()->count());
    }

    public function test_unit_ra_berbeda_ditolak_setelah_snapshot_lolos_syarat_versi_berlaku(): void
    {
        $this->rantai();
        $this->koreksi();
        $this->pengukuran->update(['periode_id' => $this->context->periode_mulai_id]);
        $this->plan->update(['unit_id' => $this->unitTujuan()->id]);

        $this->ajukan()->assertUnprocessable()->assertJsonPath('errors.pengajuan.0',
            'Unit rencana aksi berbeda dengan unit pengukuran.');

        $this->assertSame(0, $this->pengukuran->versions()->count());
    }

    public function test_periode_lampau_tetap_memuat_target_dan_versi_ra_meski_snapshot_bukan_versi_berlaku(): void
    {
        $this->rantai('manual', []);
        $tw1 = $this->context->periode_mulai_id;
        $this->pengukuran->update(['periode_id' => $tw1]);
        $this->jadwal->update(['activated_at' => '2026-04-01 09:00:00']);
        $this->travelTo(Carbon::parse('2026-04-10 09:00:00', 'Asia/Makassar'));
        $this->koreksi(['periode_mulai_id' => $tw1]);
        $this->assertTrue(PeriodeTanpaTarget::isPeriodeLampau($this->jadwal->fresh(),
            PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->where('periode_id', $tw1)->sole()));

        $this->actingAs($this->actor)->postJson('/pengukuran/'.$this->pengukuran->id,
            ['action' => 'ajukan', 'versi' => 1, 'nilai' => 87.46])->assertRedirect();

        $beku = DB::table('pengukuran_versi')->where('pengukuran_id', $this->pengukuran->id)->sole();
        $payload = json_decode($beku->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($this->planVersion->id, $beku->rencana_aksi_versi_id);
        $this->assertEquals(70, $payload['target']);
        $this->assertFalse($payload['tanpa_target_periode']);
    }
}
