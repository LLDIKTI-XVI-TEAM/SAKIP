<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Actions\PerjanjianKinerja\SetujuiRevisiPerjanjianKinerja;
use App\Actions\TargetTahunan\SaveTargetTahunan;
use App\Actions\TargetTahunan\ShowTargetTahunan;
use App\Http\Requests\PerjanjianKinerja\StoreVersiPerjanjianKinerjaRequest;
use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\RenstraPkVersi;
use App\Models\TargetKinerja;
use App\Models\TargetRenstra;
use App\Models\User;
use App\Services\Pengukuran\DaftarKewajibanPengukuran;
use App\Support\PermissionCodes;
use App\Support\TargetTahunanDecimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesRevisiPkFixture;
use Tests\TestCase;

/**
 * Revisi PK berversi: PK awal dan PK akhir, nilai dasar dari snapshot terbaru, versi snapshot baru untuk target yang
 * berubah, periode mulai, gerbang target, izin, token, serta audit sukses dan penolakan.
 */
class RevisiPkTest extends TestCase
{
    use CreatesRevisiPkFixture;
    use RefreshDatabase;

    public function test_revisi_pertama_membentuk_pk_awal_dari_snapshot_versi_1_dan_pk_akhir_dari_snapshot_terbaru(): void
    {
        $lampiranAwal = Berkas::forceCreate(['id' => (string) Str::uuid(), 'berkasable_type' => 'renstra_pk', 'berkasable_id' => $this->pk->id,
            'mode' => 'tautan', 'tautan' => 'https://example.test/pk-awal', 'nama_asli' => 'PK awal', 'uploaded_by' => $this->actor->id]);
        $tengah = $this->terjadwal('IKU-3', '50', mulai: $this->tw3);
        $arsip = $this->terjadwal('IKU-4', '40');
        IndikatorKinerja::whereKey($arsip->indikator_id)->update(['status' => IndikatorKinerja::STATUS_ARSIP]);
        // Editor target mengubah master setelah PK dikunci; versi PK tetap membaca snapshot.
        $this->master($this->iku2)->update(['target_tahunan' => '62']);

        $versi2 = $this->revisi([$this->entri($this->iku1, '85')]);

        $versi1 = RenstraPkVersi::where('renstra_pk_id', $this->pk->id)->where('nomor_versi', 1)->sole();
        $this->assertSame([null, null, null, null, null, null], [$versi1->nomor_surat, $versi1->tanggal_surat, $versi1->alasan,
            $versi1->disetujui_oleh, $versi1->disetujui_pada, $versi1->periode_mulai_id]);
        $this->assertSame([$lampiranAwal->id], $versi1->berkas_awal_ids);
        $this->assertEquals([$this->iku1->indikator_id => '80', $this->iku2->indikator_id => '60', $tengah->indikator_id => '50'], $this->targetVersi($versi1));

        $this->assertSame([2, 'B-12/2026', '2026-11-01', self::ALASAN, $this->perencanaan->id, $this->tw1->periode_id, null],
            [$versi2->nomor_versi, $versi2->nomor_surat, $versi2->tanggal_surat?->toDateString(), $versi2->alasan, $versi2->disetujui_oleh,
                $versi2->periode_mulai_id, $versi2->berkas_awal_ids]);
        $this->assertNotNull($versi2->disetujui_pada);
        $this->assertEquals([$this->iku1->indikator_id => '85', $this->iku2->indikator_id => '60', $tengah->indikator_id => '50'], $this->targetVersi($versi2));
        $this->assertSame('62', $this->nilaiMaster($this->iku2));
        $this->assertSame('85', $this->nilaiMaster($this->iku1));
        $this->assertSame($this->perencanaan->id, $this->master($this->iku1)->updated_by);

        $lampiranAkhir = Berkas::where('berkasable_type', 'renstra_pk_versi')->where('berkasable_id', $versi2->id)->sole();
        $this->assertStringStartsWith("berkas/renstra_pk_versi/{$versi2->id}/", $lampiranAkhir->path);
        Storage::disk('local')->assertExists($lampiranAkhir->path);

        $audit = AuditLog::where('tindakan', 'renstra_pk_versi.setujui')->sole();
        $this->assertSame([$this->pk->id, self::ALASAN], [$audit->objek_id, $audit->alasan]);
        $this->assertEquals(['nomor_versi_sebelumnya' => 0, 'target' => [$this->iku1->indikator_id => ['snapshot' => '80', 'master' => '80']]], $audit->nilai_lama);
        $this->assertSame(['B-12/2026', true, [$lampiranAwal->id], [$this->iku1->indikator_id => '85'], [$lampiranAkhir->id]],
            [$audit->nilai_baru['nomor_surat'], $audit->nilai_baru['versi_1_dibentuk'], $audit->nilai_baru['berkas_awal_ids'],
                $audit->nilai_baru['target'], $audit->nilai_baru['lampiran']]);
        $this->assertSame([$this->snapshotTerbaru($this->iku1)->id], $audit->nilai_baru['snapshot_terbit']);
        $this->assertEqualsCanonicalizing(SetujuiRevisiPerjanjianKinerja::IZIN, array_keys($audit->dasar_izin));

        $sidikJari = fn (): array => [DB::table('renstra_pk_versi')->whereIn('id', [$versi1->id, $versi2->id])->orderBy('nomor_versi')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('renstra_pk_versi_target')->whereIn('renstra_pk_versi_id', [$versi1->id, $versi2->id])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()];
        $sebelum = $sidikJari();
        $versi3 = $this->revisi();
        $this->assertSame(3, $versi3->nomor_versi);
        $this->assertSame($sebelum, $sidikJari());
        $auditKedua = AuditLog::where('tindakan', 'renstra_pk_versi.setujui')->where('nilai_baru->renstra_pk_versi_id', $versi3->id)->sole();
        $this->assertFalse($auditKedua->nilai_baru['versi_1_dibentuk']);
        $this->assertArrayNotHasKey('berkas_awal_ids', $auditKedua->nilai_baru);
    }

    public function test_alasan_wajib_dan_setiap_penolakan_diaudit_tepat_sekali_tanpa_file_tersisa(): void
    {
        $entri = [$this->entri($this->iku1, '85')];
        foreach (['', '   '] as $alasan) {
            $this->kirim($entri, ['alasan' => $alasan])->assertSessionHasErrors('alasan');
        }
        $this->assertSame(2, $this->penolakan('input_tidak_valid'));

        foreach (['', '   '] as $alasan) {
            $this->ditolak('alasan', $entri, ['alasan' => $alasan]);
        }
        $this->assertSame(2, $this->penolakan('aturan_revisi'));
        $this->assertSame(4, $this->penolakan());
        $this->assertSame(0, RenstraPkVersi::count());
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->kirim($entri)->assertSessionHasNoErrors()->assertRedirect("/perjanjian-kinerja/{$this->pk->id}");
        $this->assertSame(2, RenstraPkVersi::count());
    }

    public function test_revisi_setelah_tw_i_sampai_iii_disahkan_menerbitkan_snapshot_mulai_tw_iv_yang_menyalin_pendahulu(): void
    {
        $this->sahkanSampai(3, $this->iku1, $this->iku2);
        IndikatorKinerja::whereKey($this->iku1->indikator_id)->update(['nama' => 'Nama master baru', 'definisi_operasional' => 'Definisi master baru']);
        $baselineLama = $this->master($this->iku1)->getRawOriginal('baseline');

        $versi = $this->revisi([$this->entri($this->iku1, '85')]);

        $baru = $this->snapshotTerbaru($this->iku1);
        $this->assertSame([2, $this->iku1->id, $this->tw4->periode_id, 'B-12/2026 tanggal 2026-11-01', self::ALASAN, '85'],
            [$baru->nomor_versi, $baru->menggantikan_id, $baru->periode_mulai_id, $baru->rujukan_koreksi, $baru->alasan_koreksi, $this->nilai($baru->getRawOriginal('target'))]);
        $this->assertSame([$this->iku1->nama, $this->iku1->definisi, $this->iku1->presisi, $this->iku1->unit_id], [$baru->nama, $baru->definisi, $baru->presisi, $baru->unit_id]);
        $this->assertCount(2, $this->komponen($baru->id));
        $this->assertSame($this->komponen($this->iku1->id), $this->komponen($baru->id));
        $this->assertSame(1, JadwalSnapshot::where('indikator_id', $this->iku2->indikator_id)->count());
        $this->assertSame($this->tw4->periode_id, $versi->periode_mulai_id);

        $master = $this->master($this->iku1);
        $this->assertSame(['85', $this->perencanaan->id, $baselineLama], [$this->nilai($master->getRawOriginal('target_tahunan')), $master->updated_by, $master->getRawOriginal('baseline')]);

        $this->assertSame($this->iku1->id, JadwalSnapshot::versiBerlaku($this->jadwal->id, $this->iku1->indikator_id, 3)?->id);
        $this->assertSame($baru->id, JadwalSnapshot::versiBerlaku($this->jadwal->id, $this->iku1->indikator_id, 4)?->id);
        $kewajiban = app(DaftarKewajibanPengukuran::class)->query([$this->tw1->id, $this->tw2->id, $this->tw3->id, $this->tw4->id],
            DB::table('unit')->whereRaw('false')->select('id'), '2026-03-15')->where('k.indikator_id', $this->iku1->indikator_id)->pluck('periode_id')->sort()->values()->all();
        $periode = collect([$this->tw1, $this->tw2, $this->tw3, $this->tw4])->pluck('periode_id')->sort()->values()->all();
        $this->assertSame($periode, $kewajiban);

        $audit = AuditLog::where('tindakan', 'jadwal_snapshot.koreksi')->where('objek_id', $baru->id)->sole();
        $this->assertSame([$versi->id, []], [$audit->nilai_baru['renstra_pk_versi_id'], $audit->nilai_baru['pengukuran_sebelum_periode_mulai']]);
    }

    public function test_periode_mulai_mengikuti_indikator_lain_yang_dikembalikan_walau_periode_itu_sudah_disahkan_untuk_indikator_yang_diubah(): void
    {
        $this->sahkanSampai(1, $this->iku1, $this->iku2);
        $tw2Iku1 = $this->header($this->iku1, $this->tw2);
        $this->header($this->iku2, $this->tw2, 'dikembalikan');

        $versi = $this->revisi([$this->entri($this->iku1, '85')]);

        $this->assertSame([$this->tw2->periode_id, $this->tw2->periode_id], [$versi->periode_mulai_id, $this->snapshotTerbaru($this->iku1)->periode_mulai_id]);
        $this->assertSame($this->iku1->id, $tw2Iku1->fresh()->jadwal_snapshot_id);
    }

    public function test_indikator_tanpa_header_menjadikan_periode_itu_belum_dilaporkan(): void
    {
        $this->sahkanSampai(2, $this->iku1);

        $this->revisi([$this->entri($this->iku1, '85')]);

        $this->assertSame($this->tw1->periode_id, $this->snapshotTerbaru($this->iku1)->periode_mulai_id);
    }

    public function test_indikator_arsip_dan_indikator_tengah_tahun_tidak_menarik_periode_mulai_ke_periode_sebelumnya(): void
    {
        $this->assertNotSame('disahkan', $this->pengukuran->fresh()->status_alur);
        $this->terjadwal('IKU-3', '50', mulai: $this->tw3);
        $this->sahkanSampai(2, $this->iku1, $this->iku2);

        $versi = $this->revisi([$this->entri($this->iku1, '85')]);

        $this->assertSame([$this->tw3->periode_id, $this->tw3->periode_id], [$versi->periode_mulai_id, $this->snapshotTerbaru($this->iku1)->periode_mulai_id]);
    }

    public function test_semua_periode_disahkan_menolak_pengubah_target_tetapi_menerima_revisi_dokumen(): void
    {
        $this->sahkanSampai(4, $this->iku1, $this->iku2);

        $galat = $this->ditolak('target', [$this->entri($this->iku1, '85')]);
        $this->assertSame([SetujuiRevisiPerjanjianKinerja::PESAN_TERLAPOR], $galat->errors()['target']);

        $versi = $this->revisi();
        $this->assertSame([2, null], [$versi->nomor_versi, $versi->periode_mulai_id]);
    }

    public function test_jendela_tw_iv_yang_sudah_lewat_tidak_menutup_periode_yang_belum_disahkan(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 20));
        $this->sahkanSampai(3, $this->iku1, $this->iku2);

        $this->revisi([$this->entri($this->iku1, '85')]);

        $this->assertSame($this->tw4->periode_id, $this->snapshotTerbaru($this->iku1)->periode_mulai_id);
    }

    public function test_periode_mulai_tidak_lebih_awal_dari_periode_mulai_snapshot_terbaru(): void
    {
        $koreksi = $this->koreksiSnapshot($this->iku2, '60', $this->tw4);
        $this->sahkanSampai(2, $this->iku1, $this->iku2);

        $versi = $this->revisi([$this->entri($this->iku2, '65')]);

        $baru = $this->snapshotTerbaru($this->iku2);
        $this->assertSame($this->tw3->periode_id, $versi->periode_mulai_id);
        $this->assertSame([3, $koreksi->id, $this->tw4->periode_id], [$baru->nomor_versi, $baru->menggantikan_id, $baru->periode_mulai_id]);
    }

    public function test_indikator_dinilai_tahunan_tanpa_header_ikut_menentukan_periode_mulai(): void
    {
        $tahunan = $this->terjadwal('IKU-T', '30', ['dinilai_tahunan' => true]);
        $this->assertTrue($tahunan->dinilai_tahunan);
        $this->sahkanSampai(3, $this->iku1, $this->iku2);

        $this->revisi([$this->entri($this->iku1, '85')]);

        $this->assertSame($this->tw1->periode_id, $this->snapshotTerbaru($this->iku1)->periode_mulai_id);
    }

    public function test_kenaikan_periode_mulai_memerlukan_konfirmasi_pengukuran_belum_disahkan_sebelumnya(): void
    {
        $this->koreksiSnapshot($this->iku2, '60', $this->tw4);
        $this->sahkanSampai(2, $this->iku1, $this->iku2);
        $draf = $this->header($this->iku2, $this->tw3, 'draft');
        $entri = [$this->entri($this->iku2, '65')];

        $this->ditolak('konfirmasi_pengukuran_sebelumnya', $entri);
        $this->assertSame(1, $this->penolakan('aturan_revisi'));
        $this->assertSame(0, RenstraPkVersi::count());

        $this->revisi($entri, ['konfirmasi_pengukuran_sebelumnya' => true]);
        $baru = $this->snapshotTerbaru($this->iku2);
        $this->assertSame($this->tw4->periode_id, $baru->periode_mulai_id);
        $audit = AuditLog::where('tindakan', 'jadwal_snapshot.koreksi')->where('objek_id', $baru->id)->sole();
        $this->assertSame([$draf->id], $audit->nilai_baru['pengukuran_sebelum_periode_mulai']);
    }

    public function test_revisi_dokumen_lewat_multipart_tanpa_key_target_memakai_target_snapshot_terbaru(): void
    {
        $this->master($this->iku2)->update(['target_tahunan' => '62']);
        $jumlahSnapshot = JadwalSnapshot::count();

        $this->kirim([], ['expected_nomor_versi' => '0'])->assertSessionHasNoErrors()->assertRedirect("/perjanjian-kinerja/{$this->pk->id}");

        $versi = RenstraPkVersi::where('nomor_versi', 2)->sole();
        $this->assertEquals([$this->iku1->indikator_id => '80', $this->iku2->indikator_id => '60'], $this->targetVersi($versi));
        $this->assertSame('60.000000000000', DB::table('renstra_pk_versi_target')->where('renstra_pk_versi_id', $versi->id)
            ->where('indikator_id', $this->iku2->indikator_id)->value('nilai'));
        $this->assertSame($jumlahSnapshot, JadwalSnapshot::count());
        $this->assertSame(['80', '62'], [$this->nilaiMaster($this->iku1), $this->nilaiMaster($this->iku2)]);
        $audit = AuditLog::where('tindakan', 'renstra_pk_versi.setujui')->sole();
        $this->assertSame([[], []], [$audit->nilai_baru['target'], $audit->nilai_baru['snapshot_terbit']]);
    }

    public function test_entri_sama_dengan_snapshot_terbaru_dibuang_walau_berbeda_dari_master_dan_tanpa_cek_deviasi(): void
    {
        $this->master($this->iku2)->update(['target_tahunan' => '62']);
        // 60 di bawah target Renstra 70, sehingga akan ditolak bila deviasi diperiksa.
        TargetRenstra::create(['indikator_id' => $this->iku2->indikator_id, 'tahun' => 2026, 'nilai' => '70', 'updated_by' => $this->actor->id]);

        $versi = $this->revisi([$this->entri($this->iku2, '60.000')]);

        $this->assertSame('60', $this->targetVersi($versi)[$this->iku2->indikator_id]);
        $this->assertSame('62', $this->nilaiMaster($this->iku2));
        $this->assertSame(1, JadwalSnapshot::where('indikator_id', $this->iku2->indikator_id)->count());
    }

    public function test_entri_berpresisi_lebih_yang_sama_dengan_snapshot_dibuang_bukan_ditolak(): void
    {
        $this->koreksiSnapshot($this->iku2, '76.25', $this->tw1, ['presisi' => 1]);

        $versi = $this->revisi([$this->entri($this->iku2, '76.250')]);

        $this->assertSame('76.25', $this->targetVersi($versi)[$this->iku2->indikator_id]);
        $this->assertSame(2, JadwalSnapshot::where('indikator_id', $this->iku2->indikator_id)->count());
    }

    public function test_setiap_izin_yang_ditolak_menolak_revisi_dengan_403_dan_satu_audit(): void
    {
        foreach (SetujuiRevisiPerjanjianKinerja::IZIN as $izin) {
            foreach ([[$this->entri($this->iku1, '85')], []] as $target) {
                $aktor = $this->userWithRole('perencanaan');
                $this->tolak($aktor, $izin);

                $response = $this->kirim($target, [], $aktor)->assertForbidden();

                $this->assertSame(StoreVersiPerjanjianKinerjaRequest::PESAN_IZIN, $response->exception?->getMessage());
                $audit = AuditLog::where('tindakan', 'renstra_pk_versi.setujui_ditolak')->where('actor_id', $aktor->id)->sole();
                $this->assertSame([$this->pk->id, 'izin_ditolak'], [$audit->objek_id, $audit->nilai_baru['alasan_penolakan']]);
                $this->assertEqualsCanonicalizing(SetujuiRevisiPerjanjianKinerja::IZIN, array_keys($audit->dasar_izin));
            }
        }

        // Izin dicabut setelah FormRequest lolos: Action membaca ulang keputusan di transaksi.
        $aktor = $this->userWithRole('perencanaan');
        $this->tolak($aktor, PermissionCodes::BERKAS_UPLOAD);
        try {
            $this->revisi([], [], $aktor);
            $this->fail('Revisi tanpa izin unggah seharusnya ditolak.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(StoreVersiPerjanjianKinerjaRequest::PESAN_IZIN, $exception->getMessage());
        }
        $audit = AuditLog::where('tindakan', 'renstra_pk_versi.setujui_ditolak')->where('actor_id', $aktor->id)->sole();
        $this->assertSame('izin_ditolak', $audit->nilai_baru['alasan_penolakan']);
        $this->assertEqualsCanonicalizing(SetujuiRevisiPerjanjianKinerja::IZIN, array_keys($audit->dasar_izin));
        $this->assertSame(0, RenstraPkVersi::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_pk_yang_belum_dikunci_jadwal_ditolak(): void
    {
        $pkLain = RenstraPk::create(['renstra_id' => $this->pk->renstra_id, 'tahun' => 2027, 'nomor_pk' => 'PK-2027', 'tanggal_pk' => '2027-01-02', 'created_by' => $this->actor->id]);

        try {
            app(SetujuiRevisiPerjanjianKinerja::class)->handle($this->perencanaan, $pkLain->id, $this->payload());
            $this->fail('PK yang belum dikunci jadwal seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(['PK belum dikunci jadwal. Ubah PK lewat form edit dan editor target.'], $exception->errors()['pk']);
        }
        $this->assertSame(1, $this->penolakan('aturan_revisi'));
    }

    public function test_jadwal_ditutup_menolak_pengubah_target_tetapi_menerima_revisi_dokumen(): void
    {
        DB::table('jadwal_tahunan')->where('id', $this->jadwal->id)->update(['status' => 'ditutup', 'closed_at' => now()]);

        $galat = $this->ditolak('target', [$this->entri($this->iku1, '85')]);

        $this->assertSame([SetujuiRevisiPerjanjianKinerja::PESAN_JADWAL], $galat->errors()['target']);
        $this->assertSame(2, $this->revisi()->nomor_versi);
    }

    public function test_sesi_koreksi_aktif_menolak_pengubah_target(): void
    {
        $this->jadwal->update(['koreksi_mulai' => now()->subDay(), 'koreksi_sampai' => now()->addDay(), 'lingkup_koreksi' => [$this->pengukuran->id]]);
        $this->assertTrue($this->jadwal->fresh()->tahunDitutup());

        $galat = $this->ditolak('target', [$this->entri($this->iku1, '85')]);

        $this->assertSame([SetujuiRevisiPerjanjianKinerja::PESAN_JADWAL], $galat->errors()['target']);
    }

    public function test_renstra_nonaktif_atau_diarsipkan_menolak_pengubah_target_tetapi_menerima_revisi_dokumen(): void
    {
        foreach ([Renstra::STATUS_NONAKTIF, Renstra::STATUS_DIARSIPKAN] as $status) {
            DB::table('renstras')->where('id', $this->pk->renstra_id)->update(['status' => $status]);

            $galat = $this->ditolak('target', [$this->entri($this->iku1, '85')]);

            $this->assertSame([SetujuiRevisiPerjanjianKinerja::PESAN_RENSTRA], $galat->errors()['target']);
            $this->revisi();
        }
        $this->assertSame(3, RenstraPkVersi::max('nomor_versi'));
    }

    public function test_deviasi_renstra_diperiksa_untuk_setiap_pengubah_target_termasuk_yang_sama_dengan_master(): void
    {
        TargetRenstra::create(['indikator_id' => $this->iku1->indikator_id, 'tahun' => 2026, 'nilai' => '90', 'updated_by' => $this->actor->id]);
        $galat = $this->ditolak('target', [$this->entri($this->iku1, '85')]);
        $this->assertSame([sprintf(SaveTargetTahunan::PESAN_DEVIASI, 'di bawah', 2026, '90')], $galat->errors()['target']);

        // Snapshot 100, master 85: revisi ke 85 tetap pengubah target, sehingga deviasi Renstra 90 diperiksa walau sama dengan master.
        $this->koreksiSnapshot($this->iku2, '100', $this->tw1);
        $this->master($this->iku2)->update(['target_tahunan' => '85']);
        TargetRenstra::create(['indikator_id' => $this->iku2->indikator_id, 'tahun' => 2026, 'nilai' => '90', 'updated_by' => $this->actor->id]);
        $this->ditolak('target', [$this->entri($this->iku2, '85')]);
        $this->assertSame(0, RenstraPkVersi::count());
    }

    public function test_validasi_nilai_presisi_dan_indikator_target(): void
    {
        $this->kirim([['indikator_id' => $this->iku1->indikator_id, 'nilai' => '']])->assertSessionHasErrors('target.0.nilai');
        $this->ditolak('target.0.nilai', [$this->entri($this->iku1, '85.123')]);

        // Entri ke-0 sama dengan nilai dasar dan dibuang; galat presisi tetap memakai indeks payload asli.
        $galat = $this->ditolak('target.1.nilai', [$this->entri($this->iku2, '60'), $this->entri($this->iku1, '85.123')]);
        $this->assertArrayNotHasKey('target.0.nilai', $galat->errors());

        $galat = $this->ditolak('target', [['indikator_id' => $this->context->indikator_id, 'nilai' => '85']]);
        $this->assertSame(['Indikator tidak termasuk PK ini.'], $galat->errors()['target']);

        $this->kirim([$this->entri($this->iku1, '85'), ['indikator_id' => strtoupper($this->iku1->indikator_id), 'nilai' => '86']])
            ->assertSessionHasErrors(['target.1.indikator_id' => 'Indikator tidak boleh diisi lebih dari sekali.']);
        $this->kirim([], ['lampiran' => []])->assertSessionHasErrors('lampiran');
        $this->assertSame(0, RenstraPkVersi::count());
    }

    public function test_field_dan_key_item_yang_tidak_didukung_ditolak_dan_diaudit(): void
    {
        $file = fn (): UploadedFile => UploadedFile::fake()->create('pk-akhir.pdf', 20, 'application/pdf');
        $this->kirim([], ['asing' => 'x'])->assertSessionHasErrors(['revisi' => StoreVersiPerjanjianKinerjaRequest::PESAN_FIELD]);
        $this->kirim([[...$this->entri($this->iku1, '85'), 'asing' => 'x']])->assertSessionHasErrors('revisi');
        $this->kirim([], ['lampiran' => [['file' => $file(), 'mode' => 'tautan']]])->assertSessionHasErrors('revisi');

        $this->assertSame(3, $this->penolakan('input_tidak_valid'));
        $this->assertSame(0, RenstraPkVersi::count());
    }

    public function test_token_versi_usang_ditolak_409_dan_token_berupa_string_diterima(): void
    {
        $this->revisi();

        $this->ditolak('expected_nomor_versi', [], ['expected_nomor_versi' => 0], 409);
        $this->assertSame(1, $this->penolakan('konflik'));

        $this->assertSame(3, $this->revisi([], ['expected_nomor_versi' => '2'])->nomor_versi);
    }

    public function test_token_dasar_usang_setelah_koreksi_snapshot_ditolak_409(): void
    {
        $token = JadwalTahunan::tokenDasar($this->jadwal->snapshotDasar());
        $this->koreksiSnapshot($this->iku2, '90', $this->tw1);

        $this->ditolak('expected_dasar', [$this->entri($this->iku1, '85')], ['expected_dasar' => $token], 409);

        $this->assertSame(1, $this->penolakan('konflik'));
        $this->assertSame(0, RenstraPkVersi::count());
    }

    public function test_editor_target_dengan_token_lama_ditolak_setelah_revisi_lalu_berhasil_tanpa_mengubah_versi_pk(): void
    {
        $editor = fn (): string => app(ShowTargetTahunan::class)->handle($this->perencanaan, $this->iku1->indikator_id, 2026, $this->pk->renstra_id)['expected_state'];
        $simpan = fn (string $token): array => app(SaveTargetTahunan::class)->handle($this->perencanaan, $this->iku1->indikator_id, 2026,
            ['baseline' => '10', 'target_tahunan' => '88', 'expected_state' => $token, 'operation_id' => (string) Str::uuid()], $this->pk->renstra_id);
        $tokenLama = $editor();
        $this->revisi([$this->entri($this->iku1, '85')]);
        $versiPk = fn (): array => [DB::table('renstra_pk_versi')->orderBy('nomor_versi')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('renstra_pk_versi_target')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()];
        $sebelum = $versiPk();

        try {
            $simpan($tokenLama);
            $this->fail('Token editor lama seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(409, $exception->status);
            $this->assertArrayHasKey('expected_state', $exception->errors());
        }

        $this->assertTrue($simpan($editor())['changed']);
        $this->assertSame('88', $this->nilaiMaster($this->iku1));
        $this->assertSame($sebelum, $versiPk());
    }

    public function test_kegagalan_setelah_lampiran_dan_snapshot_ditulis_membatalkan_semua_dan_menghapus_file(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION tolak_audit_revisi_uji() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.tindakan = 'renstra_pk_versi.setujui' THEN
                    RAISE EXCEPTION 'Kegagalan disuntik test';
                END IF;
                RETURN NEW;
            END $$;
            CREATE TRIGGER tolak_audit_revisi_uji BEFORE INSERT ON audit_log FOR EACH ROW EXECUTE FUNCTION tolak_audit_revisi_uji();
            SQL);
        $jumlahSnapshot = JadwalSnapshot::count();

        try {
            $this->revisi([$this->entri($this->iku1, '85')]);
            $this->fail('Kegagalan audit seharusnya membatalkan revisi.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Kegagalan disuntik test', $exception->getMessage());
        }

        $this->assertSame([0, $jumlahSnapshot, '80'], [RenstraPkVersi::count(), JadwalSnapshot::count(), $this->nilaiMaster($this->iku1)]);
        $this->assertSame([], Storage::disk('local')->allFiles('berkas/renstra_pk_versi'));
    }

    private function kirim(array $target = [], array $override = [], ?User $aktor = null): TestResponse
    {
        return $this->actingAs($aktor ?? $this->perencanaan)->from("/perjanjian-kinerja/{$this->pk->id}")
            ->post("/perjanjian-kinerja/{$this->pk->id}/versi", $this->payload($target, $override));
    }

    /** Action menolak dengan key dan status yang diharapkan. */
    private function ditolak(string $key, array $target = [], array $override = [], int $status = 422): ValidationException
    {
        try {
            $this->revisi($target, $override);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
            $this->assertSame($status, $exception->status);

            return $exception;
        }
        $this->fail("Revisi seharusnya ditolak pada {$key}.");
    }

    private function penolakan(?string $alasan = null): int
    {
        return AuditLog::where('tindakan', 'renstra_pk_versi.setujui_ditolak')
            ->when($alasan !== null, fn ($query) => $query->where('nilai_baru->alasan_penolakan', $alasan))->count();
    }

    private function tolak(User $user, string $permission): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id,
            'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => null, 'alasan' => 'Fixture deny',
            'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    private function snapshotTerbaru(JadwalSnapshot $snapshot): JadwalSnapshot
    {
        return JadwalSnapshot::where('jadwal_id', $snapshot->jadwal_id)->where('indikator_id', $snapshot->indikator_id)->orderByDesc('nomor_versi')->firstOrFail();
    }

    private function master(JadwalSnapshot $snapshot): TargetKinerja
    {
        return TargetKinerja::where('indikator_kinerja_id', $snapshot->indikator_id)->where('tahun', 2026)->sole();
    }

    private function nilaiMaster(JadwalSnapshot $snapshot): ?string
    {
        return $this->nilai($this->master($snapshot)->getRawOriginal('target_tahunan'));
    }

    private function nilai(mixed $raw): ?string
    {
        return TargetTahunanDecimal::normalize($raw === null ? null : (string) $raw);
    }

    /** @return array<string, string|null> indikator_id => nilai ternormalisasi */
    private function targetVersi(RenstraPkVersi $versi): array
    {
        return DB::table('renstra_pk_versi_target')->where('renstra_pk_versi_id', $versi->id)->pluck('nilai', 'indikator_id')
            ->map(fn ($nilai): ?string => $this->nilai($nilai))->all();
    }

    /** @return list<array<string, mixed>> */
    private function komponen(string $snapshotId): array
    {
        return DB::table('jadwal_snapshot_komponen')->where('jadwal_snapshot_id', $snapshotId)->orderBy('kode')
            ->get(['komponen_id', 'kode', 'label', 'peran', 'bobot', 'urutan'])->map(fn ($row) => (array) $row)->all();
    }
}
