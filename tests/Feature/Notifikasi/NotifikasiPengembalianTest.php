<?php

namespace Tests\Feature\Notifikasi;

use App\Actions\RencanaAksi\SahkanRencanaAksi;
use App\Actions\RencanaAksi\VerifikasiRencanaAksi;
use App\Events\PengukuranDikembalikan;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\User;
use App\Services\Notifikasi\HasilKirim;
use App\Services\Notifikasi\PengirimNotifikasiEksternal;
use App\Services\Notifikasi\PenyediaKanalNotifikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

/**
 * Pengembalian dan buka-kembali RA/pengukuran menulis satu item in-app untuk PJ efektif saat pengembalian,
 * dengan judul, ringkasan alasan, dan route tujuan yang disimpan sebagai teks jadi.
 */
class NotifikasiPengembalianTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        $this->reviewer = $this->createUserWithRole('perencanaan');
    }

    /** Urut waktu pembuatan; pemanggil yang mengirim lebih dari sekali memajukan jam agar `last()` deterministik. */
    private function items(): Collection
    {
        return DB::table('notifications')->orderBy('created_at')->orderBy('id')->get()->map(fn ($row) => (object) [...(array) $row, 'data' => json_decode($row->data, true)]);
    }

    /** Header beserta satu versi pengajuan sintetis (disahkan bila statusnya disahkan) agar gerbang review terbuka. */
    private function pengukuran(string $status = 'diajukan'): PengukuranKinerja
    {
        $p = PengukuranKinerja::create(['indikator_id' => $this->indikator->id, 'tahun' => 2026, 'periode_id' => $this->snapshot->periode_mulai_id,
            'jadwal_snapshot_id' => $this->snapshot->id, 'sumber_nilai' => 'manual', 'status_alur' => $status, 'created_by' => $this->actor->id]);
        $sah = $status === 'disahkan';
        KinerjaSnapshot::create(['pengukuran_id' => $p->id, 'jadwal_snapshot_id' => $this->snapshot->id, 'nomor' => 1, 'diajukan_by' => $this->actor->id, 'diajukan_at' => now(),
            'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'], 'snapshot' => ['fixture' => 'sintetis'],
            'disahkan_by' => $sah ? $this->actor->id : null, 'disahkan_at' => $sah ? now() : null]);

        return $p;
    }

    private function kembalikanPengukuran(PengukuranKinerja $p, string $catatan, bool $json = false): TestResponse
    {
        $data = ['versi' => $p->fresh()->versi, 'catatan' => $catatan];
        $url = "/verifikasi/{$p->id}/kembalikan";

        return $json ? $this->actingAs($this->reviewer)->postJson($url, $data) : $this->actingAs($this->reviewer)->post($url, $data);
    }

    private function kembalikanRencanaAksi(User $aktor, string $alasan): TestResponse
    {
        $versi = $this->createRencanaAksiSubmission();

        return $this->actingAs($aktor)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/kembalikan",
            ['expected_versi' => $this->rencanaAksi->fresh()->versi, 'versi_pengajuan_id' => $versi->id, 'alasan' => $alasan]);
    }

    public function test_rencana_aksi_dikembalikan_menulis_satu_item_untuk_pj_efektif_bukan_pj_lama_atau_aktor(): void
    {
        $pic = $this->createPicPegawai();

        $this->kembalikanRencanaAksi($this->reviewer, 'Perbaiki target TW I.')->assertSuccessful();

        $this->assertNotifikasiEksternal($pic, 'rencana_aksi', 'Perbaiki target TW I.', route('rencana-aksi.show', ['rencanaAksi' => $this->rencanaAksi->id]));

        $items = $this->items();
        $this->assertCount(1, $items);
        $item = $items->first();
        $this->assertSame($pic->id, $item->notifiable_id);
        $this->assertSame(User::class, $item->notifiable_type);
        $this->assertSame('rencana_aksi.dikembalikan', $item->type);
        $this->assertNull($item->read_at);
        $this->assertSame(['judul' => 'Rencana Aksi IKU-RA-01 2026 dikembalikan', 'ringkasan' => 'Perbaiki target TW I.',
            'route_name' => 'rencana-aksi.show', 'route_params' => ['rencanaAksi' => $this->rencanaAksi->id]], $item->data);
    }

    public function test_rencana_aksi_dibuka_kembali_menulis_item_jenis_dibuka_kembali(): void
    {
        $pic = $this->createPicPegawai();
        $versi = $this->createRencanaAksiSubmission();
        $tokens = fn (): array => ['expected_versi' => $this->rencanaAksi->fresh()->versi, 'versi_pengajuan_id' => $versi->id];
        app(VerifikasiRencanaAksi::class)->handle($this->reviewer, $this->rencanaAksi->id, $tokens());
        app(SahkanRencanaAksi::class)->handle($this->createUserWithRole('perencanaan'), $this->rencanaAksi->id, $tokens());

        $this->actingAs($this->reviewer)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/buka-kembali", $tokens() + ['alasan' => 'Koreksi resmi.'])->assertOk();

        $this->assertNotifikasiEksternal($pic, 'rencana_aksi', 'Koreksi resmi.', route('rencana-aksi.show', ['rencanaAksi' => $this->rencanaAksi->id]));

        $item = $this->items()->sole();
        $this->assertSame([$pic->id, 'rencana_aksi.dibuka_kembali'], [$item->notifiable_id, $item->type]);
        $this->assertSame(['judul' => 'Rencana Aksi IKU-RA-01 2026 dibuka kembali', 'ringkasan' => 'Koreksi resmi.',
            'route_name' => 'rencana-aksi.show', 'route_params' => ['rencanaAksi' => $this->rencanaAksi->id]], $item->data);
    }

    public function test_pengukuran_dikembalikan_menulis_item_bertautan_halaman_isi_pengukuran(): void
    {
        $pic = $this->createPicPegawai();
        $p = $this->pengukuran();

        $this->kembalikanPengukuran($p, 'Lampiran SPJ belum ada.')->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNotifikasiEksternal($pic, 'pengukuran', 'Lampiran SPJ belum ada.', route('pengukuran.edit', ['id' => $p->id]));

        $item = $this->items()->sole();
        $this->assertSame([$pic->id, 'pengukuran.dikembalikan'], [$item->notifiable_id, $item->type]);
        $this->assertSame(['judul' => 'Pengukuran IKU-RA-01 Triwulan I 2026 dikembalikan', 'ringkasan' => 'Lampiran SPJ belum ada.',
            'route_name' => 'pengukuran.edit', 'route_params' => ['id' => $p->id]], $item->data);
    }

    public function test_pengukuran_dibuka_kembali_menulis_item_jenis_dibuka_kembali(): void
    {
        $pic = $this->createPicPegawai();
        $p = $this->pengukuran('disahkan');

        $this->actingAs($this->reviewer)->post("/verifikasi/{$p->id}/buka-kembali", ['versi' => $p->fresh()->versi, 'alasan' => 'Koreksi nilai.'])->assertSessionHasNoErrors();

        $this->assertNotifikasiEksternal($pic, 'pengukuran', 'Koreksi nilai.', route('pengukuran.edit', ['id' => $p->id]));

        $item = $this->items()->sole();
        $this->assertSame([$pic->id, 'pengukuran.dibuka_kembali'], [$item->notifiable_id, $item->type]);
        $this->assertSame('Pengukuran IKU-RA-01 Triwulan I 2026 dibuka kembali', $item->data['judul']);
        $this->assertSame(['id' => $p->id], $item->data['route_params']);
    }

    private function assertNotifikasiEksternal(User $pic, string $tipe, string $alasan, string $url): void
    {
        $rows = DB::table('notifikasi_pengiriman')->where('penerima_user_id', $pic->id)->where('objek_tipe', $tipe)->get();
        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(['email', 'whatsapp'], $rows->pluck('kanal')->all());
        foreach ($rows as $row) {
            $parameter = json_decode($row->parameter, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($alasan, $parameter['alasan']);
            $this->assertSame($url, $parameter['url']);
        }
    }

    public function test_penerima_nonaktif_dibatalkan_sebelum_job_dikirim(): void
    {
        config(['notifikasi.kanal.email.aktif' => true, 'notifikasi.kanal.whatsapp.aktif' => true]);
        $pic = $this->createPicPegawai();
        $pic->update(['status' => 'nonaktif', 'nomor_telepon' => '081234567890']);
        $this->kembalikanPengukuran($this->pengukuran(), 'Perbaiki.')->assertSessionHasNoErrors();

        $rows = DB::table('notifikasi_pengiriman')->where('penerima_user_id', $pic->id)->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['dibatalkan', 'dibatalkan'], $rows->pluck('status')->all());
        $this->assertSame(['penerima_tidak_berhak', 'penerima_tidak_berhak'], $rows->pluck('kode_hasil')->all());
    }

    public function test_replay_pengembalian_idempoten_dan_versi_baru_membuat_dua_baris_baru(): void
    {
        $pic = $this->createPicPegawai();
        $p = $this->pengukuran();
        $this->kembalikanPengukuran($p, 'Perbaiki.')->assertSessionHasNoErrors();
        $versi = $p->fresh()->versi;
        PengukuranDikembalikan::dispatch($p->id, $versi, $pic->id, $this->reviewer->id, 'Perbaiki.');
        $this->assertSame(2, DB::table('notifikasi_pengiriman')->count());

        PengukuranDikembalikan::dispatch($p->id, $versi + 1, $pic->id, $this->reviewer->id, 'Perbaiki lagi.');
        $this->assertSame(4, DB::table('notifikasi_pengiriman')->count());
    }

    public function test_pengembalian_mengantre_dua_job_jika_kanal_dan_kontak_tersedia(): void
    {
        config(['notifikasi.kanal.email.aktif' => true, 'notifikasi.kanal.whatsapp.aktif' => true]);
        $this->actor->update(['nomor_telepon' => '081234567890']);
        $this->kembalikanRencanaAksi($this->actor, 'Perbaiki.')->assertSuccessful();

        $rows = DB::table('notifikasi_pengiriman')->where('penerima_user_id', $this->actor->id)->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['antre', 'antre'], $rows->pluck('status')->all());
        $this->assertSame(2, DB::table('jobs')->where('queue', 'notifikasi')->count());
    }

    public function test_tautan_eksternal_memerlukan_login_dan_memeriksa_izin_yang_dicabut(): void
    {
        $pic = $this->createPicPegawai();
        $this->kembalikanRencanaAksi($this->reviewer, 'Perbaiki.')->assertSuccessful();
        $row = DB::table('notifikasi_pengiriman')->where('penerima_user_id', $pic->id)->first();
        $url = json_decode($row->parameter, true, flags: JSON_THROW_ON_ERROR)['url'];

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
        $this->denyPermission($pic, 'rencana_aksi:read', $this->unit->id);
        $this->actingAs($pic)->get($url)->assertForbidden();
    }

    public function test_provider_gagal_sementara_tidak_mengubah_pengembalian(): void
    {
        config(['notifikasi.kanal.email.aktif' => true]);
        $this->mock(PenyediaKanalNotifikasi::class)->shouldReceive('kirim')->once()->andReturn(HasilKirim::sementara(60));
        $this->kembalikanRencanaAksi($this->actor, 'Perbaiki.')->assertSuccessful();
        $row = DB::table('notifikasi_pengiriman')->where('kanal', 'email')->first();

        app(PengirimNotifikasiEksternal::class)->kirim($row->id, 4);

        $this->assertSame('dikembalikan', $this->rencanaAksi->fresh()->status_alur);
        $this->assertDatabaseHas('notifikasi_pengiriman', ['id' => $row->id, 'status' => 'antre', 'kode_hasil' => 'provider_sementara']);
    }

    /** Header unik per indikator × tahun × periode; pengembalian berikutnya memakai header yang sama setelah diajukan ulang. */
    private function diajukanUlang(PengukuranKinerja $p): PengukuranKinerja
    {
        PengukuranKinerja::whereKey($p->id)->update(['status_alur' => 'diajukan']);

        return $p;
    }

    public function test_ringkasan_alasan_diringkas_dan_dibatasi_lebar_200_termasuk_elipsis(): void
    {
        $this->createPicPegawai();
        $p = $this->pengukuran();
        $this->kembalikanPengukuran($p, "Rusak\x07teks", json: true)->assertUnprocessable();
        $this->assertCount(0, $this->items());

        $this->kembalikanPengukuran($p, "Lampiran\tSPJ\n\nbelum   ada")->assertSessionHasNoErrors();
        $this->assertSame('Lampiran SPJ belum ada', $this->items()->last()->data['ringkasan']);

        foreach ([199 => 199, 200 => 200, 201 => 200] as $lebar => $hasil) {
            $this->travel(1)->second();
            $this->kembalikanPengukuran($this->diajukanUlang($p), str_repeat('a', $lebar))->assertSessionHasNoErrors();
            $ringkasan = $this->items()->last()->data['ringkasan'];
            $this->assertSame($hasil, mb_strwidth($ringkasan), "lebar {$lebar}");
            $this->assertSame($lebar > 199, str_ends_with($ringkasan, '…'), "elipsis {$lebar}");
        }
    }

    public function test_aktor_yang_juga_pj_efektif_tetap_menerima_item(): void
    {
        // Superadmin fixture adalah PJ efektif sekaligus pengembali.
        $this->kembalikanRencanaAksi($this->actor, 'Perbaiki.')->assertSuccessful();

        $this->assertSame($this->actor->id, $this->items()->sole()->notifiable_id);
    }

    public function test_pj_nonaktif_atau_tanpa_peran_aktif_dan_indikator_tanpa_pj_tidak_menerima_item(): void
    {
        $p = $this->pengukuran();
        $nonaktif = $this->createPicPegawai();
        $nonaktif->update(['status' => 'nonaktif']);
        $this->kembalikanPengukuran($p, 'Alasan.')->assertSessionHasNoErrors();

        $tanpaPeran = $this->createPicPegawai();
        DB::table('user_roles')->where('user_id', $tanpaPeran->id)->delete();
        $this->kembalikanPengukuran($this->diajukanUlang($p), 'Alasan.')->assertSessionHasNoErrors();

        PenugasanIndikator::where('indikator_id', $this->indikator->id)->delete();
        $this->kembalikanPengukuran($this->diajukanUlang($p), 'Alasan.')->assertSessionHasNoErrors();

        $this->assertCount(0, $this->items());
    }

    public function test_tanpa_pj_mutasi_sukses_dan_penerima_kosong_dicatat_tanpa_isi_alasan(): void
    {
        PenugasanIndikator::where('indikator_id', $this->indikator->id)->delete();
        $p = $this->pengukuran();
        Log::spy();

        $this->kembalikanPengukuran($p, 'Alasan rahasia.')->assertSessionHasNoErrors();

        $this->assertSame('dikembalikan', $p->fresh()->status_alur);
        $this->assertCount(0, $this->items());
        $this->assertSame(0, DB::table('notifikasi_pengiriman')->count());
        Log::shouldHaveReceived('info')->twice();
        Log::shouldHaveReceived('info')->withArgs(fn (string $pesan, array $konteks): bool => $pesan === 'Notifikasi in-app tanpa penerima.'
            && $konteks === ['jenis' => 'pengukuran.dikembalikan', 'objek_id' => $p->id])->once();
        Log::shouldHaveReceived('info')->withArgs(fn (string $pesan, array $konteks): bool => $pesan === 'Notifikasi eksternal tanpa penerima.'
            && $konteks === ['jenis' => 'pengukuran.dikembalikan', 'objek_id' => $p->id])->once();
        Log::shouldNotHaveReceived('error');
    }

    public function test_logger_yang_gagal_di_listener_tidak_membatalkan_mutasi_yang_sudah_commit(): void
    {
        PenugasanIndikator::where('indikator_id', $this->indikator->id)->delete();
        $p = $this->pengukuran();
        // Channel log melempar (ignore_exceptions=false); galat ini terjadi setelah commit dan tidak boleh lolos ke Action,
        // karena kompensasinya menganggap transaksi batal dan menghapus berkas unggahan yang sudah dirujuk DB.
        Log::spy();
        // Listener in-app dan eksternal masing-masing mencatat "tanpa penerima" sekali; keduanya harus menelan galat logger.
        Log::shouldReceive('info')->twice()->andThrow(new RuntimeException('log mati'));
        Log::shouldReceive('error')->andThrow(new RuntimeException('log mati'));

        $this->kembalikanPengukuran($p, 'Alasan.')->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('dikembalikan', $p->fresh()->status_alur);
    }

    public function test_pj_dengan_deny_eksplisit_pengukuran_read_hanya_kehilangan_item_pengukuran(): void
    {
        $pic = $this->createPicPegawai();
        $this->denyPermission($pic, 'pengukuran:read', $this->unit->id);
        Log::spy();

        $this->kembalikanPengukuran($this->pengukuran(), 'Alasan rahasia.')->assertSessionHasNoErrors();
        $this->kembalikanRencanaAksi($this->reviewer, 'Alasan rahasia.')->assertSuccessful();

        $this->assertSame(['rencana_aksi.dikembalikan'], $this->items()->pluck('type')->all());
        $this->assertSame($pic->id, $this->items()->sole()->notifiable_id);
        Log::shouldHaveReceived('info')->withArgs(fn (string $pesan, array $konteks): bool => str_contains($pesan, 'ditolak eksplisit')
            && $konteks === ['user_ids' => [$pic->id]])->once();
    }

    public function test_pj_dengan_deny_eksplisit_rencana_aksi_read_hanya_kehilangan_item_rencana_aksi(): void
    {
        $pic = $this->createPicPegawai();
        $this->denyPermission($pic, 'rencana_aksi:read', $this->unit->id);
        Log::spy();

        $this->kembalikanPengukuran($this->pengukuran(), 'Alasan rahasia.')->assertSessionHasNoErrors();
        $this->kembalikanRencanaAksi($this->reviewer, 'Alasan rahasia.')->assertSuccessful();

        $this->assertSame(['pengukuran.dikembalikan'], $this->items()->pluck('type')->all());
        $this->assertSame($pic->id, $this->items()->sole()->notifiable_id);
        Log::shouldHaveReceived('info')->withArgs(fn (string $pesan, array $konteks): bool => str_contains($pesan, 'ditolak eksplisit')
            && $konteks === ['user_ids' => [$pic->id]])->once();
    }

    public function test_pj_tanpa_grant_unit_tetap_menerima_item_pengembalian(): void
    {
        $pic = $this->createPicPegawai();
        DB::table('user_permission_granted')->where('user_id', $pic->id)->delete();

        $this->kembalikanPengukuran($this->pengukuran(), 'Perbaiki.')->assertSessionHasNoErrors();
        $this->kembalikanRencanaAksi($this->reviewer, 'Perbaiki.')->assertSuccessful();

        $this->assertSame([$pic->id, $pic->id], $this->items()->pluck('notifiable_id')->all());
    }
}
