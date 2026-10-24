<?php

namespace Tests\Feature\Kegiatan;

use App\Actions\Kegiatan\TambahKegiatanRencanaAksi;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Policies\KegiatanPolicy;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Transisi status pelaksanaan kegiatan (Q69 §69.1–§69.4): justifikasi wajib,
 * status final, gerbang izin/unit/tahun, data basi, dan penolakan yang selalu diaudit.
 */
class UbahStatusKegiatanTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    /** @var array<string, mixed> */
    private array $fixture;

    private RencanaAksi $header;

    private Kegiatan $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = $this->buatFixtureManual();
        // Draf membuat "Kegiatan pendukung" di Triwulan I yang diklaim tahap RA.
        $this->header = $this->buatDraf($this->fixture);
        // PIC berperan pegawai: kegiatan:read dari preset, kegiatan:create tidak dimiliki.
        $this->grant($this->fixture['pic'], 'kegiatan:update', $this->fixture['unit']->id, $this->fixture['perencanaan']);
        $this->kegiatan = Kegiatan::where('nama', 'Kegiatan pendukung')->sole();
    }

    #[DataProvider('statusTujuan')]
    public function test_tiga_transisi_dari_rencana_menyimpan_status_dan_justifikasi(string $status): void
    {
        // L5: cukup kegiatan:update; kegiatan:create tidak disyaratkan.
        $this->assertFalse(app(PermissionResolver::class)->allows($this->fixture['pic'], 'kegiatan:create', $this->fixture['unit']->id));

        $tunda = $status === 'ditunda';
        $this->ubah(['status' => $status, ...($tunda ? ['periode_tujuan_id' => $this->fixture['periode2']->id] : [])])->assertRedirect();

        $kegiatan = $this->kegiatan->fresh();
        $this->assertSame([$status, 'Narasumber berhalangan.'], [$kegiatan->status, $kegiatan->justifikasi]);
        $lanjutan = Kegiatan::where('kegiatan_asal_id', $kegiatan->id)->first();
        $this->assertSame($tunda, $lanjutan !== null);
        $audit = AuditLog::where('tindakan', 'kegiatan.ubah_status')->sole();
        $this->assertNilai(['status' => 'rencana', 'justifikasi' => null], $audit->nilai_lama);
        $this->assertNilai(['status' => $status, 'justifikasi' => 'Narasumber berhalangan.',
            'periode_tujuan_id' => $tunda ? $this->fixture['periode2']->id : null, 'kegiatan_lanjutan_id' => $lanjutan?->id], $audit->nilai_baru);
        $this->assertSame('Perubahan status pelaksanaan kegiatan.', $audit->alasan);
        $this->assertNotEmpty($audit->dasar_izin);
    }

    public static function statusTujuan(): array
    {
        return [['tidak_terlaksana'], ['ditunda'], ['batal']];
    }

    public function test_tunda_membentuk_lanjutan_minimal_dengan_kegiatan_asal_id(): void
    {
        $this->kegiatan->update(['tujuan' => 'Meningkatkan mutu', 'sasaran_peserta' => 30, 'keterangan_peserta' => 'Asesor internal',
            'lokasi' => 'Aula', 'anggaran' => 2500000, 'tanggal_rencana' => '2026-03-20']);

        $this->ubah($this->tunda())->assertRedirect();

        $asal = $this->kegiatan->fresh();
        $lanjutan = Kegiatan::where('kegiatan_asal_id', $asal->id)->sole();
        $salin = [...TambahKegiatanRencanaAksi::FIELD_SALIN, 'unit_id', 'tahun'];
        $this->assertSame(Arr::only($asal->getAttributes(), $salin), Arr::only($lanjutan->getAttributes(), $salin));
        $this->assertSame([$this->fixture['periode2']->id, null, 'rencana', null, $this->fixture['pic']->id],
            [$lanjutan->periode_id, $lanjutan->tanggal_rencana, $lanjutan->status, $lanjutan->justifikasi, $lanjutan->created_by]);
        // Klaim dan berkas tidak disalin (Q69 §69.5 butir 1).
        $this->assertFalse(KlaimKegiatan::where('kegiatan_id', $lanjutan->id)->exists());
    }

    #[DataProvider('periodeTujuanTidakSah')]
    public function test_periode_tujuan_wajib_aktif_dan_lebih_akhir(string $kasus): void
    {
        $tujuan = match ($kasus) {
            'kosong' => null,
            'bukan uuid' => 'triwulan-2',
            'periode sama' => $this->fixture['periode1']->id,
            'lebih awal' => $this->pindahKePeriodeTerakhir(),
            'urutan sama' => Periode::create(['nama' => 'Triwulan I-B', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false])->id,
            'nonaktif' => Periode::create(['nama' => 'Triwulan III', 'urutan' => 3, 'aktif' => false, 'is_nilai_akhir' => false])->id,
            'asing' => (string) Str::uuid(),
        };
        $jumlah = Kegiatan::count();

        $this->ubah($this->tunda(['periode_tujuan_id' => $tujuan]))->assertUnprocessable()->assertJsonValidationErrors('periode_tujuan_id');

        $this->assertSame([$jumlah, 'rencana'], [Kegiatan::count(), $this->kegiatan->fresh()->status]);
        $this->assertDitolak(match ($kasus) {
            'kosong' => 'Pilih periode tujuan.',
            'bukan uuid' => 'Pilih periode tujuan dari daftar.',
            default => 'Periode tujuan harus periode aktif yang lebih akhir dari periode kegiatan.',
        });
    }

    public static function periodeTujuanTidakSah(): array
    {
        $kasus = ['kosong', 'bukan uuid', 'periode sama', 'lebih awal', 'urutan sama', 'nonaktif', 'asing'];

        return array_combine($kasus, array_map(fn (string $k): array => [$k], $kasus));
    }

    public function test_audit_ubah_status_dan_geser_periode_dalam_transaksi_yang_sama(): void
    {
        // Atomisitas: audit kedua gagal membatalkan audit pertama yang sudah tertulis, status, lanjutan, dan kenaikan versi RA.
        $versi = $this->header->fresh()->versi;
        $logger = app(AuditLogger::class);
        $panggilan = 0;
        $this->mock(AuditLogger::class)->shouldReceive('catat')->twice()->andReturnUsing(function (mixed ...$argumen) use (&$panggilan, $logger): AuditLog {
            if (++$panggilan === 2) {
                throw new RuntimeException('Audit geser periode gagal.');
            }

            return $logger->catat(...$argumen);
        });
        $this->withoutExceptionHandling();

        try {
            $this->ubah($this->tunda());
            $this->fail('Kegagalan audit harus diteruskan.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit geser periode gagal.', $exception->getMessage());
        }

        $this->assertFalse(AuditLog::where('tindakan', 'kegiatan.ubah_status')->where('objek_id', $this->kegiatan->id)->exists());
        $this->assertSame('rencana', $this->kegiatan->fresh()->status);
        $this->assertFalse(Kegiatan::where('kegiatan_asal_id', $this->kegiatan->id)->exists());
        $this->assertSame($versi, $this->header->fresh()->versi);

        $this->forgetMock(AuditLogger::class);
        $this->withExceptionHandling();
        $this->ubah($this->tunda())->assertRedirect();

        $lanjutan = Kegiatan::where('kegiatan_asal_id', $this->kegiatan->id)->sole();
        $geser = AuditLog::where('tindakan', 'kegiatan.geser_periode')->sole();
        $this->assertSame(['kegiatan', $lanjutan->id, 'Pembentukan kegiatan lanjutan dari kegiatan yang ditunda.'], [$geser->objek_tipe, $geser->objek_id, $geser->alasan]);
        $this->assertNilai(['kegiatan_asal_id' => $this->kegiatan->id, 'periode_asal_id' => $this->fixture['periode1']->id,
            'periode_tujuan_id' => $this->fixture['periode2']->id, 'klaim' => []], $geser->nilai_baru);
        $this->assertEquals(AuditLog::where('tindakan', 'kegiatan.ubah_status')->sole()->dasar_izin, $geser->dasar_izin);
        $this->assertFalse(AuditLog::where('tindakan', 'kegiatan.roll_over')->exists());
    }

    public function test_tunda_dua_kali_hanya_membentuk_satu_lanjutan(): void
    {
        // Berurutan, bukan bukti race; bukti race ada di KegiatanConcurrencyTest.
        $token = $this->kegiatan->fresh()->versionToken();
        $this->ubah($this->tunda(['expected_state' => $token]))->assertRedirect();

        $this->ubah($this->tunda(['expected_state' => $token]))->assertStatus(409)->assertJsonValidationErrors('expected_state');
        $this->ubah($this->tunda())->assertUnprocessable()->assertJsonValidationErrors(['status' => 'Status kegiatan sudah final dan tidak dapat diubah.']);

        $this->assertSame(1, Kegiatan::where('kegiatan_asal_id', $this->kegiatan->id)->count());
    }

    public function test_tidak_terlaksana_ke_tunda_mencatat_justifikasi_lama(): void
    {
        $this->kegiatan->forceFill(['status' => 'tidak_terlaksana', 'justifikasi' => 'Hujan deras.'])->save();

        $this->ubah($this->tunda(['justifikasi' => 'Dijadwalkan ulang.']))->assertRedirect();

        $this->assertSame(['ditunda', 'Dijadwalkan ulang.'], [$this->kegiatan->fresh()->status, $this->kegiatan->fresh()->justifikasi]);
        $this->assertNilai(['status' => 'tidak_terlaksana', 'justifikasi' => 'Hujan deras.'], AuditLog::where('tindakan', 'kegiatan.ubah_status')->sole()->nilai_lama);
        $this->assertTrue(Kegiatan::where('kegiatan_asal_id', $this->kegiatan->id)->exists());
    }

    #[DataProvider('justifikasiTidakSah')]
    public function test_justifikasi_kosong_atau_tidak_sah_ditolak_dan_diaudit(?string $nilai): void
    {
        $this->ubah(['justifikasi' => $nilai])->assertUnprocessable()->assertJsonValidationErrors('justifikasi');

        $this->assertSame('rencana', $this->kegiatan->fresh()->status);
        $this->assertSame([$this->kegiatan->id], AuditLog::where('tindakan', 'kegiatan.ubah_status_ditolak')->pluck('objek_id')->all());
    }

    public static function justifikasiTidakSah(): array
    {
        return [
            'kosong' => [''],
            'spasi' => ['   '],
            'null' => [null],
            'karakter kontrol' => ["Rapat\x07batal"],
            'terlalu panjang' => [str_repeat('a', 10001)],
        ];
    }

    public function test_justifikasi_multibaris_diterima(): void
    {
        $this->ubah(['justifikasi' => "Baris satu\n\tBaris dua"])->assertRedirect();

        $kegiatan = $this->kegiatan->fresh();
        $this->assertSame(['tidak_terlaksana', "Baris satu\n\tBaris dua"], [$kegiatan->status, $kegiatan->justifikasi]);
    }

    #[DataProvider('statusDiLuarPilihan')]
    public function test_status_tujuan_di_luar_pilihan_ditolak(string $status): void
    {
        $this->ubah(['status' => $status])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame('rencana', $this->kegiatan->fresh()->status);
        $this->assertDitolak('Pilih status tidak terlaksana, ditunda, atau batal.');
    }

    public static function statusDiLuarPilihan(): array
    {
        return [['terlaksana'], ['rencana'], ['']];
    }

    #[DataProvider('statusFinal')]
    public function test_status_final_ditolak_422_dan_diaudit(string $final): void
    {
        $this->kegiatan->forceFill(['status' => $final])->save();

        $this->ubah(['status' => 'batal'])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame($final, $this->kegiatan->fresh()->status);
        $this->assertDitolak('Status kegiatan sudah final dan tidak dapat diubah.');
    }

    public static function statusFinal(): array
    {
        return [['terlaksana'], ['ditunda'], ['batal']];
    }

    public function test_tidak_terlaksana_ke_tidak_terlaksana_ditolak(): void
    {
        $this->kegiatan->forceFill(['status' => 'tidak_terlaksana'])->save();

        $this->ubah()->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDitolak('Status tujuan tidak tersedia untuk status kegiatan saat ini.');
    }

    public function test_tidak_terlaksana_ke_batal_mencatat_justifikasi_lama(): void
    {
        $this->kegiatan->forceFill(['status' => 'tidak_terlaksana', 'justifikasi' => 'Hujan deras.'])->save();

        $this->ubah(['status' => 'batal', 'justifikasi' => 'Anggaran dialihkan.'])->assertRedirect();

        $this->assertSame(['batal', 'Anggaran dialihkan.'], [$this->kegiatan->fresh()->status, $this->kegiatan->fresh()->justifikasi]);
        $this->assertNilai(['status' => 'tidak_terlaksana', 'justifikasi' => 'Hujan deras.'], AuditLog::where('tindakan', 'kegiatan.ubah_status')->sole()->nilai_lama);
    }

    public function test_tahun_ditutup_ditolak_422_dan_diaudit(): void
    {
        $this->fixture['jadwal']->update(['status' => 'ditutup', 'closed_at' => now()]);

        $this->ubah()->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame('rencana', $this->kegiatan->fresh()->status);
        $this->assertDitolak('Tahun kegiatan sudah ditutup; perubahan hanya melalui pembukaan kembali jadwal.');
    }

    #[DataProvider('tanpaIzin')]
    public function test_tanpa_izin_deny_atau_tanpa_baca_ditolak_403_dan_diaudit(string $kasus): void
    {
        $pic = $this->fixture['pic'];
        match ($kasus) {
            'tanpa grant' => $this->cabutGrant($pic, 'kegiatan:update'),
            'deny ubah' => $this->deny($pic, 'kegiatan:update', $this->fixture['unit']->id),
            'deny baca' => $this->deny($pic, 'kegiatan:read', $this->fixture['unit']->id),
        };

        $this->ubah()->assertForbidden();

        $this->assertSame('rencana', $this->kegiatan->fresh()->status);
        $audit = AuditLog::where('tindakan', 'kegiatan.ubah_status_ditolak')->sole();
        $this->assertSame($this->kegiatan->id, $audit->objek_id);
        $this->assertEquals(app(KegiatanPolicy::class)->keputusanSimpan($pic, $this->kegiatan, (string) $this->kegiatan->unit_id)->toAuditBasis(), $audit->dasar_izin);
    }

    public static function tanpaIzin(): array
    {
        return ['tanpa grant' => ['tanpa grant'], 'deny ubah' => ['deny ubah'], 'deny baca' => ['deny baca']];
    }

    public function test_unit_nonaktif_ditolak_422_dan_diaudit(): void
    {
        $this->fixture['unit']->update(['status' => 'nonaktif']);

        $this->ubah()->assertUnprocessable()->assertJsonValidationErrors(['status' => 'Unit kegiatan harus tetap aktif.']);

        $this->assertSame('rencana', $this->kegiatan->fresh()->status);
        $this->assertDitolak('Unit kegiatan harus tetap aktif.');
    }

    public function test_akun_nonaktif_ditolak_dan_diaudit_sebagai_ubah_status(): void
    {
        $this->fixture['pic']->update(['status' => 'nonaktif']);

        $this->actingAs($this->fixture['pic'])->withHeader('X-Inertia', 'true')->post("/kegiatan/{$this->kegiatan->id}/status", [
            'status' => 'batal', 'justifikasi' => 'Tidak jadi.', 'expected_state' => $this->kegiatan->fresh()->versionToken(),
        ])->assertForbidden();

        $audit = AuditLog::where('tindakan', 'kegiatan.ubah_status_ditolak')->sole();
        $this->assertSame(['inactive_user', 'Akun tidak aktif saat mengubah status kegiatan.'], [$audit->dasar_izin['alasan'], $audit->alasan]);
        $this->assertFalse(AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->exists());
        $this->assertSame('rencana', $this->kegiatan->fresh()->status);
    }

    public function test_token_basi_ditolak_409_tanpa_mutasi(): void
    {
        $token = $this->kegiatan->fresh()->versionToken();
        $this->kegiatan->update(['nama' => 'Diubah pengguna lain']);

        $this->ubah(['expected_state' => $token])->assertStatus(409)->assertJsonValidationErrors('expected_state');

        $this->assertSame('rencana', $this->kegiatan->fresh()->status);
        $this->assertDitolak('Kegiatan telah berubah. Muat ulang sebelum mengubah status.');
    }

    public function test_versi_ra_draf_naik_sedangkan_ra_diajukan_tidak_berubah(): void
    {
        $lain = $this->headerLain(RencanaAksi::STATUS_DIAJUKAN);
        $versiHeader = $this->header->fresh()->versi;
        $versiLain = $lain->fresh()->versi;

        $this->ubah()->assertRedirect();

        $this->assertSame([$versiHeader + 1, RencanaAksi::STATUS_DRAFT], [$this->header->fresh()->versi, $this->header->fresh()->status_alur]);
        $this->assertSame([$versiLain, RencanaAksi::STATUS_DIAJUKAN], [$lain->fresh()->versi, $lain->fresh()->status_alur]);
    }

    public function test_ra_disahkan_dan_periode_asal_terkunci_tidak_menghalangi_dan_tidak_berubah(): void
    {
        $this->header->forceFill(['status_alur' => RencanaAksi::STATUS_DISAHKAN])->save();
        $versi = $this->header->fresh()->versi;
        // Lewat pengisian_selesai Triwulan I (31 Maret): kunci periode asal tidak membatasi status kegiatan.
        $this->travelTo(now()->setDate(2026, 4, 15)->setTime(9, 0));

        $this->ubah(['status' => 'batal'])->assertRedirect();

        $this->assertSame('batal', $this->kegiatan->fresh()->status);
        $this->assertSame([$versi, RencanaAksi::STATUS_DISAHKAN], [$this->header->fresh()->versi, $this->header->fresh()->status_alur]);
    }

    public function test_periode_tujuan_diabaikan_untuk_status_selain_ditunda(): void
    {
        $jumlah = Kegiatan::count();

        $this->ubah(['status' => 'batal', 'periode_tujuan_id' => $this->fixture['periode2']->id])->assertRedirect();

        $this->assertSame($jumlah, Kegiatan::count());
        $this->assertNull(AuditLog::where('tindakan', 'kegiatan.ubah_status')->sole()->nilai_baru['periode_tujuan_id']);
    }

    public function test_sukses_kembali_ke_halaman_asal_dengan_pesan(): void
    {
        $this->actingAs($this->fixture['pic'])->withHeader('referer', '/kegiatan?tahun=2026')->post("/kegiatan/{$this->kegiatan->id}/status", [
            'status' => 'tidak_terlaksana', 'justifikasi' => 'Narasumber berhalangan.', 'expected_state' => $this->kegiatan->fresh()->versionToken(),
        ])->assertRedirect('/kegiatan?tahun=2026')->assertSessionHas('success', 'Kegiatan ditandai tidak terlaksana.');

        $kedua = $this->kegiatanLain('Kegiatan kedua', $this->fixture['periode1']->id);
        $this->actingAs($this->fixture['pic'])->withHeader('referer', "/kegiatan/{$kedua->id}/edit")->post("/kegiatan/{$kedua->id}/status", [
            'status' => 'batal', 'justifikasi' => 'Anggaran dialihkan.', 'expected_state' => $kedua->fresh()->versionToken(),
        ])->assertRedirect("/kegiatan/{$kedua->id}/edit")->assertSessionHas('success', 'Kegiatan dibatalkan.');

        $ketiga = $this->kegiatanLain('Kegiatan ketiga', $this->fixture['periode1']->id);
        $this->actingAs($this->fixture['pic'])->withHeader('referer', '/kegiatan?tahun=2026')->post("/kegiatan/{$ketiga->id}/status", $this->tunda([
            'justifikasi' => 'Dijadwalkan ulang.', 'expected_state' => $ketiga->fresh()->versionToken(),
        ]))->assertRedirect('/kegiatan?tahun=2026')->assertSessionHas('success', 'Kegiatan ditunda ke Triwulan II; kegiatan lanjutan dibuat.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function tunda(array $data = []): array
    {
        return ['status' => 'ditunda', 'periode_tujuan_id' => $this->fixture['periode2']->id, ...$data];
    }

    /** Pindahkan kegiatan uji ke periode aktif terakhir fixture; kembalikan periode yang lebih awal. */
    private function pindahKePeriodeTerakhir(): string
    {
        $this->kegiatan->forceFill(['periode_id' => $this->fixture['periode2']->id])->save();

        return $this->fixture['periode1']->id;
    }

    public function test_daftar_dan_form_mengirim_pilihan_status_dari_server(): void
    {
        $pendukung = $this->baris('Kegiatan pendukung');
        $this->assertSame([['ditunda', 'tidak_terlaksana', 'batal'], true], [$pendukung['can']['ubah_status'], $pendukung['can']['ubah_rencana']]);
        $this->assertSame([['id' => $this->fixture['periode2']->id, 'nama' => 'Triwulan II']], $pendukung['opsi_periode_tujuan']);
        $this->assertSame('Triwulan I', $this->form($this->kegiatan)['kegiatan']['periode_nama']);

        // Periode aktif terakhir: tanpa Tunda dan tanpa opsi periode tujuan.
        $kedua = $this->kegiatanLain('Kegiatan triwulan dua', $this->fixture['periode2']->id);
        $barisKedua = $this->baris('Kegiatan triwulan dua');
        $this->assertSame([['tidak_terlaksana', 'batal'], []], [$barisKedua['can']['ubah_status'], $barisKedua['opsi_periode_tujuan']]);

        $this->ubah(['status' => 'batal'])->assertRedirect();
        $batal = $this->baris('Kegiatan pendukung');
        $this->assertSame([[], false, true], [$batal['can']['ubah_status'], $batal['can']['ubah_rencana'], $batal['can']['update']]);
        $form = $this->form($this->kegiatan);
        $this->assertSame(['Narasumber berhalangan.', []], [$form['kegiatan']['justifikasi'], $form['can']['ubah_status']]);

        $ketiga = $this->kegiatanLain('Kegiatan ditunda', $this->fixture['periode1']->id);
        $this->ubah($this->tunda(), $ketiga)->assertRedirect();
        $this->assertSame('Triwulan II', $this->form($ketiga)['kegiatan']['periode_tujuan']);

        $this->fixture['jadwal']->update(['status' => 'ditutup', 'closed_at' => now()]);
        $this->assertSame([], $this->baris('Kegiatan triwulan dua')['can']['ubah_status']);
        $this->assertSame([], $this->form($kedua)['can']['ubah_status']);

        $this->fixture['jadwal']->update(['status' => 'aktif', 'closed_at' => null]);
        $this->deny($this->fixture['pic'], 'kegiatan:update', $this->fixture['unit']->id);
        $this->assertSame([], $this->baris('Kegiatan triwulan dua')['can']['ubah_status']);
    }

    /** @return array<string, mixed> */
    private function baris(string $nama): array
    {
        $props = $this->actingAs($this->fixture['pic'])->get('/kegiatan?tahun=2026')->assertOk()->viewData('page')['props'];

        return collect($props['kegiatan']['data'])->firstWhere('nama', $nama);
    }

    /** @return array<string, mixed> */
    private function form(Kegiatan $kegiatan): array
    {
        return $this->actingAs($this->fixture['pic'])->get("/kegiatan/{$kegiatan->id}/edit")->assertOk()->viewData('page')['props'];
    }

    private function kegiatanLain(string $nama, string $periodeId): Kegiatan
    {
        return Kegiatan::create(['unit_id' => $this->fixture['unit']->id, 'tahun' => 2026, 'periode_id' => $periodeId,
            'nama' => $nama, 'anggaran' => 0, 'created_by' => $this->fixture['pic']->id]);
    }

    /** @param array<string, mixed> $data */
    private function ubah(array $data = [], ?Kegiatan $kegiatan = null): TestResponse
    {
        $kegiatan ??= $this->kegiatan;

        return $this->actingAs($this->fixture['pic'])->postJson("/kegiatan/{$kegiatan->id}/status", [
            'status' => 'tidak_terlaksana', 'justifikasi' => 'Narasumber berhalangan.',
            'expected_state' => $kegiatan->fresh()->versionToken(), ...$data,
        ]);
    }

    private function assertDitolak(string $alasan): void
    {
        $audit = AuditLog::where('tindakan', 'kegiatan.ubah_status_ditolak')->sole();
        $this->assertSame([$this->kegiatan->id, $alasan], [$audit->objek_id, $audit->alasan]);
        $this->assertNotEmpty($audit->dasar_izin);
    }

    /**
     * JSONB mengurutkan ulang key, sehingga perbandingan ketat dilakukan setelah key diurutkan.
     *
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>|null  $actual
     */
    private function assertNilai(array $expected, ?array $actual): void
    {
        $actual ??= [];
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }

    private function deny(User $user, string $permission, ?string $unit): void
    {
        UserPermissionDeny::create(['user_id' => $user->id, 'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => $unit, 'alasan' => 'Pembatasan pengujian', 'ditetapkan_oleh' => $this->fixture['perencanaan']->id]);
    }

    /** Header RA IKU lain dengan status alur tertentu yang juga mengklaim kegiatan uji pada tahap RA. */
    private function headerLain(string $statusAlur): RencanaAksi
    {
        $indikator = $this->fixture['indikator']->replicate();
        $indikator->kode = 'I-UJI-LAIN';
        $indikator->save();
        $header = RencanaAksi::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'unit_id' => $this->fixture['unit']->id,
            'jadwal_tahunan_id' => $this->fixture['jadwal']->id, 'penanggung_jawab_id' => $this->fixture['pic']->id, 'status_alur' => $statusAlur, 'versi' => 1,
            'created_by' => $this->fixture['pic']->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $header->id, 'kegiatan_id' => $this->kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->fixture['pic']->id, 'created_at' => now()]);

        return $header;
    }
}
