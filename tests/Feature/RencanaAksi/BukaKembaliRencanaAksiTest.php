<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\RencanaAksi\KembalikanRencanaAksi;
use App\Actions\RencanaAksi\SahkanRencanaAksi;
use App\Actions\RencanaAksi\VerifikasiRencanaAksi;
use App\Events\RencanaAksiDibukaKembali;
use App\Events\RencanaAksiDikembalikan;
use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Buka-kembali disahkan → dikembalikan dengan alasan wajib (Q43 §43.2),
 * sebelum penutupan atau di dalam sesi koreksi yang sah. Kunci per periode
 * (Q41 §41.2) ditegakkan pada simpan/ajukan PIC setelah dibuka kembali;
 * Perencanaan dikecualikan sampai penutupan.
 */
class BukaKembaliRencanaAksiTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase, RencanaAksiFixtures;

    private const DITUTUP = 'Tahun jadwal telah ditutup; review memerlukan sesi koreksi resmi untuk indikator ini.';

    private RencanaAksiVersi $versiPengajuan;

    private User $verifikator;

    private User $pengesah;

    private User $pembuka;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        $this->verifikator = $this->createUserWithRole('perencanaan');
        $this->pengesah = $this->createUserWithRole('perencanaan');
        $this->pembuka = $this->createUserWithRole('perencanaan');
        // Versi 1 diajukan jalur PIC oleh aktor fixture (superadmin sebagai PJ).
        $this->versiPengajuan = $this->createRencanaAksiSubmission();
    }

    private function verifikasi(): void
    {
        app(VerifikasiRencanaAksi::class)->handle($this->verifikator, $this->rencanaAksi->id, $this->tokens());
    }

    private function sahkan(): void
    {
        app(SahkanRencanaAksi::class)->handle($this->pengesah, $this->rencanaAksi->id, $this->tokens());
    }

    /** @return array{expected_versi: int, versi_pengajuan_id: string} */
    private function tokens(): array
    {
        return ['expected_versi' => $this->rencanaAksi->fresh()->versi, 'versi_pengajuan_id' => $this->versiPengajuan->id];
    }

    /** @param array<string, mixed>|null $data */
    private function bukaKembali(User $aktor, ?array $data = null): TestResponse
    {
        return $this->actingAs($aktor)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/buka-kembali", $data ?? $this->tokens() + ['alasan' => 'Koreksi resmi target periode.']);
    }

    public function test_alasan_valid_dan_jadwal_aktif_memindahkan_header_ke_dikembalikan_dengan_audit(): void
    {
        $this->verifikasi();
        $this->sahkan();

        $this->bukaKembali($this->pembuka)->assertOk()->assertJson(['status_alur' => 'dikembalikan', 'versi' => 4]);

        $header = $this->rencanaAksi->fresh();
        $this->assertSame('dikembalikan', $header->status_alur);
        $this->assertSame(4, $header->versi);
        $audit = AuditLog::where('tindakan', 'rencana_aksi.buka_kembali')->sole();
        $this->assertSame(PermissionCodes::RENCANA_AKSI_BUKA_KEMBALI, $audit->dasar_izin['permission']);
        $this->assertSame('diizinkan', $audit->dasar_izin['keputusan']);
        $this->assertSame('Koreksi resmi target periode.', $audit->alasan);
        $this->assertSame('dikembalikan', $audit->nilai_baru['status_alur']);
        $this->assertSame($this->versiPengajuan->id, $audit->nilai_baru['versi_pengajuan_id']);
        $this->assertFalse($audit->nilai_baru['self_approval']);
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('RencanaAksi/Review')->where('rencanaAksi.status_alur', 'dikembalikan')
            ->where('rencanaAksi.can.buka_kembali', false)
            ->where('rencanaAksi.dibuka_kembali', true));
    }

    public function test_event_baru_dibuka_kembali_didispatch_dengan_flag_dari_status(): void
    {
        $this->verifikasi();
        $this->sahkan();
        Event::fake([RencanaAksiDibukaKembali::class, RencanaAksiDikembalikan::class]);

        $this->bukaKembali($this->pembuka)->assertOk();

        Event::assertDispatched(RencanaAksiDibukaKembali::class, function ($event): bool {
            return $event->rencanaAksiId === $this->rencanaAksi->id
                && $event->versiPengajuanId === $this->versiPengajuan->id
                && $event->aktorId === $this->pembuka->id
                && $event->alasan === 'Koreksi resmi target periode.'
                && $event->dariStatus === 'disahkan';
        });
        Event::assertNotDispatched(RencanaAksiDikembalikan::class);
    }

    public function test_pengesah_boleh_membuka_kembali_pengesahannya_sendiri(): void
    {
        $this->verifikasi();
        $this->sahkan();

        $this->bukaKembali($this->pengesah)->assertOk()->assertJson(['status_alur' => 'dikembalikan', 'versi' => 4]);

        $this->assertSame('dikembalikan', $this->rencanaAksi->fresh()->status_alur);
        $audit = AuditLog::where('tindakan', 'rencana_aksi.buka_kembali')->sole();
        $this->assertSame('Koreksi resmi target periode.', $audit->alasan);
        $this->assertSame(PermissionCodes::RENCANA_AKSI_BUKA_KEMBALI, $audit->dasar_izin['permission']);
    }

    public function test_tanpa_alasan_ditolak_status_tetap_disahkan_dan_diaudit(): void
    {
        $this->verifikasi();
        $this->sahkan();

        // Q43 §43.2: alasan buka-kembali WAJIB — hilang, string kosong, atau
        // spasi-only sama-sama 422 dan tercatat sebagai penolakan.
        $this->actingAs($this->pembuka)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/buka-kembali", $this->tokens())
            ->assertUnprocessable()->assertJsonValidationErrors('alasan');
        $this->actingAs($this->pembuka)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/buka-kembali", $this->tokens() + ['alasan' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('alasan');
        $this->actingAs($this->pembuka)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/buka-kembali", $this->tokens() + ['alasan' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('alasan');

        $this->assertSame('disahkan', $this->rencanaAksi->fresh()->status_alur);
        $this->assertSame(3, AuditLog::where('tindakan', 'rencana_aksi.buka_kembali_ditolak')->count());
    }

    public function test_dengan_alasan_tersimpan_disanitasi(): void
    {
        $this->verifikasi();
        $this->sahkan();

        $this->actingAs($this->pembuka)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/buka-kembali", $this->tokens() + ['alasan' => '  Koreksi resmi target periode.  '])
            ->assertOk();
        $this->assertSame('Koreksi resmi target periode.', AuditLog::where('tindakan', 'rencana_aksi.buka_kembali')->sole()->alasan);
    }

    public function test_alasan_tidak_valid_tetap_ditolak(): void
    {
        $this->verifikasi();
        $this->sahkan();

        foreach ([str_repeat('a', 1001), "Rusak\0teks", ['bukan string']] as $alasan) {
            $this->actingAs($this->pembuka)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/buka-kembali", $this->tokens() + ['alasan' => $alasan])
                ->assertUnprocessable()->assertJsonValidationErrors('alasan');
        }
        $this->assertSame('disahkan', $this->rencanaAksi->fresh()->status_alur);
    }

    public function test_klik_kedua_setelah_dikembalikan_ditolak_via_status_mismatch(): void
    {
        $this->verifikasi();
        $this->sahkan();
        $this->bukaKembali($this->pembuka)->assertOk();
        $this->assertSame('dikembalikan', $this->rencanaAksi->fresh()->status_alur);

        // Token segar pasca-buka: status sudah dikembalikan sehingga guard status menolak klik kedua.
        $this->actingAs($this->pembuka)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/buka-kembali", $this->tokens() + ['alasan' => 'Upaya kedua.'])
            ->assertUnprocessable()->assertJsonPath('errors.businessErrors', ['Status rencana aksi tidak sesuai untuk tindakan ini.']);

        $this->assertSame('dikembalikan', $this->rencanaAksi->fresh()->status_alur);
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.buka_kembali_ditolak')->count());
        $this->actingAs($this->pembuka)->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('rencanaAksi.can.buka_kembali', false));
    }

    public function test_versi_disahkan_tetap_immutable_dan_dapat_dibaca_sebagai_histori(): void
    {
        $this->verifikasi();
        $this->sahkan();
        $beku = $this->versiPengajuan->fresh()->getRawOriginal('snapshot');

        $this->bukaKembali($this->pembuka)->assertOk();

        $this->assertSame($beku, $this->versiPengajuan->fresh()->getRawOriginal('snapshot'));
        $this->assertSame(1, $this->rencanaAksi->versions()->count());
        $this->assertSame($this->pengesah->id, $this->versiPengajuan->fresh()->disahkan_by);
        $this->actingAs($this->pembuka)->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RencanaAksi/Review')->where('rencanaAksi.versi_pengajuan.id', $this->versiPengajuan->id));

        // Baris pengajuan ulang (nomor 2) membuat versi lama menjadi historis read-only.
        $this->rencanaAksi->versions()->create(array_merge(
            $this->versiPengajuan->only(['jadwal_snapshot_id', 'snapshot', 'dasar_izin_pengajuan']),
            ['nomor' => 2, 'diajukan_by' => $this->actor->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'pic'],
        ));
        $this->rencanaAksi->update(['status_alur' => 'diajukan', 'versi' => 5]);
        $this->actingAs($this->pembuka)->get("/rencana-aksi/{$this->rencanaAksi->id}/review?versi={$this->versiPengajuan->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RencanaAksi/Review')->where('rencanaAksi.versi_pengajuan.id', $this->versiPengajuan->id)
                ->where('rencanaAksi.can.buka_kembali', false)
                ->where('rencanaAksi.businessErrors.buka_kembali', ['Status rencana aksi tidak sesuai untuk tindakan ini.', 'Versi historis hanya dapat dibaca; review memakai pengajuan terbaru.']));
    }

    public function test_pic_tanpa_izin_buka_ditolak_dan_menulis_tanpa_jendela_penyusunan_diterima(): void
    {
        $this->verifikasi();
        $this->sahkan();
        $pic = $this->createPicPegawai();

        $this->bukaKembali($pic)->assertForbidden();
        $this->assertSame('disahkan', $this->rencanaAksi->fresh()->status_alur);
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.buka_kembali_ditolak')->count());

        // Tidak ada jendela penyusunan — kolom lama yang sudah lewat tidak
        // dibaca. PIC boleh menulis periode yang belum terkunci sebelum
        // penutupan selama grant + PIC efektif valid.
        $this->jadwal->update(['rencana_aksi_mulai' => '2026-02-01', 'rencana_aksi_selesai' => '2026-02-28']);
        $this->bukaKembali($this->pembuka)->assertOk();

        $this->travelTo(now()->setDate(2026, 3, 20)->setTime(9, 0));
        $this->actingAs($pic)->post("/rencana-aksi/{$this->rencanaAksi->id}/target", [
            'expected_versi' => $this->rencanaAksi->fresh()->versi,
            'expected_snapshot_id' => $this->snapshot->id,
            'expected_snapshot_versi' => 1,
            'targets' => [['periode_id' => $this->snapshot->periode_mulai_id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(5, $this->rencanaAksi->fresh()->versi);
    }

    /**
     * Kunci per periode (Q41 §41.2): setelah dibuka kembali, PIC yang
     * menyimpan periode yang sudah terkunci ditolak 422 dengan pesan
     * spesifik per periode dan diaudit; periode belum terkunci diterima dan
     * Perencanaan dikecualikan sampai penutupan. Pengajuan PIC hanya menolak
     * bila nilai periode terkunci berubah dibanding versi sah terakhir —
     * ubah periode terbuka lalu ajukan ulang tanpa menyentuh periode
     * terkunci BERHASIL.
     */
    public function test_kunci_periode_terkunci_pic_ditolak_422_perencanaan_dikecualikan(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versi = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokens = fn (): array => ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $versi->id];
        app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        app(SahkanRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        $pembuka = $this->createUserWithRole('perencanaan');
        $this->actingAs($pembuka)->postJson("/rencana-aksi/{$header->id}/buka-kembali", $tokens() + ['alasan' => 'Koreksi resmi target periode belum terkunci.'])->assertOk();
        $dibuka = $header->fresh()->versi;

        // TW I terkunci bagi PIC, TW II masih terbuka (batas dalam urutan tanggal resmi).
        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode1']->id)->update(['pengisian_selesai' => '2026-03-05']);
        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode2']->id)->update(['pengisian_selesai' => '2026-03-20']);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $simpan = fn (User $aktor, int $versiHeader, array $targets, array $tambahan = []): TestResponse => $this->actingAs($aktor)->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $versiHeader,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => $targets,
            ...$tambahan,
        ]);
        $manual = fn ($periode, $nilai): array => ['periode_id' => $periode->id, 'komponen_id' => null, 'nilai' => $nilai, 'keterangan' => null];
        $pesanKunci = 'Periode terkunci bagi PIC setelah batas pengisian lewat: Triwulan I (batas 2026-03-05).';

        $simpan($fixture['pic'], $dibuka, [$manual($fixture['periode1'], 50)])
            ->assertSessionHasErrors(['jendela' => $pesanKunci]);
        $this->assertSame($dibuka, $header->fresh()->versi);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah_ditolak')->where('objek_id', $header->id)->exists());

        // Nilai 90 menyimpang dari target PK 100 sehingga alasan deviasi
        // wajib disertakan agar pengajuan berikutnya tidak tertolak gerbang
        // deviasi sebelum perilaku kunci sempat dibuktikan.
        $simpan($fixture['pic'], $dibuka, [$manual($fixture['periode2'], 90)], ['alasan_deviasi_pk' => 'Realisasi TW II di bawah target PK.'])->assertSessionHasNoErrors();
        $this->assertSame($dibuka + 1, $header->fresh()->versi);

        // TW I terkunci tetapi nilainya sama dengan versi sah (100) sehingga
        // pengajuan ulang yang hanya mengubah TW II terbuka BERHASIL.
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $dibuka + 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $header->fresh()->status_alur);
        $this->assertSame(2, $header->versions()->count());

        // Perencanaan tetap dikecualikan sampai penutupan (ditunjukkan pada
        // fixture segar agar tidak mengganggu status diajukan di atas).
        $kedua = $this->buatFixtureManual();
        $headerKedua = $this->drafManual($kedua);
        $this->actingAs($kedua['pic'])->post("/rencana-aksi/{$headerKedua->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versiKedua = $headerKedua->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokensKedua = fn (): array => ['expected_versi' => $headerKedua->fresh()->versi, 'versi_pengajuan_id' => $versiKedua->id];
        app(VerifikasiRencanaAksi::class)->handle($kedua['perencanaan'], $headerKedua->id, $tokensKedua());
        app(SahkanRencanaAksi::class)->handle($kedua['perencanaan'], $headerKedua->id, $tokensKedua());
        $pembukaKedua = $this->createUserWithRole('perencanaan');
        $this->actingAs($pembukaKedua)->postJson("/rencana-aksi/{$headerKedua->id}/buka-kembali", $tokensKedua() + ['alasan' => 'Koreksi Perencanaan pada periode terkunci.'])->assertOk();
        $dibukaKedua = $headerKedua->fresh()->versi;
        PeriodeJadwal::where('jadwal_id', $kedua['jadwal']->id)->where('periode_id', $kedua['periode1']->id)->update(['pengisian_selesai' => '2026-03-05']);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $this->actingAs($kedua['perencanaan'])->post("/rencana-aksi/{$headerKedua->id}/target", [
            'expected_versi' => $dibukaKedua,
            'expected_snapshot_id' => $kedua['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [$manual($kedua['periode1'], 55)],
        ])->assertSessionHasNoErrors();
        $this->assertSame($dibukaKedua + 1, $headerKedua->fresh()->versi);
    }

    /**
     * Pengajuan PIC ditolak 422 hanya bila nilai periode terkunci berubah
     * dibanding versi sah terakhir, dengan pesan spesifik per periode
     * seperti guard simpan dan audit penolakan.
     */
    public function test_ajukan_menolak_bila_nilai_periode_terkunci_berubah_dibanding_versi_sah(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture, 100, 100);
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versi = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokens = fn (): array => ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $versi->id];
        app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        app(SahkanRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        $pembuka = $this->createUserWithRole('perencanaan');
        $this->actingAs($pembuka)->postJson("/rencana-aksi/{$header->id}/buka-kembali", $tokens() + ['alasan' => 'Koreksi resmi nilai terkunci.'])->assertOk();
        $dibuka = $header->fresh()->versi;

        // PIC mengubah TW I saat masih terbuka (100 → 50), lalu TW I dikunci.
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $dibuka,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 50, 'keterangan' => null]],
        ])->assertSessionHasNoErrors();
        $diubah = $header->fresh()->versi;

        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode1']->id)->update(['pengisian_selesai' => '2026-03-05']);
        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode2']->id)->update(['pengisian_selesai' => '2026-03-20']);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $pesanKunci = 'Periode terkunci bagi PIC setelah batas pengisian lewat: Triwulan I (batas 2026-03-05).';
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $diubah])
            ->assertSessionHasErrors(['jendela' => $pesanKunci]);
        $this->assertSame($diubah, $header->fresh()->versi);
        $this->assertSame('dikembalikan', $header->fresh()->status_alur);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ajukan_ditolak')->where('objek_id', $header->id)->where('alasan', $pesanKunci)->exists());

        // Perencanaan dikecualikan: pengajuan dengan nilai yang sama tetap lolos.
        $this->actingAs($fixture['perencanaan'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $diubah])
            ->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $header->fresh()->status_alur);
    }

    /**
     * Dalam sesi koreksi pascapenutupan (Q41 §41.2 butir 6), PIC hanya boleh
     * menulis periode yang jendela pengisiannya dibuka kembali secara resmi;
     * periode lain ditolak.
     */
    public function test_sesi_koreksi_pic_hanya_boleh_periode_dibuka_resmi(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versi = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokens = fn (): array => ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $versi->id];
        app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        app(SahkanRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        $pembuka = $this->createUserWithRole('perencanaan');
        $this->actingAs($pembuka)->postJson("/rencana-aksi/{$header->id}/buka-kembali", $tokens() + ['alasan' => 'Koreksi resmi pascapenutupan.'])->assertOk();
        $dibuka = $header->fresh()->versi;

        $fixture['jadwal']->update([
            'penutupan' => '2026-03-05',
            'koreksi_mulai' => '2026-03-06 00:00:00',
            'koreksi_sampai' => '2026-03-31 23:59:59',
            'lingkup_koreksi' => [
                'jenis_objek' => ['rencana_aksi'],
                'indikator_ids' => [$fixture['indikator']->id],
                'periode_ids' => [$fixture['periode2']->id],
            ],
        ]);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $dibuka,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 95, 'keterangan' => null]],
        ])->assertSessionHasNoErrors();
        $this->assertSame($dibuka + 1, $header->fresh()->versi);

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $dibuka + 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 95, 'keterangan' => null]],
        ])->assertSessionHasErrors(['jendela' => 'Tahun jadwal telah ditutup; penyimpanan memerlukan sesi koreksi resmi.']);
        $this->assertSame($dibuka + 1, $header->fresh()->versi);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah_ditolak')->where('objek_id', $header->id)->exists());
    }

    public function test_props_koreksi_tetap_mengunci_periode_sampai_jendela_pengisian_dibuka_resmi(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versi = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokens = fn (): array => ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $versi->id];
        app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        app(SahkanRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        $pembuka = $this->createUserWithRole('perencanaan');
        $this->actingAs($pembuka)->postJson("/rencana-aksi/{$header->id}/buka-kembali", $tokens() + ['alasan' => 'Koreksi resmi pascapenutupan.'])->assertOk();

        // Kedua periode melewati batas pengisian; sesi koreksi tidak membuka
        // akses PIC tanpa pembukaan jendela pengisian resmi (Q19, Q41 §41.2 butir 6).
        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->update(['pengisian_selesai' => '2026-03-05']);
        $fixture['jadwal']->update([
            'penutupan' => '2026-03-05',
            'koreksi_mulai' => '2026-03-06 00:00:00',
            'koreksi_sampai' => '2026-03-31 23:59:59',
            'lingkup_koreksi' => [
                'jenis_objek' => ['rencana_aksi'],
                'indikator_ids' => [$fixture['indikator']->id],
                'periode_ids' => [$fixture['periode2']->id],
            ],
        ]);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rencanaAksi.koreksi.aktif', true)
                ->where('rencanaAksi.periode_terkunci', [$fixture['periode1']->id, $fixture['periode2']->id])
                ->where('rencanaAksi.can.update', false));

        // Pembukaan jendela TW II di dalam sesi koreksi (ISS-14.03) disimulasikan langsung.
        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode2']->id)->update(['pengisian_selesai' => '2026-03-20']);
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rencanaAksi.periode_terkunci', [$fixture['periode1']->id])
                ->where('rencanaAksi.can.update', true));
    }

    public function test_penutupan_tanpa_sesi_koreksi_menolak_buka_kembali_dan_mengaudit_penolakan(): void
    {
        $this->verifikasi();
        $this->sahkan();
        $this->travelTo(now()->setDate(2027, 1, 5));

        $this->bukaKembali($this->pembuka)->assertUnprocessable()->assertJsonPath('errors.businessErrors', [self::DITUTUP]);

        $this->assertSame('disahkan', $this->rencanaAksi->fresh()->status_alur);
        $this->assertSame(3, $this->rencanaAksi->fresh()->versi);
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.buka_kembali_ditolak')->count());
    }

    public function test_pengajuan_ulang_setelah_dibuka_membuat_baris_versi_baru(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versi = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokens = fn (): array => ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $versi->id];
        app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        app(SahkanRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());

        // Buka-kembali memakai Perencanaan lain agar jejak pembuka dan
        // pengesah terpisah pada audit.
        $pembuka = $this->createUserWithRole('perencanaan');
        $this->actingAs($pembuka)->postJson("/rencana-aksi/{$header->id}/buka-kembali", $tokens() + ['alasan' => 'Koreksi resmi pasca pengesahan.'])
            ->assertOk()->assertJson(['status_alur' => 'dikembalikan']);
        $beku = $versi->fresh()->getRawOriginal('snapshot');

        // Pengajuan ulang memakai gerbang normal: baris versi baru, bukan overwrite versi disahkan.
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $header->fresh()->versi])->assertSessionHasNoErrors();

        $this->assertSame('diajukan', $header->fresh()->status_alur);
        $this->assertSame(2, $header->versions()->count());
        $baru = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $this->assertSame(2, $baru->nomor);
        $this->assertSame($beku, $versi->fresh()->getRawOriginal('snapshot'));
    }

    /**
     * Setelah buka-kembali lalu ganti/hapus bukti pada status dikembalikan,
     * unduhan bukti versi resmi lama tetap 200 dengan isi beku yang benar;
     * bila file fisiknya dihapus menjadi 404. `download_url` versi historis
     * tetap diberikan selama file fisiknya ada.
     */
    public function test_unduhan_bukti_versi_sah_lama_tetap_200_setelah_ganti_hapus_dan_404_bila_file_hilang(): void
    {
        Storage::fake('local');
        $fixture = $this->buatFixtureManual();
        $header = $this->buatDraf($fixture);

        Storage::disk('local')->put('berkas/rencana_aksi/lama.pdf', 'ISI LAMA BENAR');
        $buktiLama = BuktiDukung::create([
            'berkasable_type' => 'rencana_aksi', 'berkasable_id' => $header->id, 'mode' => 'file',
            'nama_asli' => 'lama.pdf', 'path' => 'berkas/rencana_aksi/lama.pdf', 'mime' => 'application/pdf', 'ukuran_bytes' => 14,
            'uploaded_by' => $fixture['pic']->id, 'created_at' => now(),
        ]);

        $this->simpan($fixture, $header, 1, [$this->manual($fixture['periode1'], 100), $this->manual($fixture['periode2'], 100)]);
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versiSah = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokens = fn (): array => ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $versiSah->id];
        app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        app(SahkanRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        $pembuka = $this->createUserWithRole('perencanaan');
        $this->actingAs($pembuka)->postJson("/rencana-aksi/{$header->id}/buka-kembali", $tokens() + ['alasan' => 'Koreksi bukti pendukung.'])->assertOk();

        // Ganti/hapus pada status dikembalikan: hapus bukti lama lalu unggah baru.
        $this->actingAs($fixture['pic'])->delete(
            route('rencana-aksi.bukti.destroy', ['rencanaAksi' => $header->id, 'bukti' => $buktiLama->id]),
            ['alasan' => 'Dokumen lama diganti versi terbaru.']
        )->assertSessionHasNoErrors();
        $this->assertNotNull($buktiLama->fresh()->dihapus_pada);
        $this->actingAs($fixture['pic'])->post(route('rencana-aksi.bukti.store', $header->id), [
            'mode' => 'file', 'file' => UploadedFile::fake()->create('baru.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $urlLama = route('rencana-aksi.versi.bukti.download', [$header->id, $versiSah->id, $buktiLama->id]);
        $this->actingAs($fixture['perencanaan'])->get($urlLama)->assertOk()->assertStreamedContent('ISI LAMA BENAR');

        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}/review?versi={$versiSah->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rencanaAksi.bukti_dukungs.0.id', $buktiLama->id)
                ->where('rencanaAksi.bukti_dukungs.0.download_url', $urlLama));

        Storage::disk('local')->delete('berkas/rencana_aksi/lama.pdf');

        $this->actingAs($fixture['perencanaan'])->get($urlLama)->assertNotFound();
        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}/review?versi={$versiSah->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rencanaAksi.bukti_dukungs.0.id', $buktiLama->id)
                ->where('rencanaAksi.bukti_dukungs.0.download_url', null));
    }

    public function test_capability_campuran_terkunci_sebagian_tetap_menawarkan_aksi(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versi = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokens = fn (): array => ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $versi->id];
        app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        app(SahkanRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        $pembuka = $this->createUserWithRole('perencanaan');
        $this->actingAs($pembuka)->postJson("/rencana-aksi/{$header->id}/buka-kembali", $tokens() + ['alasan' => 'Koreksi campuran terkunci sebagian.'])->assertOk();

        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode1']->id)->update(['pengisian_selesai' => '2026-03-05']);
        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode2']->id)->update(['pengisian_selesai' => '2026-03-20']);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        // Draf masih selaras dengan versi sah pada periode terkunci sehingga
        // capability tetap menawarkan ubah dan ajukan beserta daftar kunci.
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rencanaAksi.can.update', true)
                ->where('rencanaAksi.can.submit', true)
                ->where('rencanaAksi.periode_terkunci', [$fixture['periode1']->id]));
        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rencanaAksi.can.update', true)
                ->where('rencanaAksi.can.submit', true)
                ->where('rencanaAksi.periode_terkunci', []));

        // Perubahan pada periode terkunci membuat pengajuan tidak ditawarkan,
        // tetapi ubah tetap ditawarkan karena periode terbuka masih ada.
        $kedua = $this->buatFixtureManual();
        $headerKedua = $this->drafManual($kedua, 100, 100);
        $this->actingAs($kedua['pic'])->post("/rencana-aksi/{$headerKedua->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versiKedua = $headerKedua->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokensKedua = fn (): array => ['expected_versi' => $headerKedua->fresh()->versi, 'versi_pengajuan_id' => $versiKedua->id];
        app(VerifikasiRencanaAksi::class)->handle($kedua['perencanaan'], $headerKedua->id, $tokensKedua());
        app(SahkanRencanaAksi::class)->handle($kedua['perencanaan'], $headerKedua->id, $tokensKedua());
        $pembukaKedua = $this->createUserWithRole('perencanaan');
        $this->actingAs($pembukaKedua)->postJson("/rencana-aksi/{$headerKedua->id}/buka-kembali", $tokensKedua() + ['alasan' => 'Koreksi campuran berubah terkunci.'])->assertOk();
        $dibukaKedua = $headerKedua->fresh()->versi;
        $this->actingAs($kedua['pic'])->post("/rencana-aksi/{$headerKedua->id}/target", [
            'expected_versi' => $dibukaKedua,
            'expected_snapshot_id' => $kedua['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [['periode_id' => $kedua['periode1']->id, 'komponen_id' => null, 'nilai' => 50, 'keterangan' => null]],
        ])->assertSessionHasNoErrors();
        PeriodeJadwal::where('jadwal_id', $kedua['jadwal']->id)->where('periode_id', $kedua['periode1']->id)->update(['pengisian_selesai' => '2026-03-05']);
        PeriodeJadwal::where('jadwal_id', $kedua['jadwal']->id)->where('periode_id', $kedua['periode2']->id)->update(['pengisian_selesai' => '2026-03-20']);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($kedua['pic'])->get("/rencana-aksi/{$headerKedua->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rencanaAksi.can.update', true)
                ->where('rencanaAksi.can.submit', false)
                ->where('rencanaAksi.periode_terkunci', [$kedua['periode1']->id]));
    }

    public function test_simpan_periode_terkunci_nilai_sama_diterima_dan_kembali_ke_sah_diterima(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versi = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokens = fn (): array => ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $versi->id];
        app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        app(SahkanRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        $pembuka = $this->createUserWithRole('perencanaan');
        $this->actingAs($pembuka)->postJson("/rencana-aksi/{$header->id}/buka-kembali", $tokens() + ['alasan' => 'Koreksi nilai sama terkunci.'])->assertOk();
        $dibuka = $header->fresh()->versi;

        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode1']->id)->update(['pengisian_selesai' => '2026-03-05']);
        PeriodeJadwal::where('jadwal_id', $fixture['jadwal']->id)->where('periode_id', $fixture['periode2']->id)->update(['pengisian_selesai' => '2026-03-20']);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        // Periode terkunci dengan nilai sama seperti tersimpan diterima
        // sehingga matriks penuh dari UI tetap bisa menyimpan periode terbuka.
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $dibuka,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'alasan_deviasi_pk' => 'Realisasi TW II di bawah target PK.',
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 100, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 90, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame($dibuka + 1, $header->fresh()->versi);

        // Pengembalian ke nilai versi sah terakhir juga diterima agar jalan
        // buntu koreksi dapat diselesaikan PIC.
        $kedua = $this->buatFixtureManual();
        $headerKedua = $this->drafManual($kedua, 100, 100);
        $this->actingAs($kedua['pic'])->post("/rencana-aksi/{$headerKedua->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versiKedua = $headerKedua->versions()->reorder('nomor', 'desc')->firstOrFail();
        $tokensKedua = fn (): array => ['expected_versi' => $headerKedua->fresh()->versi, 'versi_pengajuan_id' => $versiKedua->id];
        app(VerifikasiRencanaAksi::class)->handle($kedua['perencanaan'], $headerKedua->id, $tokensKedua());
        app(SahkanRencanaAksi::class)->handle($kedua['perencanaan'], $headerKedua->id, $tokensKedua());
        $pembukaKedua = $this->createUserWithRole('perencanaan');
        $this->actingAs($pembukaKedua)->postJson("/rencana-aksi/{$headerKedua->id}/buka-kembali", $tokensKedua() + ['alasan' => 'Koreksi kembali ke sah.'])->assertOk();
        $dibukaKedua = $headerKedua->fresh()->versi;
        $this->actingAs($kedua['pic'])->post("/rencana-aksi/{$headerKedua->id}/target", [
            'expected_versi' => $dibukaKedua,
            'expected_snapshot_id' => $kedua['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [['periode_id' => $kedua['periode1']->id, 'komponen_id' => null, 'nilai' => 50, 'keterangan' => null]],
        ])->assertSessionHasNoErrors();
        $diubahKedua = $headerKedua->fresh()->versi;
        PeriodeJadwal::where('jadwal_id', $kedua['jadwal']->id)->where('periode_id', $kedua['periode1']->id)->update(['pengisian_selesai' => '2026-03-05']);
        PeriodeJadwal::where('jadwal_id', $kedua['jadwal']->id)->where('periode_id', $kedua['periode2']->id)->update(['pengisian_selesai' => '2026-03-20']);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($kedua['pic'])->post("/rencana-aksi/{$headerKedua->id}/target", [
            'expected_versi' => $diubahKedua,
            'expected_snapshot_id' => $kedua['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [['periode_id' => $kedua['periode1']->id, 'komponen_id' => null, 'nilai' => 100, 'keterangan' => null]],
        ])->assertSessionHasNoErrors();
        $this->assertSame($diubahKedua + 1, $headerKedua->fresh()->versi);
        $this->actingAs($kedua['pic'])->post("/rencana-aksi/{$headerKedua->id}/ajukan", ['expected_versi' => $diubahKedua + 1])->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $headerKedua->fresh()->status_alur);
    }

    public function test_unduhan_bukti_versi_tak_disahkan_tetap_404(): void
    {
        Storage::fake('local');
        $fixture = $this->buatFixtureManual();
        $header = $this->buatDraf($fixture);

        Storage::disk('local')->put('berkas/rencana_aksi/sementara.pdf', 'ISI SEMENTARA');
        $bukti = BuktiDukung::create([
            'berkasable_type' => 'rencana_aksi', 'berkasable_id' => $header->id, 'mode' => 'file',
            'nama_asli' => 'sementara.pdf', 'path' => 'berkas/rencana_aksi/sementara.pdf', 'mime' => 'application/pdf', 'ukuran_bytes' => 13,
            'uploaded_by' => $fixture['pic']->id, 'created_at' => now(),
        ]);

        $this->simpan($fixture, $header, 1, [$this->manual($fixture['periode1'], 100), $this->manual($fixture['periode2'], 100)]);
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versiDraf = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $this->assertNull($versiDraf->disahkan_at);

        // Bukti dikunci setelah submit (diajukan/diverifikasi), sehingga
        // penghapusan harus dilakukan setelah header kembali dapat disunting.
        // Verifikasi lalu kembalikan via Action nyata agar status kembali
        // dikembalikan; versi tetap tak-disahkan (disahkan_at null).
        $tokens = fn (): array => ['expected_versi' => $header->fresh()->versi, 'versi_pengajuan_id' => $versiDraf->id];
        app(VerifikasiRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens());
        app(KembalikanRencanaAksi::class)->handle($fixture['perencanaan'], $header->id, $tokens() + ['alasan' => 'Koreksi bukti pendukung sebelum verifikasi lanjut.']);
        $this->assertSame('dikembalikan', $header->fresh()->status_alur);

        $this->actingAs($fixture['pic'])->delete(
            route('rencana-aksi.bukti.destroy', ['rencanaAksi' => $header->id, 'bukti' => $bukti->id]),
            ['alasan' => 'Dokumen salah unggah sebelum verifikasi.']
        )->assertSessionHasNoErrors();

        $url = route('rencana-aksi.versi.bukti.download', [$header->id, $versiDraf->id, $bukti->id]);
        $this->actingAs($fixture['perencanaan'])->get($url)->assertNotFound();
        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}/review?versi={$versiDraf->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rencanaAksi.bukti_dukungs.0.id', $bukti->id)
                ->where('rencanaAksi.bukti_dukungs.0.download_url', null));
    }
}
