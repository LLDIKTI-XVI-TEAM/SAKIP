<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\RencanaAksi\SahkanRencanaAksi;
use App\Actions\RencanaAksi\VerifikasiRencanaAksi;
use App\Events\RencanaAksiDibukaKembali;
use App\Events\RencanaAksiDikembalikan;
use App\Models\AuditLog;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * ISS-05.06: buka-kembali disahkan → dikembalikan dengan alasan WAJIB
 * (Q43 §43.2), sebelum penutupan atau di dalam sesi koreksi yang sah (#85).
 * Kunci per periode Q41 (§41.2) ditegakkan pada simpan/ajukan PIC setelah
 * dibuka kembali; Perencanaan dikecualikan sampai penutupan.
 */
class BukaKembaliRencanaAksiTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase, RencanaAksiFixtures;

    private const DITUTUP = 'Tahun jadwal telah ditutup; review memerlukan sesi koreksi resmi untuk indikator ini.';

    private const PISAH_TUGAS = 'Pengesah tidak boleh membuka kembali pengesahannya sendiri; minta Perencanaan lain atau Superadmin darurat.';

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
        $this->assertArrayNotHasKey('pembuka_sama_dengan_pengesah', $audit->nilai_baru);
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

    public function test_pengesah_membuka_sendiri_ditolak_dan_diaudit(): void
    {
        $this->verifikasi();
        $this->sahkan();

        $this->bukaKembali($this->pengesah)->assertUnprocessable()->assertJsonPath('errors.businessErrors', [self::PISAH_TUGAS]);

        $this->assertSame('disahkan', $this->rencanaAksi->fresh()->status_alur);
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.buka_kembali_ditolak')->count());
        $this->actingAs($this->pengesah)->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rencanaAksi.can.buka_kembali', false)
                ->where('rencanaAksi.businessErrors.buka_kembali', [self::PISAH_TUGAS]));
    }

    public function test_superadmin_sama_orang_boleh_darurat_dengan_penanda_audit(): void
    {
        $this->verifikasi();
        $superadmin = $this->createUserWithRole('superadmin');
        app(SahkanRencanaAksi::class)->handle($superadmin, $this->rencanaAksi->id, $this->tokens());

        $this->actingAs($superadmin)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/buka-kembali", [
            'expected_versi' => $this->rencanaAksi->fresh()->versi,
            'versi_pengajuan_id' => $this->versiPengajuan->id,
            'alasan' => 'Koreksi darurat pasca pengesahan.',
        ])->assertOk()->assertJson(['status_alur' => 'dikembalikan']);

        $audit = AuditLog::where('tindakan', 'rencana_aksi.buka_kembali')->sole();
        $this->assertTrue($audit->nilai_baru['pembuka_sama_dengan_pengesah']);
        $this->assertSame('Koreksi darurat pasca pengesahan.', $audit->alasan);
        $this->assertSame('dikembalikan', $this->rencanaAksi->fresh()->status_alur);
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

        // Q41: tidak ada jendela penyusunan — kolom lama yang sudah lewat
        // tidak dibaca. PIC boleh menulis periode yang belum terkunci
        // sebelum penutupan selama grant + PIC efektif valid.
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
     * ISS-05.06 AC-6 / Q41 §41.2: setelah dibuka kembali, PIC yang menyimpan
     * atau mengajukan periode yang sudah terkunci ditolak 422 dengan pesan
     * spesifik per periode dan diaudit; periode belum terkunci diterima dan
     * Perencanaan dikecualikan sampai penutupan.
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

        $simpan = fn (User $aktor, int $versiHeader, array $targets): TestResponse => $this->actingAs($aktor)->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $versiHeader,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => $targets,
        ]);
        $manual = fn ($periode, $nilai): array => ['periode_id' => $periode->id, 'komponen_id' => null, 'nilai' => $nilai, 'keterangan' => null];
        $pesanKunci = 'Periode terkunci bagi PIC setelah batas pengisian lewat: Triwulan I (batas 2026-03-05).';

        $simpan($fixture['pic'], $dibuka, [$manual($fixture['periode1'], 50)])
            ->assertSessionHasErrors(['jendela' => $pesanKunci]);
        $this->assertSame($dibuka, $header->fresh()->versi);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah_ditolak')->where('objek_id', $header->id)->exists());

        $simpan($fixture['pic'], $dibuka, [$manual($fixture['periode2'], 90)])->assertSessionHasNoErrors();
        $this->assertSame($dibuka + 1, $header->fresh()->versi);

        // Pengajuan menyentuh seluruh RA sehingga TW I yang terkunci menolak.
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => $dibuka + 1])
            ->assertSessionHasErrors(['jendela' => $pesanKunci]);
        $this->assertSame($dibuka + 1, $header->fresh()->versi);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ajukan_ditolak')->where('objek_id', $header->id)->exists());

        $simpan($fixture['perencanaan'], $dibuka + 1, [$manual($fixture['periode1'], 55)])->assertSessionHasNoErrors();
        $this->assertSame($dibuka + 2, $header->fresh()->versi);
    }

    /**
     * ISS-05.06 AC-7 / Q41 §41.2 butir 6: dalam sesi koreksi pascapenutupan,
     * PIC hanya boleh menulis periode yang jendela pengisiannya dibuka
     * kembali secara resmi; periode lain ditolak.
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

        // Q2: pengesah tidak boleh membuka pengesahannya sendiri, sehingga
        // buka-kembali memakai Perencanaan kedua yang berbeda orang.
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
}
