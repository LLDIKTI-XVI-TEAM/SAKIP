<?php

namespace Tests\Feature\Jadwal;

use App\Actions\Jadwal\TambahIndikatorJadwal;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\TargetKinerja;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Penambahan indikator resmi ke jadwal aktif di tengah tahun: snapshot versi 1 dengan periode mulai
 * pilihan, periode sebelumnya Tidak berlaku, snapshot lama, jendela PIC, dan baris jadwal tetap,
 * serta audit keberhasilan maupun penolakan.
 */
class TambahIndikatorJadwalTest extends TestCase
{
    use CreatesDashboardFixture;
    use CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    private const ALASAN = 'Indikator baru ditetapkan resmi.';

    private const PESAN_TIDAK_LAYAK = 'Indikator tidak aktif, belum berlaku pada tahun jadwal, atau bukan bagian Renstra jadwal ini.';

    private const PESAN_JADWAL = 'Indikator hanya dapat ditambahkan pada jadwal aktif yang belum melewati tanggal penutupan.';

    private PeriodeJadwal $tw2;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->tw2 = $this->tambahPeriode(2, '2026-06-01', '2026-06-15');
    }

    /** Indikator resmi yang belum terjadwal di Sasaran dan unit fixture; atribut meniru indikator siap aktivasi. */
    private function kandidat(?string $target = '80', array $atribut = [], bool $denganTarget = true): IndikatorKinerja
    {
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $this->pengukuran->indikator->sasaran_strategis_id, 'unit_id' => $this->unit->id,
            'kode' => 'IKU-'.Str::random(6), 'nama' => 'Indikator tengah tahun', 'definisi_operasional' => 'Definisi operasional fixture', 'satuan' => 'persen',
            'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'status' => 'aktif',
            'tahun_mulai_berlaku' => 2026, 'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan', ...$atribut]);
        if ($denganTarget) {
            TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => $target, 'baseline' => null]);
        }

        return $indikator;
    }

    /** @param list<array{0: string, 1: string, 2: int}> $komponen kode, bobot, urutan */
    private function komponen(IndikatorKinerja $indikator, array $komponen): void
    {
        foreach ($komponen as [$kode, $bobot, $urutan]) {
            IndikatorKomponen::create(['indikator_id' => $indikator->id, 'kode' => $kode, 'label' => 'Komponen '.$kode, 'peran' => 'penjumlah',
                'bobot' => $bobot, 'urutan' => $urutan, 'aktif' => true, 'created_by' => $this->actor->id]);
        }
    }

    private function tambah(User $user, array $overrides = []): TestResponse
    {
        return $this->actingAs($user)->from('/jadwal/'.$this->jadwal->id)->post('/jadwal/'.$this->jadwal->id.'/indikator',
            [...['periode_mulai_id' => $this->tw2->periode_id, 'alasan' => self::ALASAN], ...$overrides]);
    }

    private function tolak(User $user, string $permission): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id,
            'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => null, 'alasan' => 'Fixture deny',
            'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    private function penolakan(string $alasan): int
    {
        return AuditLog::where('tindakan', 'jadwal.tambah_indikator_ditolak')->where('nilai_baru->alasan_penolakan', $alasan)->count();
    }

    private function berhasil(TestResponse $response): TestResponse
    {
        return $response->assertSessionHasNoErrors()->assertRedirect(route('jadwal.show', $this->jadwal->id));
    }

    /** Sidik jari snapshot dan komponen selain snapshot yang baru dibuat. @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>} */
    private function sidikJariSnapshot(array $kecuali = []): array
    {
        return [DB::table('jadwal_snapshot')->whereNotIn('id', $kecuali)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('jadwal_snapshot_komponen')->whereNotIn('jadwal_snapshot_id', $kecuali)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()];
    }

    public function test_snapshot_baru_memakai_periode_mulai_pilihan_anggota_jadwal(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $kandidat = $this->kandidat('76.25', ['tipe_perhitungan' => 'penjumlahan', 'arah' => 'turun_baik', 'dinilai_tahunan' => true]);
        TargetKinerja::where('indikator_kinerja_id', $kandidat->id)->update(['baseline' => '12.5']);
        $this->komponen($kandidat, [['B', '2.5', 2], ['A', '1', 1]]);

        $this->berhasil($this->tambah($perencana, ['indikator_id' => $kandidat->id]))
            ->assertSessionHas('inertia.flash_data.success', 'Indikator berhasil ditambahkan ke jadwal berjalan.');

        $snapshot = JadwalSnapshot::where('indikator_id', $kandidat->id)->sole();
        $kandidat->refresh();
        $target = TargetKinerja::where('indikator_kinerja_id', $kandidat->id)->sole();
        $this->assertSame([1, $this->tw2->periode_id, true], [$snapshot->nomor_versi, $snapshot->periode_mulai_id, (bool) $snapshot->komposisi_final]);
        $this->assertSame([...$kandidat->only(['unit_id', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'dinilai_tahunan']), 'definisi' => $kandidat->definisi_operasional],
            [...$snapshot->only(['unit_id', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'dinilai_tahunan']), 'definisi' => $snapshot->definisi]);
        $this->assertSame([$target->getRawOriginal('target_tahunan'), $target->getRawOriginal('baseline')], [$snapshot->getRawOriginal('target'), $snapshot->getRawOriginal('baseline')]);
        $this->assertSame('76.250000000000', $snapshot->getRawOriginal('target'));
        $salinan = fn ($rows): array => $rows->map(fn ($row): array => [$row->kode, $row->label, $row->peran, $row->getRawOriginal('bobot'), $row->urutan])->all();
        $this->assertSame($salinan(IndikatorKomponen::where('indikator_id', $kandidat->id)->orderBy('urutan')->get()),
            $salinan(JadwalSnapshotKomponen::where('jadwal_snapshot_id', $snapshot->id)->orderBy('urutan')->get()));

        // UUID kapital sah di input; audit dibentuk dari atribut sebelum refresh sehingga membuktikan normalisasi.
        $kedua = $this->kandidat();
        $this->berhasil($this->tambah($perencana, ['indikator_id' => strtoupper($kedua->id), 'periode_mulai_id' => strtoupper($this->tw2->periode_id)]));
        $baru = JadwalSnapshot::where('indikator_id', $kedua->id)->sole();
        $audit = AuditLog::where('tindakan', 'jadwal_snapshot.buat')->where('objek_id', $baru->id)->sole();
        $this->assertSame([strtolower($kedua->id), strtolower($this->tw2->periode_id)], [$audit->nilai_baru['indikator_id'], $audit->nilai_baru['periode_mulai_id']]);
    }

    public function test_periode_mulai_wajib_dan_harus_anggota_jadwal(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $kandidat = $this->kandidat();
        $asing = Periode::create(['nama' => 'Triwulan III', 'urutan' => 3, 'aktif' => true, 'is_nilai_akhir' => false]);

        $this->tambah($perencana, ['indikator_id' => $kandidat->id, 'periode_mulai_id' => ''])->assertSessionHasErrors('periode_mulai_id');
        $this->tambah($perencana, ['indikator_id' => $kandidat->id, 'periode_mulai_id' => $asing->id])
            ->assertSessionHasErrors(['periode_mulai_id' => 'Periode mulai berlaku harus salah satu periode jadwal ini.']);

        $this->assertSame(0, JadwalSnapshot::where('indikator_id', $kandidat->id)->count());
        $this->assertSame(1, $this->penolakan('input_tidak_valid'));
        $this->assertSame(1, $this->penolakan('aturan_penambahan'));
    }

    public function test_periode_sebelum_mulai_tidak_berlaku_di_dashboard_pengukuran_rencana_aksi_dan_rekap(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $kandidat = $this->kandidat(atribut: ['kode' => 'I-TENGAH']);
        $tw1 = $this->pengukuran->periode_id;
        $this->berhasil($this->tambah($perencana, ['indikator_id' => $kandidat->id]));

        // (a) Dashboard: TW I tidak berlaku dan tidak ikut dihitung.
        $dashboard = $this->actingAs($this->actor)->get('/dashboard')->assertOk()->viewData('page')['props'];
        $this->assertSame('tidak_berlaku', collect($dashboard['statusIndikator'])->firstWhere('indikator.kode', 'I-TENGAH')['status']);
        $this->assertSame(1, $dashboard['stats']['total']);

        // (b) Daftar pengukuran TW I: baris tanpa header, tidak berlaku, dan tidak dapat dibuat.
        $pengukuran = $this->actingAs($perencana)->get('/pengukuran?'.http_build_query(['jadwal_id' => $this->jadwal->id, 'periode_id' => $tw1]))
            ->assertOk()->viewData('page')['props']['pengukurans'];
        $baris = collect($pengukuran)->firstWhere('indikator.kode', 'I-TENGAH');
        $this->assertSame(['absent', false, false], [$baris['jenis'], $baris['berlaku'], $baris['can']['create']]);

        // (c) Matriks RA: header di unit snapshot; halaman mencari snapshot lewat jadwal × indikator.
        $ra = RencanaAksi::create([...$this->plan->only(['tahun', 'unit_id', 'jadwal_tahunan_id', 'penanggung_jawab_id', 'created_by']), 'indikator_id' => $kandidat->id, 'status_alur' => 'draft']);
        $this->actingAs($this->actor)->get("/rencana-aksi/{$ra->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.periode.0.id', $tw1)->where('rencanaAksi.periode.0.efektif', false)->where('rencanaAksi.periode.0.status', 'tidak_berlaku')
            ->where('rencanaAksi.periode.1.id', $this->tw2->periode_id)->where('rencanaAksi.periode.1.efektif', true));

        // (d) Rekap: periode sebelum efektif bukan baris rekap, periode mulai tetap tampil.
        $rekap = fn (string $periodeId): array => collect($this->actingAs($this->actor)
            ->get('/laporan/rekap?'.http_build_query(['jadwal_id' => $this->jadwal->id, 'periode_id' => $periodeId]))
            ->assertOk()->viewData('page')['props']['baris'])->pluck('indikator.id')->all();
        $this->assertNotContains($kandidat->id, $rekap($tw1));
        $this->assertContains($kandidat->id, $rekap($this->tw2->periode_id));

        // (e) Header pengukuran TW I ditolak.
        $snapshot = JadwalSnapshot::where('indikator_id', $kandidat->id)->sole();
        $this->actingAs($perencana)->post('/pengukuran/ensure-draft', ['jadwal_snapshot_id' => $snapshot->id, 'periode_id' => $tw1])
            ->assertSessionHasErrors(['periode_id' => 'Periode belum termasuk kewajiban efektif indikator pada jadwal ini.']);
        $this->assertSame(0, PengukuranKinerja::where('indikator_id', $kandidat->id)->count());
    }

    public function test_snapshot_lama_tidak_berubah_dan_indikator_terjadwal_ditolak(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $kandidat = $this->kandidat();
        $sebelum = $this->sidikJariSnapshot();
        $jumlah = JadwalSnapshot::count();

        $this->berhasil($this->tambah($perencana, ['indikator_id' => $kandidat->id]));
        $baru = JadwalSnapshot::where('indikator_id', $kandidat->id)->sole();
        $this->assertSame($jumlah + 1, JadwalSnapshot::count());
        $this->assertSame($sebelum, $this->sidikJariSnapshot([$baru->id]));

        $this->tambah($perencana, ['indikator_id' => $this->pengukuran->indikator_id])
            ->assertSessionHasErrors(['indikator_id' => 'Indikator sudah masuk jadwal ini.']);
        $this->assertSame($jumlah + 1, JadwalSnapshot::count());
        $this->assertSame($sebelum, $this->sidikJariSnapshot([$baru->id]));
    }

    public function test_jendela_pic_dan_baris_jadwal_tidak_berubah(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $kandidat = $this->kandidat();
        $jadwal = fn (): array => (array) DB::table('jadwal_tahunan')->where('id', $this->jadwal->id)->first();
        $jendela = fn (): array => DB::table('jadwal_periode')->where('jadwal_id', $this->jadwal->id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        [$jadwalSebelum, $jendelaSebelum, $jumlah] = [$jadwal(), $jendela(), JadwalSnapshot::count()];

        $this->berhasil($this->tambah($perencana, ['indikator_id' => $kandidat->id]));

        $this->assertSame($jumlah + 1, JadwalSnapshot::count());
        $this->assertSame($jadwalSebelum, $jadwal());
        $this->assertSame($jendelaSebelum, $jendela());
    }

    public function test_audit_memuat_alasan_detail_dan_dasar_izin(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $kandidat = $this->kandidat('15', ['tipe_perhitungan' => 'penjumlahan']);
        $this->komponen($kandidat, [['A', '1', 1]]);
        $alasan = "Indikator baru ditetapkan resmi.\nRujukan: SK Kepala.";
        $auditJadwal = AuditLog::where('tindakan', 'like', 'jadwal.%')->count();

        $this->berhasil($this->tambah($perencana, ['indikator_id' => $kandidat->id, 'alasan' => $alasan]));

        $snapshot = JadwalSnapshot::where('indikator_id', $kandidat->id)->sole();
        $audit = AuditLog::where('tindakan', 'jadwal_snapshot.buat')->sole();
        $this->assertSame([$perencana->id, 'jadwal_snapshot', $snapshot->id, $alasan], [$audit->actor_id, $audit->objek_tipe, $audit->objek_id, $audit->alasan]);
        $this->assertSame([$kandidat->id, $this->tw2->periode_id, 1, '15.000000000000', true],
            [$audit->nilai_baru['indikator_id'], $audit->nilai_baru['periode_mulai_id'], $audit->nilai_baru['nomor_versi'], $audit->nilai_baru['target'], $audit->nilai_baru['komposisi_final']]);
        $this->assertCount(1, $audit->nilai_baru['komponen']);
        $this->assertArrayNotHasKey('label', $audit->nilai_baru['komponen'][0]);
        $this->assertSame('A', $audit->nilai_baru['komponen'][0]['kode']);
        $this->assertEqualsCanonicalizing(['jadwal:buka_kembali', 'indikator:read'], array_keys($audit->dasar_izin));
        $this->assertSame(['diizinkan', 'diizinkan'], [$audit->dasar_izin['jadwal:buka_kembali']['keputusan'], $audit->dasar_izin['indikator:read']['keputusan']]);
        $this->assertSame($auditJadwal, AuditLog::where('tindakan', 'like', 'jadwal.%')->count());
    }

    public function test_izin_dicek_dan_penolakan_teraudit_di_luar_rollback(): void
    {
        $kandidat = $this->kandidat();
        $payload = ['indikator_id' => $kandidat->id];

        // (a) Tanpa izin jadwal: berhenti di FormRequest.
        $this->tambah($this->userWithRole('pegawai'), $payload)->assertForbidden();

        // (b) Izin jadwal ada, indikator:read dicabut: ditolak recheck Action dengan keputusan yang dipakai.
        $tanpaBaca = $this->userWithRole('perencanaan');
        $this->tolak($tanpaBaca, 'indikator:read');
        $this->tambah($tanpaBaca, $payload)->assertForbidden();
        $audit = AuditLog::where('tindakan', 'jadwal.tambah_indikator_ditolak')->where('actor_id', $tanpaBaca->id)->sole();
        $this->assertSame(['ditolak', 'diizinkan'], [$audit->dasar_izin['indikator:read']['keputusan'], $audit->dasar_izin['jadwal:buka_kembali']['keputusan']]);

        // (c) Deny jadwal:buka_kembali menang atas preset peran.
        $tanpaJadwal = $this->userWithRole('perencanaan');
        $this->tolak($tanpaJadwal, 'jadwal:buka_kembali');
        $this->tambah($tanpaJadwal, $payload)->assertForbidden();

        // (d) Action dipanggil langsung tetap memeriksa ulang izin dengan aktor terkunci.
        $this->assertThrows(fn () => app(TambahIndikatorJadwal::class)->handle($tanpaJadwal, $this->jadwal->id,
            [...$payload, 'periode_mulai_id' => $this->tw2->periode_id, 'alasan' => self::ALASAN]), AuthorizationException::class);
        // FormRequest mencatat satu keputusan izin dalam bentuk datar; hanya Action yang mencatat dasar_izin per kode izin, sehingga bentuk itu menandai kasus (d).
        $langsung = AuditLog::where('tindakan', 'jadwal.tambah_indikator_ditolak')->where('actor_id', $tanpaJadwal->id)->get()
            ->firstWhere(fn (AuditLog $row): bool => isset($row->dasar_izin['jadwal:buka_kembali']));
        $this->assertSame('ditolak', $langsung->dasar_izin['jadwal:buka_kembali']['keputusan']);

        $this->assertSame(4, $this->penolakan('izin_ditolak'));
        $this->assertSame(0, JadwalSnapshot::where('indikator_id', $kandidat->id)->count());
    }

    public function test_jadwal_bukan_aktif_atau_lewat_penutupan_ditolak(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $kandidat = $this->kandidat();

        $this->travelTo(now()->setDate(2027, 1, 1)->setTime(9, 0));
        $this->tambah($perencana, ['indikator_id' => $kandidat->id])->assertSessionHasErrors(['jadwal' => self::PESAN_JADWAL]);
        $this->travelBack();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(9, 0));
        JadwalTahunan::whereKey($this->jadwal->id)->update(['status' => 'ditutup', 'closed_at' => now()]);
        $this->tambah($perencana, ['indikator_id' => $kandidat->id])->assertSessionHasErrors(['jadwal' => self::PESAN_JADWAL]);

        $this->assertSame(0, JadwalSnapshot::where('indikator_id', $kandidat->id)->count());
        $this->assertSame(2, $this->penolakan('aturan_penambahan'));
    }

    public function test_indikator_tidak_layak_ditolak(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $renstraLain = Renstra::create(['kode' => 'R-LAIN', 'nama' => 'Renstra Lain', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'status' => 'draft',
            'dasar_hukum' => 'Kepmen fixture', 'created_by' => $this->actor->id]);
        $sasaranLain = SasaranStrategis::create(['renstra_id' => $renstraLain->id, 'kode' => 'S-LAIN', 'deskripsi' => 'Sasaran lain']);
        $jumlah = JadwalSnapshot::count();
        $tidakLayak = [
            $this->kandidat(atribut: ['status' => IndikatorKinerja::STATUS_ARSIP])->id,
            $this->kandidat(atribut: ['tahun_mulai_berlaku' => 2027])->id,
            $this->kandidat(atribut: ['sasaran_strategis_id' => $sasaranLain->id])->id,
            (string) Str::uuid(),
        ];
        foreach ($tidakLayak as $indikatorId) {
            $this->tambah($perencana, ['indikator_id' => $indikatorId])->assertSessionHasErrors(['indikator_id' => self::PESAN_TIDAK_LAYAK]);
        }

        $this->tambah($perencana, ['indikator_id' => $this->kandidat(atribut: ['nama' => str_repeat('n', 256)])->id])->assertSessionHasErrors(['indikator_id' => 'Data indikator tidak dapat dibekukan (nama terlalu panjang atau komponen tidak sesuai tipe perhitungan). Perbaiki pada modul indikator.']);
        foreach ([$this->kandidat(null), $this->kandidat(denganTarget: false)] as $tanpaTarget) {
            $this->tambah($perencana, ['indikator_id' => $tanpaTarget->id])->assertSessionHasErrors(['indikator_id' => 'Target tahunan indikator pada Perjanjian Kinerja belum diisi.']);
        }

        $this->assertSame($jumlah, JadwalSnapshot::count());
        $this->assertSame(7, $this->penolakan('aturan_penambahan'));
    }

    public function test_target_nol_sah_dan_baseline_opsional(): void
    {
        $kandidat = $this->kandidat('0');

        $this->berhasil($this->tambah($this->userWithRole('perencanaan'), ['indikator_id' => $kandidat->id]));

        $snapshot = JadwalSnapshot::where('indikator_id', $kandidat->id)->sole();
        $this->assertSame(['0.000000000000', null], [$snapshot->getRawOriginal('target'), $snapshot->getRawOriginal('baseline')]);
    }

    public function test_field_tak_dikenal_dan_uuid_route_salah(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $kandidat = $this->kandidat();

        $this->tambah($perencana, ['indikator_id' => $kandidat->id, 'nomor_versi' => 2])
            ->assertSessionHasErrors(['jadwal' => 'Permintaan memuat field yang tidak didukung.']);
        $this->actingAs($perencana)->post('/jadwal/bukan-uuid/indikator', ['indikator_id' => $kandidat->id, 'periode_mulai_id' => $this->tw2->periode_id, 'alasan' => self::ALASAN])
            ->assertNotFound();

        $this->assertSame(0, JadwalSnapshot::where('indikator_id', $kandidat->id)->count());
    }

    public function test_konflik_lock_menjadi_penolakan_muat_ulang_dengan_audit(): void
    {
        $kandidat = $this->kandidat();
        $disuntik = false;
        DB::connection()->beforeExecuting(function (string $sql) use (&$disuntik): void {
            if (! $disuntik && str_contains($sql, 'from "indikator_kinerjas"') && str_contains($sql, 'for update')) {
                $disuntik = true;
                $pdo = new \PDOException('deadlock detected');
                $pdo->errorInfo = ['40P01', 7, 'deadlock detected'];
                throw new QueryException('pgsql', $sql, [], $pdo);
            }
        });

        $this->tambah($this->userWithRole('perencanaan'), ['indikator_id' => $kandidat->id])
            ->assertSessionHasErrors(['jadwal' => 'Data sedang diubah pengguna lain. Muat ulang detail jadwal lalu coba lagi.']);

        $this->assertTrue($disuntik);
        $this->assertSame(0, JadwalSnapshot::where('indikator_id', $kandidat->id)->count());
        $this->assertSame(1, $this->penolakan('aturan_penambahan'));
    }

    public function test_props_editor_menawarkan_kandidat_hanya_untuk_jadwal_aktif_berizin(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $ditolak = $this->userWithRole('perencanaan');
        $this->tolak($ditolak, 'jadwal:buka_kembali');
        $kandidat = $this->kandidat(atribut: ['kode' => 'IKU-B']);
        $tanpaTarget = $this->kandidat(null, ['kode' => 'IKU-A']);
        $this->kandidat(atribut: ['status' => IndikatorKinerja::STATUS_ARSIP]);
        $editor = fn (User $user): TestResponse => $this->actingAs($user)->get('/jadwal/'.$this->jadwal->id)->assertOk();
        $tanpaPenawaran = fn ($page) => $page->where('can.tambah_indikator', false)->where('indikator_belum_terjadwal', [])
            ->where('periode_mulai_default', null)->where('perjanjian_kinerja_id', null);

        // Daftar persis (key dan urutan kode): indikator terjadwal dan arsip tidak ditawarkan, nilai target tidak dikirim.
        $editor($perencana)->assertInertia(fn ($page) => $page
            ->where('can.tambah_indikator', true)
            ->where('indikator_belum_terjadwal', [
                ['id' => $tanpaTarget->id, 'kode' => 'IKU-A', 'nama' => 'Indikator tengah tahun', 'target_terisi' => false, 'dapat_dibekukan' => true],
                ['id' => $kandidat->id, 'kode' => 'IKU-B', 'nama' => 'Indikator tengah tahun', 'target_terisi' => true, 'dapat_dibekukan' => true],
            ])
            ->where('periode_mulai_default', $this->pengukuran->periode_id)
            ->where('perjanjian_kinerja_id', $this->jadwal->renstra_pk_id));
        $editor($ditolak)->assertInertia($tanpaPenawaran);

        $this->travelTo(now()->setDate(2026, 3, 16));
        $editor($perencana)->assertInertia(fn ($page) => $page->where('periode_mulai_default', $this->tw2->periode_id));

        $this->travelTo(now()->setDate(2027, 1, 1));
        $editor($perencana)->assertInertia($tanpaPenawaran);
    }
}
