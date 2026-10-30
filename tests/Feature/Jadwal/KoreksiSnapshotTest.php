<?php

namespace Tests\Feature\Jadwal;

use App\Actions\Jadwal\TambahIndikatorJadwal;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\TargetKinerja;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengukuran\DaftarKewajibanPengukuran;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Koreksi snapshot berversi (Q35 §35.1, Q79): versi baru disalin dari data master terkini, versi lama dan rujukannya
 * tetap, periode mulai tidak mundur, izin dinilai per kelompok kolom, serta keberhasilan dan penolakan teraudit.
 */
class KoreksiSnapshotTest extends TestCase
{
    use CreatesDashboardFixture;
    use CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    private const ALASAN = 'Target PK salah ketik saat penyusunan.';

    private const RUJUKAN = 'Nota dinas Perencanaan 12/2026';

    private const PESAN_JADWAL = 'Snapshot hanya dapat dikoreksi pada jadwal aktif yang belum melewati tanggal penutupan.';

    private const PESAN_TIDAK_COCOK = 'Snapshot tidak ditemukan pada jadwal ini.';

    private const PESAN_USANG = 'Snapshot sudah dikoreksi atau berubah. Muat ulang halaman snapshot sebelum mengoreksi.';

    private const PESAN_TANPA_SELISIH = 'Data master sama dengan versi snapshot terbaru. Perbaiki data master terlebih dahulu.';

    private const PESAN_PERIODE_JADWAL = 'Periode mulai berlaku harus salah satu periode jadwal ini.';

    private const PESAN_PERIODE_MUNDUR = 'Periode mulai berlaku tidak boleh lebih awal dari periode mulai versi sebelumnya.';

    private PeriodeJadwal $tw1;

    private PeriodeJadwal $tw2;

    private PeriodeJadwal $tw3;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->tw1 = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->sole();
        $this->tw2 = $this->tambahPeriode(2, '2026-06-01', '2026-06-15');
        $this->tw3 = $this->tambahPeriode(3, '2026-09-01', '2026-09-15');
    }

    /**
     * Indikator resmi yang dibekukan lewat jalur produksi penambahan indikator, sehingga versi 1 sama dengan master.
     *
     * @param  list<array{0: string, 1: string, 2: int}>  $komponen  kode, bobot, urutan
     */
    private function terjadwal(array $atribut = [], array $komponen = [], ?PeriodeJadwal $mulai = null, string $target = '80'): JadwalSnapshot
    {
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $this->pengukuran->indikator->sasaran_strategis_id, 'unit_id' => $this->unit->id,
            'kode' => 'IKU-'.Str::random(6), 'nama' => 'Indikator koreksi', 'definisi_operasional' => 'Definisi operasional fixture', 'satuan' => 'persen',
            'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'status' => 'aktif',
            'tahun_mulai_berlaku' => 2026, 'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan', ...$atribut]);
        TargetKinerja::create(['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026, 'target_tahunan' => $target, 'baseline' => '10']);
        foreach ($komponen as [$kode, $bobot, $urutan]) {
            IndikatorKomponen::create(['indikator_id' => $indikator->id, 'kode' => $kode, 'label' => 'Komponen '.$kode, 'peran' => 'penjumlah',
                'bobot' => $bobot, 'urutan' => $urutan, 'aktif' => true, 'created_by' => $this->actor->id]);
        }
        app(TambahIndikatorJadwal::class)->handle($this->actor, $this->jadwal->id,
            ['indikator_id' => $indikator->id, 'periode_mulai_id' => ($mulai ?? $this->tw1)->periode_id, 'alasan' => 'Fixture versi 1']);

        return JadwalSnapshot::where('indikator_id', $indikator->id)->sole();
    }

    private function ubahTarget(JadwalSnapshot $snapshot, string $target): void
    {
        TargetKinerja::where('indikator_kinerja_id', $snapshot->indikator_id)->where('tahun', 2026)->update(['target_tahunan' => $target]);
    }

    private function payload(JadwalSnapshot $snapshot, array $overrides = []): array
    {
        return [...['periode_mulai_id' => $snapshot->periode_mulai_id, 'alasan_koreksi' => self::ALASAN, 'rujukan_koreksi' => self::RUJUKAN,
            'expected_snapshot_id' => $snapshot->id, 'expected_snapshot_versi' => $snapshot->nomor_versi], ...$overrides];
    }

    private function koreksi(User $user, JadwalSnapshot $snapshot, array $overrides = [], ?string $jadwalId = null): TestResponse
    {
        $jadwalId ??= $this->jadwal->id;

        return $this->actingAs($user)->from("/jadwal/{$jadwalId}/snapshot")
            ->post("/jadwal/{$jadwalId}/snapshot/{$snapshot->id}/koreksi", $this->payload($snapshot, $overrides));
    }

    private function tolak(User $user, string $permission): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id,
            'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => null, 'alasan' => 'Fixture deny',
            'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    private function penolakan(?string $alasan = null): int
    {
        return AuditLog::where('tindakan', 'jadwal_snapshot.koreksi_ditolak')
            ->when($alasan !== null, fn ($query) => $query->where('nilai_baru->alasan_penolakan', $alasan))->count();
    }

    private function versi(JadwalSnapshot $snapshot): int
    {
        return JadwalSnapshot::where('jadwal_id', $snapshot->jadwal_id)->where('indikator_id', $snapshot->indikator_id)->count();
    }

    /** Baris mentah snapshot beserta komponennya. @return array{0: array<string, mixed>, 1: list<array<string, mixed>>} */
    private function sidikJari(string $snapshotId): array
    {
        return [(array) DB::table('jadwal_snapshot')->where('id', $snapshotId)->first(),
            DB::table('jadwal_snapshot_komponen')->where('jadwal_snapshot_id', $snapshotId)->orderBy('kode')->get()->map(fn ($row) => (array) $row)->all()];
    }

    public function test_koreksi_menerbitkan_versi_baru_dari_data_master_tanpa_mengubah_versi_lama(): void
    {
        $lama = $this->terjadwal(target: '94');
        $sidikLama = $this->sidikJari($lama->id);
        $this->ubahTarget($lama, '94.05');
        IndikatorKinerja::whereKey($lama->indikator_id)->update(['nama' => 'Indikator koreksi (nama resmi)', 'dinilai_tahunan' => true]);

        $this->koreksi($this->userWithRole('perencanaan'), $lama)->assertSessionHasNoErrors()
            ->assertRedirect("/jadwal/{$this->jadwal->id}/snapshot")
            ->assertSessionHas('inertia.flash_data.success', 'Versi koreksi snapshot berhasil diterbitkan.');

        $this->assertSame($sidikLama, $this->sidikJari($lama->id));
        $baru = DB::table('jadwal_snapshot')->where('indikator_id', $lama->indikator_id)->where('nomor_versi', 2)->first();
        $this->assertNotNull($baru);
        $this->assertSame([$lama->id, self::ALASAN, self::RUJUKAN, true, $lama->periode_mulai_id],
            [$baru->menggantikan_id, $baru->alasan_koreksi, $baru->rujukan_koreksi, (bool) $baru->komposisi_final, $baru->periode_mulai_id]);
        $this->assertSame(['94.050000000000', '10.000000000000', 'Indikator koreksi (nama resmi)', true],
            [$baru->target, $baru->baseline, $baru->nama, (bool) $baru->dinilai_tahunan]);

        $audit = AuditLog::where('tindakan', 'jadwal_snapshot.koreksi')->where('objek_id', $baru->id)->sole();
        $this->assertSame(self::ALASAN, $audit->alasan);
        $this->assertSame(['94.000000000000', 'Indikator koreksi', false], [$audit->nilai_lama['target'], $audit->nilai_lama['nama'], $audit->nilai_lama['dinilai_tahunan']]);
        $this->assertSame(['94.050000000000', 'Indikator koreksi (nama resmi)', true, 2, $lama->id, self::RUJUKAN],
            [$audit->nilai_baru['target'], $audit->nilai_baru['nama'], $audit->nilai_baru['dinilai_tahunan'], $audit->nilai_baru['nomor_versi'],
                $audit->nilai_baru['menggantikan_id'], $audit->nilai_baru['rujukan_koreksi']]);
        $this->assertEqualsCanonicalizing(['target:update', 'indikator:update'], array_keys($audit->dasar_izin));
        $this->assertSame(0, $this->penolakan());
    }

    public function test_audit_koreksi_memuat_definisi_dan_label_komponen_yang_berubah(): void
    {
        $lama = $this->terjadwal(['tipe_perhitungan' => 'penjumlahan'], [['A', '1', 1]]);
        IndikatorKinerja::whereKey($lama->indikator_id)->update(['definisi_operasional' => 'Definisi operasional resmi']);
        IndikatorKomponen::where('indikator_id', $lama->indikator_id)->update(['label' => 'Komponen A resmi']);

        $this->koreksi($this->userWithRole('perencanaan'), $lama)->assertSessionHasNoErrors();

        $audit = AuditLog::where('tindakan', 'jadwal_snapshot.koreksi')->sole();
        $this->assertSame(['Definisi operasional fixture', 'Definisi operasional resmi'], [$audit->nilai_lama['definisi'], $audit->nilai_baru['definisi']]);
        $this->assertSame(['Komponen A', 'Komponen A resmi'], [$audit->nilai_lama['komponen'][0]['label'], $audit->nilai_baru['komponen'][0]['label']]);
        $this->assertEqualsCanonicalizing(['indikator:update', 'komponen:update'], array_keys($audit->dasar_izin));
    }

    public function test_koreksi_menyalin_komponen_aktif_master_dan_komponen_lama_tetap(): void
    {
        $lama = $this->terjadwal(['tipe_perhitungan' => 'penjumlahan'], [['A', '1', 1], ['B', '2.5', 2]]);
        $sidikLama = $this->sidikJari($lama->id);
        IndikatorKomponen::where('indikator_id', $lama->indikator_id)->where('kode', 'B')->update(['bobot' => '3']);
        IndikatorKomponen::create(['indikator_id' => $lama->indikator_id, 'kode' => 'C', 'label' => 'Komponen C', 'peran' => 'penjumlah',
            'bobot' => '1', 'urutan' => 3, 'aktif' => true, 'created_by' => $this->actor->id]);

        $this->koreksi($this->userWithRole('perencanaan'), $lama)->assertSessionHasNoErrors();

        $this->assertSame($sidikLama, $this->sidikJari($lama->id));
        $baru = JadwalSnapshot::where('indikator_id', $lama->indikator_id)->where('nomor_versi', 2)->sole();
        $this->assertSame([['A', '1.000000000000', 1], ['B', '3.000000000000', 2], ['C', '1.000000000000', 3]],
            DB::table('jadwal_snapshot_komponen')->where('jadwal_snapshot_id', $baru->id)->orderBy('urutan')->get()
                ->map(fn ($row): array => [$row->kode, $row->bobot, $row->urutan])->all());
        $audit = AuditLog::where('tindakan', 'jadwal_snapshot.koreksi')->sole();
        $this->assertSame(['komponen:update'], array_keys($audit->dasar_izin));
        $this->assertCount(2, $audit->nilai_lama['komponen']);
        $this->assertCount(3, $audit->nilai_baru['komponen']);
    }

    public function test_versi_lama_tetap_beku_setelah_koreksi(): void
    {
        $lama = $this->terjadwal(['tipe_perhitungan' => 'penjumlahan'], [['A', '1', 1]]);
        $this->ubahTarget($lama, '85');
        $this->koreksi($this->userWithRole('perencanaan'), $lama)->assertSessionHasNoErrors();

        foreach ([fn () => DB::table('jadwal_snapshot')->where('id', $lama->id)->update(['target' => 1]),
            fn () => DB::table('jadwal_snapshot')->where('id', $lama->id)->delete(),
            fn () => DB::table('jadwal_snapshot_komponen')->where('jadwal_snapshot_id', $lama->id)->update(['bobot' => 9])] as $ubah) {
            try {
                DB::transaction($ubah);
                $this->fail('Guard snapshot tidak menolak perubahan versi lama.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
            }
        }
        $this->assertSame('80.000000000000', DB::table('jadwal_snapshot')->where('id', $lama->id)->value('target'));
    }

    public function test_rujukan_pengukuran_dan_rencana_aksi_tidak_berpindah(): void
    {
        // Snapshot fixture dirujuk RA disahkan, versi RA, dan pengukuran draf; master diselaraskan lalu targetnya dikoreksi.
        TargetKinerja::create(['indikator_kinerja_id' => $this->context->indikator_id, 'tahun' => 2026, 'target_tahunan' => '75', 'baseline' => null]);
        $this->context->refresh();
        $sebelum = [$this->pengukuran->fresh()->jadwal_snapshot_id, $this->plan->fresh()->jadwal_snapshot_id, $this->plan->fresh()->snapshot_draf_id,
            $this->planVersion->fresh()->jadwal_snapshot_id];

        $this->koreksi($this->userWithRole('perencanaan'), $this->context)->assertSessionHasNoErrors();

        $this->assertSame(2, $this->versi($this->context));
        $this->assertSame($sebelum, [$this->pengukuran->fresh()->jadwal_snapshot_id, $this->plan->fresh()->jadwal_snapshot_id,
            $this->plan->fresh()->snapshot_draf_id, $this->planVersion->fresh()->jadwal_snapshot_id]);
        $this->assertSame($this->context->id, $this->pengukuran->fresh()->jadwal_snapshot_id);

        // RA yang dibuka kembali hanya mengikuti versi koreksi lewat jalur simpan: token versi lama ditolak 409, token baru diterima.
        DB::table('rencana_aksi')->where('id', $this->plan->id)->update(['status_alur' => 'dikembalikan']);
        $baru = JadwalSnapshot::where('indikator_id', $this->context->indikator_id)->where('nomor_versi', 2)->sole();
        $simpan = fn (JadwalSnapshot $token): TestResponse => $this->actingAs($this->actor)->postJson("/rencana-aksi/{$this->plan->id}/target", [
            'expected_versi' => $this->plan->fresh()->versi, 'expected_snapshot_id' => $token->id, 'expected_snapshot_versi' => $token->nomor_versi,
            'targets' => [['periode_id' => $this->tw1->periode_id, 'komponen_id' => null, 'nilai' => 75, 'keterangan' => null]]]);
        $simpan($this->context)->assertConflict()->assertJsonValidationErrors('expected_snapshot_id');
        $this->assertNull($this->plan->fresh()->snapshot_draf_id);
        $simpan($baru)->assertSessionHasNoErrors();
        $this->assertSame($baru->id, $this->plan->fresh()->snapshot_draf_id);
        $this->assertSame($this->context->id, $this->planVersion->fresh()->jadwal_snapshot_id);
    }

    public function test_snapshot_tidak_cocok_atau_bukan_versi_terbaru_ditolak(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $lama = $this->terjadwal();
        $jadwalLain = JadwalTahunan::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2027, 'penutupan' => '2027-12-31', 'status' => 'draft']);
        $this->ubahTarget($lama, '81');

        $this->koreksi($perencana, $lama, jadwalId: $jadwalLain->id)->assertSessionHasErrors(['snapshot' => self::PESAN_TIDAK_COCOK]);
        $this->actingAs($perencana)->post("/jadwal/{$this->jadwal->id}/snapshot/".Str::uuid().'/koreksi', $this->payload($lama))
            ->assertSessionHasErrors(['snapshot' => self::PESAN_TIDAK_COCOK]);
        $this->actingAs($perencana)->postJson("/jadwal/{$this->jadwal->id}/snapshot/{$lama->id}/koreksi", $this->payload($lama, ['expected_snapshot_versi' => 2]))
            ->assertConflict()->assertJsonValidationErrors(['expected_snapshot_id' => self::PESAN_USANG]);
        $this->assertSame(1, $this->versi($lama));

        $this->koreksi($perencana, $lama)->assertSessionHasNoErrors();
        $this->ubahTarget($lama, '82');
        // Versi lama di URL dengan token lama: sudah digantikan sehingga ditolak sebagai usang.
        $this->actingAs($perencana)->postJson("/jadwal/{$this->jadwal->id}/snapshot/{$lama->id}/koreksi", $this->payload($lama))
            ->assertConflict()->assertJsonValidationErrors(['expected_snapshot_id' => self::PESAN_USANG]);
        $this->assertSame(2, $this->versi($lama));
        $this->assertSame(4, $this->penolakan());
    }

    public function test_tanpa_selisih_dan_periode_mulai_tidak_sah_ditolak(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $lama = $this->terjadwal(mulai: $this->tw2);

        $this->koreksi($perencana, $lama)->assertSessionHasErrors(['snapshot' => self::PESAN_TANPA_SELISIH]);
        // Periode mulai saja bukan selisih.
        $this->koreksi($perencana, $lama, ['periode_mulai_id' => $this->tw3->periode_id])->assertSessionHasErrors(['snapshot' => self::PESAN_TANPA_SELISIH]);

        $this->ubahTarget($lama, '90');
        $this->koreksi($perencana, $lama, ['periode_mulai_id' => $this->tw1->periode_id])->assertSessionHasErrors(['periode_mulai_id' => self::PESAN_PERIODE_MUNDUR]);
        $lain = Periode::create(['nama' => 'Periode di luar jadwal', 'urutan' => 5, 'aktif' => true, 'is_nilai_akhir' => false]);
        $this->koreksi($perencana, $lama, ['periode_mulai_id' => $lain->id])->assertSessionHasErrors(['periode_mulai_id' => self::PESAN_PERIODE_JADWAL]);
        $this->assertSame(1, $this->versi($lama));
        $this->assertSame(4, $this->penolakan('aturan_koreksi'));

        // Kontrol positif: periode mulai sama dengan atau sesudah versi sebelumnya diterima.
        $this->koreksi($perencana, $lama, ['periode_mulai_id' => $this->tw3->periode_id])->assertSessionHasNoErrors();
        $this->assertSame($this->tw3->periode_id, JadwalSnapshot::where('indikator_id', $lama->indikator_id)->where('nomor_versi', 2)->value('periode_mulai_id'));
    }

    public function test_izin_per_kelompok_kolom_dan_penolakan_teraudit(): void
    {
        $lama = $this->terjadwal();
        $this->koreksi($this->userWithRole('pegawai'), $lama)->assertForbidden();
        $this->assertSame(1, $this->penolakan('izin_ditolak'));

        $tanpaTarget = $this->userWithRole('perencanaan');
        $this->tolak($tanpaTarget, 'target:update');
        $this->ubahTarget($lama, '88');
        $this->koreksi($tanpaTarget, $lama)->assertForbidden();
        $audit = AuditLog::where('tindakan', 'jadwal_snapshot.koreksi_ditolak')->where('actor_id', $tanpaTarget->id)->sole();
        $this->assertSame('izin_ditolak', $audit->nilai_baru['alasan_penolakan']);
        $this->assertSame('ditolak', $audit->dasar_izin['target:update']['keputusan']);
        $this->assertSame(1, $this->versi($lama));

        // Kontrol positif: aktor yang sama boleh mengoreksi kelompok identitas bila target tidak berubah.
        $this->ubahTarget($lama, '80');
        IndikatorKinerja::whereKey($lama->indikator_id)->update(['satuan' => 'poin']);
        $this->koreksi($tanpaTarget, $lama)->assertSessionHasNoErrors();
        $this->assertSame(2, $this->versi($lama));

        $tanpaIndikator = $this->userWithRole('perencanaan');
        $this->tolak($tanpaIndikator, 'indikator:update');
        $baru = JadwalSnapshot::where('indikator_id', $lama->indikator_id)->where('nomor_versi', 2)->sole();
        IndikatorKinerja::whereKey($lama->indikator_id)->update(['satuan' => 'indeks']);
        $this->koreksi($tanpaIndikator, $baru)->assertForbidden();
        $this->assertSame(2, $this->versi($lama));

        // Kelompok komponen: perubahan bobot ditolak bagi aktor dengan deny komponen:update, diterima bagi kontrol positif.
        $berkomponen = $this->terjadwal(['tipe_perhitungan' => 'penjumlahan'], [['A', '1', 1]]);
        IndikatorKomponen::where('indikator_id', $berkomponen->indikator_id)->update(['bobot' => '2']);
        $tanpaKomponen = $this->userWithRole('perencanaan');
        $this->tolak($tanpaKomponen, 'komponen:update');
        $this->koreksi($tanpaKomponen, $berkomponen)->assertForbidden();
        $this->assertSame('ditolak', AuditLog::where('tindakan', 'jadwal_snapshot.koreksi_ditolak')->where('actor_id', $tanpaKomponen->id)->sole()
            ->dasar_izin['komponen:update']['keputusan']);
        $this->assertSame(1, $this->versi($berkomponen));
        $this->koreksi($this->userWithRole('perencanaan'), $berkomponen)->assertSessionHasNoErrors();
        $this->assertSame(2, $this->versi($berkomponen));
    }

    public function test_alasan_dan_rujukan_wajib_serta_field_tak_dikenal_ditolak(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $lama = $this->terjadwal();
        $this->ubahTarget($lama, '83');

        $this->koreksi($perencana, $lama, ['alasan_koreksi' => '   '])->assertSessionHasErrors('alasan_koreksi');
        $this->koreksi($perencana, $lama, ['rujukan_koreksi' => ''])->assertSessionHasErrors('rujukan_koreksi');
        $this->koreksi($perencana, $lama, ['target' => '1'])->assertSessionHasErrors('snapshot');
        $this->assertSame(1, $this->versi($lama));
        $this->assertSame(3, $this->penolakan('input_tidak_valid'));
    }

    public function test_periode_sebelum_periode_mulai_koreksi_tetap_memakai_versi_lama(): void
    {
        $lama = $this->terjadwal();
        $this->ubahTarget($lama, '96');
        $this->koreksi($this->userWithRole('perencanaan'), $lama, ['periode_mulai_id' => $this->tw2->periode_id,
            'rujukan_koreksi' => 'Surat persetujuan Eselon I nomor 77/2026'])->assertSessionHasNoErrors();
        $baru = JadwalSnapshot::where('indikator_id', $lama->indikator_id)->where('nomor_versi', 2)->sole();
        $this->assertSame(['Surat persetujuan Eselon I nomor 77/2026', $this->tw2->periode_id], [$baru->rujukan_koreksi, $baru->periode_mulai_id]);

        $baris = app(DaftarKewajibanPengukuran::class)->query([$this->tw1->id, $this->tw2->id, $this->tw3->id], DB::table('unit')->whereRaw('false')->select('id'), '2026-03-15')
            ->where('k.indikator_id', $lama->indikator_id)->get()->mapWithKeys(fn ($row): array => [$row->periode_id => $row->jadwal_snapshot_id])->all();
        $this->assertSame([$this->tw1->periode_id => $lama->id, $this->tw2->periode_id => $baru->id, $this->tw3->periode_id => $baru->id], $baris);
    }

    public function test_jadwal_tidak_aktif_atau_data_master_tidak_layak_ditolak(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $lama = $this->terjadwal();
        $this->ubahTarget($lama, '84');

        $this->travelTo(now()->setDate(2027, 1, 1)->setTime(9, 0));
        $this->koreksi($perencana, $lama)->assertSessionHasErrors(['jadwal' => self::PESAN_JADWAL]);
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(9, 0));

        IndikatorKinerja::whereKey($lama->indikator_id)->update(['nama' => str_repeat('n', 256)]);
        $this->koreksi($perencana, $lama)->assertSessionHasErrors('snapshot');
        IndikatorKinerja::whereKey($lama->indikator_id)->update(['nama' => 'Indikator koreksi', 'status' => IndikatorKinerja::STATUS_ARSIP]);
        $this->koreksi($perencana, $lama)->assertSessionHasErrors('snapshot');
        IndikatorKinerja::whereKey($lama->indikator_id)->update(['status' => 'aktif']);
        $this->ubahTarget($lama, '84');
        TargetKinerja::where('indikator_kinerja_id', $lama->indikator_id)->update(['target_tahunan' => null]);
        $this->koreksi($perencana, $lama)->assertSessionHasErrors('snapshot');
        $nonaktif = Unit::create(['nama' => 'Unit Nonaktif', 'status' => 'nonaktif', 'created_by' => $this->actor->id]);
        $this->ubahTarget($lama, '84');
        IndikatorKinerja::whereKey($lama->indikator_id)->update(['unit_id' => $nonaktif->id]);
        $this->koreksi($perencana, $lama)->assertSessionHasErrors('snapshot');

        JadwalTahunan::whereKey($this->jadwal->id)->update(['status' => 'ditutup', 'closed_at' => now()]);
        $this->koreksi($perencana, $lama)->assertSessionHasErrors(['jadwal' => self::PESAN_JADWAL]);
        $this->assertSame(1, $this->versi($lama));
        $this->assertSame(6, $this->penolakan('aturan_koreksi'));
    }

    public function test_konflik_lock_menjadi_penolakan_muat_ulang_dengan_audit(): void
    {
        $lama = $this->terjadwal();
        $this->ubahTarget($lama, '86');
        $disuntik = false;
        DB::connection()->beforeExecuting(function (string $sql) use (&$disuntik): void {
            if (! $disuntik && str_contains($sql, 'from "jadwal_snapshot"') && str_contains($sql, 'for update')) {
                $disuntik = true;
                $pdo = new \PDOException('deadlock detected');
                $pdo->errorInfo = ['40P01', 7, 'deadlock detected'];
                throw new QueryException('pgsql', $sql, [], $pdo);
            }
        });

        $this->koreksi($this->userWithRole('perencanaan'), $lama)
            ->assertSessionHasErrors(['jadwal' => 'Data sedang diubah pengguna lain. Muat ulang halaman snapshot lalu coba lagi.']);

        $this->assertTrue($disuntik);
        $this->assertSame(1, $this->versi($lama));
        $this->assertSame(1, $this->penolakan('aturan_koreksi'));
    }

    public function test_halaman_snapshot_menampilkan_versi_selisih_dan_izin_koreksi(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $lama = $this->terjadwal(['kode' => 'IKU-K'], mulai: $this->tw2);
        $this->ubahTarget($lama, '91.5');
        $halaman = fn (User $user): TestResponse => $this->actingAs($user)->get("/jadwal/{$this->jadwal->id}/snapshot");

        $halaman($perencana)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Jadwal/SnapshotIndikator')
            ->where('jadwal.id', $this->jadwal->id)
            ->where('can.koreksi', true)
            ->where('indikator', fn ($rows) => collect($rows)->firstWhere('kode', 'IKU-K') !== null)
            ->where('indikator', function ($rows) use ($lama): bool {
                $row = collect($rows)->firstWhere('kode', 'IKU-K');

                return $row['snapshot_id'] === $lama->id && $row['nomor_versi'] === 1 && $row['periode_mulai']['id'] === $this->tw2->periode_id
                    && $row['selisih']['kolom'] === [['kolom' => 'target', 'label' => 'Target PK', 'lama' => '80', 'baru' => '91.5']]
                    && $row['selisih']['komponen'] === null && count($row['riwayat']) === 1;
            }));

        $tanpaIzinKoreksi = $this->userWithRole('perencanaan');
        foreach (['target:update', 'indikator:update', 'komponen:update'] as $permission) {
            $this->tolak($tanpaIzinKoreksi, $permission);
        }
        $halaman($tanpaIzinKoreksi)->assertOk()->assertInertia(fn ($page) => $page->where('can.koreksi', false)->where('can.baca_indikator', true));

        // Seperti detail jadwal, pembaca jadwal tanpa izin baca indikator tidak menerima nama, target, maupun formula.
        $tanpaBacaIndikator = $this->userWithRole('perencanaan');
        $this->tolak($tanpaBacaIndikator, 'indikator:read');
        $halaman($tanpaBacaIndikator)->assertOk()->assertInertia(fn ($page) => $page
            ->where('indikator', [])->where('can.koreksi', false)->where('can.baca_indikator', false));
        $halaman($this->userWithRole('pegawai'))->assertForbidden();
        $this->actingAs($perencana)->get('/jadwal/'.Str::uuid().'/snapshot')->assertNotFound();
    }
}
