<?php

namespace Tests\Feature\Kegiatan;

use App\Models\AuditLog;
use App\Models\JadwalSnapshot;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\Unit;
use App\Models\User;
use App\Services\Kegiatan\AnggaranKegiatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

/**
 * Halaman kegiatan di dalam Rencana Aksi (Q74 §74.1): satu-satunya permukaan
 * tambah, ubah, klaim, berkas, dan status kegiatan.
 */
class HalamanKegiatanRencanaAksiTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    private Periode $tw1;

    private Periode $tw2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        $this->tw1 = Periode::findOrFail($this->snapshot->periode_mulai_id);
        $this->tw2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $this->tw2->id,
            'pengisian_mulai' => '2026-04-01', 'pengisian_selesai' => '2026-06-30']);
    }

    public function test_halaman_kegiatan_ra_menampilkan_kegiatan_per_periode_dan_capability(): void
    {
        $kegiatan = $this->kegiatan(['nama' => 'Rapat koordinasi']);
        $this->klaim($kegiatan);

        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('RencanaAksi/Kegiatan')
            ->where('rencanaAksi.id', $this->rencanaAksi->id)
            ->has('kegiatan.daftar', 1)
            ->where('kegiatan.daftar.0.kegiatan_id', $kegiatan->id)
            ->where('kegiatan.daftar.0.periode_id', $this->tw1->id)
            ->where('kegiatan.daftar.0.nama', 'Rapat koordinasi')
            ->where('kegiatan.daftar.0.anggaran', '0')
            ->where('kegiatan.can', ['tambah' => true, 'kelola' => true])
            ->has('kegiatan.total.per_periode'));
    }

    public function test_halaman_kegiatan_ra_tanpa_izin_baca_menyembunyikan_daftar(): void
    {
        $this->klaim($this->kegiatan());
        $this->denyPermission($this->actor, 'kegiatan:read', $this->unit->id);

        $this->actingAs($this->actor)->get($this->url())->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('RencanaAksi/Kegiatan')->where('kegiatan', null));
    }

    public function test_pembuatan_kegiatan_dari_ra_menyimpan_klaim_dan_mengabaikan_input_di_luar_kontrak(): void
    {
        $pic = $this->pic();

        $this->actingAs($pic)->post($this->url(), [
            ...$this->payload(),
            // Di luar kontrak permintaan: unit/tahun dari header, status/pelaksanaan tidak boleh dipercaya.
            'unit_id' => $this->unit->id, 'tahun' => 2027, 'status' => 'terlaksana', 'created_by' => $this->actor->id,
            'tanggal_realisasi' => '2026-03-20', 'uraian_pelaksanaan' => 'Tidak boleh tersimpan',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $kegiatan = Kegiatan::sole();
        $this->assertSame([$this->unit->id, 2026, $this->tw1->id, 'rencana', $pic->id, '1500000'],
            [$kegiatan->unit_id, (int) $kegiatan->tahun, $kegiatan->periode_id, $kegiatan->status, $kegiatan->created_by, $kegiatan->anggaran]);
        $this->assertNull($kegiatan->tanggal_realisasi);
        $this->assertNull($kegiatan->uraian_pelaksanaan);

        $klaim = KlaimKegiatan::sole();
        $this->assertSame([$this->rencanaAksi->id, $kegiatan->id, 'rencana_aksi', $pic->id],
            [$klaim->rencana_aksi_id, $klaim->kegiatan_id, $klaim->sumber_klaim, $klaim->created_by]);
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.buat')->where('objek_id', $kegiatan->id)->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'klaim_kegiatan.tambah')->where('objek_id', $klaim->id)->count());

        // Endpoint pembuatan tanpa konteks Rencana Aksi sudah tidak tersedia.
        $this->post('/kegiatan', $this->payload())->assertStatus(405);
        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertDatabaseCount('klaim_kegiatan', 1);
    }

    /** @return array<string, array{mixed}> */
    public static function anggaranTidakSah(): array
    {
        return ['negatif' => [-1], 'pecahan sen' => ['1500.50'], 'bukan angka' => ['satu juta'], 'lebih dari 15 digit' => ['1000000000000000']];
    }

    #[DataProvider('anggaranTidakSah')]
    public function test_anggaran_pembuatan_divalidasi_di_jalur_ra(mixed $anggaran): void
    {
        $this->actingAs($this->pic())->postJson($this->url(), array_replace($this->payload(), ['anggaran' => $anggaran]))
            ->assertUnprocessable()->assertJsonValidationErrors('anggaran');
        $this->assertDatabaseCount('kegiatan', 0);
        $this->assertDatabaseCount('klaim_kegiatan', 0);
    }

    public function test_anggaran_nol_sah_dan_kosong_wajib_pada_periode_berjalan(): void
    {
        $pic = $this->pic();

        $this->actingAs($pic)->post($this->url(), array_replace($this->payload(), ['anggaran' => 0]))->assertSessionHasNoErrors();
        $this->assertSame('0', Kegiatan::sole()->anggaran);

        $this->actingAs($pic)->post($this->url(), array_replace($this->payload(), ['nama' => 'Tanpa anggaran', 'anggaran' => null]))
            ->assertSessionHasErrors(['anggaran' => AnggaranKegiatan::PESAN_WAJIB]);
        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertSame('0', Kegiatan::sole()->anggaran);
    }

    public function test_tanpa_izin_create_tidak_menawarkan_tambah_dan_request_langsung_ditolak(): void
    {
        $pic = $this->pic(create: false);

        $this->actingAs($pic)->get($this->url())->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('kegiatan.can.tambah', false));

        $this->actingAs($pic)->post($this->url(), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('kegiatan', 0);
        $audit = AuditLog::where('tindakan', 'kegiatan.buat_ditolak')->sole();
        $this->assertSame($pic->id, $audit->actor_id);
        $this->assertSame('kegiatan:create', $audit->dasar_izin['permission']);
    }

    public function test_unit_nonaktif_menolak_pembuatan_dari_ra(): void
    {
        $this->unit->update(['status' => 'nonaktif']);

        // PIC bergantung pada grant unit; grant pada unit nonaktif tidak lagi memberi izin (403 + audit).
        $pic = $this->pic();
        $this->actingAs($pic)->postJson($this->url(), $this->payload())->assertForbidden();
        $auditPic = AuditLog::where('tindakan', 'kegiatan.buat_ditolak')->sole();
        $this->assertSame($pic->id, $auditPic->actor_id);
        $this->assertSame('rencana_aksi', $auditPic->objek_tipe);
        $this->assertSame('inactive_unit', $auditPic->dasar_izin['alasan']);
        $this->assertDatabaseCount('kegiatan', 0);

        // Aktor yang izinnya berasal dari peran tetap ditolak sebagai pelanggaran bisnis 422.
        $this->actingAs($this->actor)->postJson($this->url(), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->assertDatabaseCount('kegiatan', 0);
        $this->assertSame(2, AuditLog::where('tindakan', 'kegiatan.buat_ditolak')->count());
    }

    public function test_halaman_edit_ra_membawa_form_klaim_dan_berkas(): void
    {
        $kegiatan = $this->kegiatan();
        $klaim = $this->klaim($kegiatan);

        $this->actingAs($this->actor)->get($this->url($kegiatan))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('RencanaAksi/KegiatanEdit')
            ->where('rencanaAksi.id', $this->rencanaAksi->id)
            ->where('kegiatan.id', $kegiatan->id)
            ->where('kegiatan.version_token', fn (string $token) => strlen($token) === 64)
            ->has('klaim.daftar', 1)
            ->where('klaim.daftar.0.id', $klaim->id)
            ->has('berkasPerencanaan.persyaratan'));
    }

    public function test_halaman_edit_ra_menolak_kegiatan_di_luar_klaim_ra_tersebut(): void
    {
        $kegiatan = $this->kegiatan();
        $this->klaim($kegiatan);
        $lain = $this->kegiatan(['nama' => 'Kegiatan tanpa klaim']);

        $this->actingAs($this->actor)->get($this->url($lain))->assertNotFound();
        $this->actingAs($this->actor)->get('/rencana-aksi/'.(string) Str::uuid().'/kegiatan/'.$kegiatan->id)->assertNotFound();
    }

    public function test_lintas_unit_ditolak_403_dengan_audit_dan_kontrol_positif(): void
    {
        $unitB = Unit::create(['nama' => 'Unit B', 'status' => 'aktif', 'created_by' => $this->actor->id]);
        $raB = $this->rencanaAksiLain($unitB);
        $kegiatanB = $this->kegiatan(['unit_id' => $unitB->id]);
        $this->klaim($kegiatanB, $raB);

        $pegawai = $this->createUserWithRole('pegawai');
        foreach (['rencana_aksi:update', 'kegiatan:read', 'kegiatan:update'] as $permission) {
            $this->grantUnitPermission($pegawai, $permission, $this->unit->id);
        }

        // Kontrol positif: unit A boleh dibuka dan diubah.
        $kegiatanA = $this->kegiatan();
        $this->klaim($kegiatanA);
        $this->actingAs($pegawai)->get($this->url($kegiatanA))->assertOk();
        $this->actingAs($pegawai)->post("/kegiatan/{$kegiatanA->id}", $this->payloadFor($kegiatanA))
            ->assertSessionHasNoErrors();
        $this->assertSame('Nama diperbarui', $kegiatanA->fresh()->nama);

        // Objek unit B tidak boleh dibuka atau diubah dengan grant unit A.
        $this->actingAs($pegawai)->get("/rencana-aksi/{$raB->id}/kegiatan/{$kegiatanB->id}")->assertForbidden();
        $this->assertSame('kegiatan:read', AuditLog::where('tindakan', 'kegiatan.form_ditolak')->where('objek_id', $kegiatanB->id)->sole()->dasar_izin['permission']);

        $this->actingAs($pegawai)->post("/kegiatan/{$kegiatanB->id}", $this->payloadFor($kegiatanB, 'Tidak boleh'))->assertForbidden();
        $this->assertSame('Kegiatan awal', $kegiatanB->fresh()->nama);
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->where('objek_id', $kegiatanB->id)->count());
    }

    public function test_kegiatan_dari_ra_a_dapat_diklaim_ra_b_dan_ditandai_kegiatan_bersama(): void
    {
        // 1. Kegiatan dibuat dari RA A oleh PIC berizin; klaim tahap RA A terbentuk.
        $this->actingAs($this->pic())->post($this->url(), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $kegiatan = Kegiatan::sole();
        $klaimA = KlaimKegiatan::sole();
        $this->assertSame([$this->rencanaAksi->id, $kegiatan->id, 'rencana_aksi'],
            [$klaimA->rencana_aksi_id, $klaimA->kegiatan_id, $klaimA->sumber_klaim]);

        // RA B berbagi unit dan jadwal dengan RA A tetapi indikatornya berbeda:
        // syarat penanda kegiatan bersama (klaim lintas IKU).
        $raB = $this->rencanaAksiLain($this->unit);
        $this->snapshotIndikator($raB);

        // 2. Kegiatan yang sama diklaim ke RA B tanpa komponen, seperti opsi "tanpa komponen".
        $this->actingAs($this->actor)->post("/kegiatan/{$kegiatan->id}/klaim", [
            'rencana_aksi_id' => $raB->id, 'komponen_id' => null, 'arah_dampak' => 'mengurangi', 'catatan' => null,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('klaim_kegiatan', 2);

        // 3. Kedua halaman RA memuat kegiatan yang sama dan menandainya sebagai kegiatan bersama.
        foreach ([$this->rencanaAksi, $raB] as $header) {
            $this->actingAs($this->actor)->get('/rencana-aksi/'.$header->id.'/kegiatan')->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('RencanaAksi/Kegiatan')
                    ->has('kegiatan.daftar', 1)
                    ->where('kegiatan.daftar.0.kegiatan_id', $kegiatan->id)
                    ->where('kegiatan.daftar.0.kegiatan_bersama', true));
        }

        // 4. Kontrol negatif: kegiatan yang hanya diklaim RA A tidak bertanda bersama.
        $tunggal = $this->kegiatan(['nama' => 'Kegiatan satu rencana aksi']);
        $this->klaim($tunggal);

        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('kegiatan.daftar', 2)
            ->where('kegiatan.daftar', fn ($daftar) => collect($daftar)->contains(
                fn ($baris) => $baris['kegiatan_id'] === (string) $tunggal->id && $baris['kegiatan_bersama'] === false)));
    }

    public function test_periode_terkunci_tampil_dan_pembuatan_pada_periode_itu_ditolak(): void
    {
        $pic = $this->pic();
        $this->travelTo(now()->setDate(2026, 4, 15)->setTime(9, 0));

        $this->actingAs($pic)->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('rencanaAksi.periode_terkunci', fn ($terkunci) => collect($terkunci)->contains($this->tw1->id))
            // Satu periode masih terbuka: capability tambah tetap ditawarkan.
            ->where('rencanaAksi.periode_dapat_ditulis', [$this->tw2->id])
            ->where('kegiatan.can.tambah', true)
            ->where('kegiatan.daftar', []));

        $this->actingAs($pic)->post($this->url(), array_replace($this->payload(), ['periode_id' => $this->tw1->id]))
            ->assertSessionHasErrors('jendela');
        $this->assertDatabaseCount('kegiatan', 0);
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.buat_ditolak')->count());

        // Kontrol: periode yang jendelanya belum lewat tetap dapat ditambahi.
        $this->actingAs($pic)->post($this->url(), array_replace($this->payload(), ['periode_id' => $this->tw2->id]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertSame($this->tw2->id, Kegiatan::sole()->periode_id);
    }

    public function test_semua_periode_terkunci_bagi_pic_menonaktifkan_tambah_dan_pembuatan_ditolak(): void
    {
        $pic = $this->pic();
        // Kedua periode efektif sudah lewat batas pengisiannya: tidak ada periode
        // yang dapat ditulis PIC, sehingga capability tambah harus fail-closed.
        $this->travelTo(now()->setDate(2026, 7, 15)->setTime(9, 0));

        $this->actingAs($pic)->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('rencanaAksi.periode_dapat_ditulis', [])
            ->where('rencanaAksi.periode_terkunci', [$this->tw1->id, $this->tw2->id])
            ->where('kegiatan.can.tambah', false));

        // Request langsung tetap ditolak tanpa mutasi, pada periode mana pun.
        foreach ([$this->tw1->id, $this->tw2->id] as $periodeId) {
            $this->actingAs($pic)->post($this->url(), array_replace($this->payload(), ['periode_id' => $periodeId]))
                ->assertSessionHasErrors('jendela');
        }

        $this->assertDatabaseCount('kegiatan', 0);
        $this->assertDatabaseCount('klaim_kegiatan', 0);
        $this->assertSame(2, AuditLog::where('tindakan', 'kegiatan.buat_ditolak')->count());
    }

    public function test_akun_nonaktif_pada_halaman_baru_ditolak_dan_diaudit_sebagai_baca(): void
    {
        $this->actor->update(['status' => 'nonaktif']);

        // Navigasi baca dialihkan ke halaman status akun (EnsureUserIsActive), bukan 403.
        $this->actingAs($this->actor)->get($this->url())->assertRedirect(route('auth.pending'));
        $audit = AuditLog::where('tindakan', 'kegiatan.baca_ditolak')->sole();
        $this->assertSame('rencana_aksi', $audit->objek_tipe);
        $this->assertSame($this->rencanaAksi->id, $audit->objek_id);
    }

    private function url(?Kegiatan $kegiatan = null): string
    {
        $url = route('rencana-aksi.kegiatan.index', ['rencanaAksi' => $this->rencanaAksi->id]);

        return $kegiatan === null ? $url : route('rencana-aksi.kegiatan.edit', [
            'rencanaAksi' => $this->rencanaAksi->id, 'kegiatan' => $kegiatan->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['periode_id' => $this->tw1->id, 'nama' => 'Rapat koordinasi', 'tujuan' => null, 'sasaran_peserta' => null,
            'keterangan_peserta' => null, 'lokasi' => null, 'tanggal_rencana' => null, 'anggaran' => 1500000,
            'komponen_id' => null, 'arah_dampak' => 'menambah', 'catatan' => null];
    }

    /** @param array<string, mixed> $atribut */
    private function kegiatan(array $atribut = []): Kegiatan
    {
        return Kegiatan::create(array_replace([
            'unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->tw1->id,
            'nama' => 'Kegiatan awal', 'anggaran' => 0, 'created_by' => $this->actor->id,
        ], $atribut));
    }

    /** @param array<string, mixed> $atribut */
    private function klaim(Kegiatan $kegiatan, ?RencanaAksi $header = null, array $atribut = []): KlaimKegiatan
    {
        return KlaimKegiatan::create(array_replace([
            'rencana_aksi_id' => ($header ?? $this->rencanaAksi)->id, 'kegiatan_id' => $kegiatan->id,
            'arah_dampak' => 'menambah', 'sumber_klaim' => 'rencana_aksi',
            'created_by' => $this->actor->id, 'created_at' => now(),
        ], $atribut));
    }

    /** PIC dengan grant tulis kegiatan dan penugasan PJ efektif pada unit RA. */
    private function pic(bool $create = true): User
    {
        $pic = $this->createPicPegawai();
        foreach (array_filter(['kegiatan:read', $create ? 'kegiatan:create' : null]) as $permission) {
            $this->grantUnitPermission($pic, $permission);
        }

        return $pic;
    }

    private function rencanaAksiLain(Unit $unit): RencanaAksi
    {
        $indikator = $this->indikator->replicate();
        $indikator->kode = 'IKU-RA-LAIN';
        $indikator->unit_id = $unit->id;
        $indikator->save();

        return RencanaAksi::create(['indikator_id' => $indikator->id, 'unit_id' => $unit->id, 'jadwal_tahunan_id' => $this->jadwal->id,
            'penanggung_jawab_id' => $this->actor->id, 'tahun' => 2026, 'created_by' => $this->actor->id]);
    }

    /** Snapshot beku indikator RA lain agar halaman dan klaim RA itu punya konteks efektif. */
    private function snapshotIndikator(RencanaAksi $header): JadwalSnapshot
    {
        return JadwalSnapshot::create([
            ...$this->snapshot->only(['periode_mulai_id', 'unit_id', 'nama', 'definisi', 'satuan', 'presisi',
                'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'jadwal_id' => $this->jadwal->id,
            'indikator_id' => $header->indikator_id,
        ]);
    }

    /** @return array<string, mixed> */
    private function payloadFor(Kegiatan $kegiatan, ?string $nama = null): array
    {
        return [
            ...$kegiatan->rencanaAttributes(),
            'nama' => $nama ?? 'Nama diperbarui',
            'expected_state' => $kegiatan->fresh()->versionToken(),
        ];
    }
}
