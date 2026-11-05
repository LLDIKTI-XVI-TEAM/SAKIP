<?php

namespace Tests\Feature\Notifikasi;

use App\Jobs\KirimNotifikasiEksternal;
use App\Models\NotifikasiPengiriman;
use App\Models\Pengaturan;
use App\Models\PengukuranKinerja;
use App\Services\Notifikasi\AntrekanNotifikasiEksternal;
use App\Services\Notifikasi\HasilKirim;
use App\Services\Notifikasi\PembacaStatusPengiriman;
use App\Services\Notifikasi\PengirimNotifikasiEksternal;
use App\Services\Notifikasi\PenyediaKanalNotifikasi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

class NotifikasiEksternalTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        config(['notifikasi.kanal.email.aktif' => true]);
        Http::preventStrayRequests();
    }

    public function test_kejadian_sama_hanya_membuat_satu_outbox_job_dan_indeks_unik_menolak_duplikasi(): void
    {
        $row = $this->antre();
        $this->assertSame($row->id, $this->antre()->id);
        $this->assertDatabaseCount('notifikasi_pengiriman', 1);
        $this->assertDatabaseCount('jobs', 1);
        $payload = json_decode(DB::table('jobs')->value('payload'), true);
        $this->assertSame($row->id, $payload['notifikasi_pengiriman_id']);
        $this->assertStringNotContainsString($this->actor->email, json_encode($payload));
        try {
            DB::transaction(fn () => DB::table('notifikasi_pengiriman')->insert([
                ...$row->getAttributes(), 'id' => (string) Str::uuid(),
            ]));
            $this->fail('Indeks unik harus menolak duplikasi tabel mentah.');
        } catch (QueryException $e) {
            $this->assertSame('23505', $e->errorInfo[0]);
        }
        $this->antre('pengembalian:versi-kedua');
        $this->assertDatabaseCount('notifikasi_pengiriman', 2);
    }

    public function test_rollback_menghapus_outbox_job_dan_unique_lock(): void
    {
        DB::beginTransaction();
        $this->antre();
        $this->assertDatabaseCount('jobs', 1);
        DB::rollBack();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
        $this->assertDatabaseCount('cache_locks', 0);
        $this->antre();
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_uuid_alias_objek_dan_penerima_tetap_satu_pengiriman(): void
    {
        $row = $this->antre();
        $tujuan = clone $this->rencanaAksi;
        $tujuan->id = strtoupper($tujuan->id);
        $replay = app(AntrekanNotifikasiEksternal::class)->antre('pengembalian:versi-1', $tujuan, (string) $this->jadwal->id,
            [strtoupper($this->actor->id)], ['email'], 'Perbaiki {{alasan}}', ['alasan' => 'Narasi perlu diperjelas.'])->first();
        $this->assertSame($row->id, $replay->id);
        $this->assertDatabaseCount('notifikasi_pengiriman', 1);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_kegagalan_enqueue_tidak_meninggalkan_outbox(): void
    {
        Event::listen(JobQueueing::class, function ($event): void {
            if ($event->connectionName === 'notifikasi') {
                throw new RuntimeException('enqueue dihentikan oleh test');
            }
        });
        try {
            $this->antre();
            $this->fail('Dispatch harus gagal.');
        } catch (RuntimeException $e) {
            $this->assertSame('enqueue dihentikan oleh test', $e->getMessage());
        }
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('cache_locks', 0);
    }

    public function test_kegagalan_penyimpanan_tidak_meneruskan_sql_alamat_atau_previous_exception(): void
    {
        Log::spy();
        try {
            app(AntrekanNotifikasiEksternal::class)->antre('pengembalian:versi-1', $this->rencanaAksi, (string) $this->jadwal->id,
                [$this->actor->id], ['email'], 'Pesan sintetis', ['alasan' => "Narasi\0sintetis"]);
            $this->fail('PostgreSQL JSONB harus menolak karakter nul.');
        } catch (\Throwable $e) {
            $this->assertSame('notifikasi.gangguan_penyimpanan', $e->getMessage());
            $this->assertNull($e->getPrevious());
            report($e);
        }
        Log::shouldHaveReceived('error')->once()->withArgs(fn ($message, $context) => $message === 'notifikasi.gangguan_penyimpanan'
            && $context['exception']->getMessage() === $message && $context['exception']->getPrevious() === null);
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('cache_locks', 0);
    }

    public function test_kanal_mati_terminal_dan_tidak_dihidupkan_ulang_oleh_replay(): void
    {
        config(['notifikasi.kanal.email.aktif' => false]);
        $row = $this->antre();
        $this->assertSame('dibatalkan', $row->status);
        $this->assertSame('kanal_nonaktif', $row->kode_hasil);
        config(['notifikasi.kanal.email.aktif' => true]);
        $this->assertSame('dibatalkan', $this->antre()->status);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_driver_tak_dikenal_gagal_aman_tanpa_menyimpan_nilai_konfigurasi(): void
    {
        config(['notifikasi.kanal.email.driver' => 'RAHASIA-bukan-driver']);
        try {
            $this->antre();
            $this->fail('Driver tak dikenal harus ditolak.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Driver notifikasi belum didukung.', $e->getMessage());
        }
        $this->assertDatabaseCount('notifikasi_pengiriman', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_saklar_dibaca_ulang_setelah_enqueue_sebelum_worker(): void
    {
        $provider = $this->provider();
        config(['notifikasi.kanal.email.aktif' => false]);
        $saklar = Pengaturan::create(['kunci' => 'notifikasi.kanal_email_aktif', 'nilai' => '1', 'tipe' => 'boolean', 'grup' => 'notifikasi']);
        $row = $this->antre();
        $this->assertSame('antre', $row->status);
        $saklar->update(['nilai' => '0']);
        $this->kirim($row);
        $this->assertSame('kanal_nonaktif', $row->fresh()->kode_hasil);
        $this->assertSame(0, $provider->calls);
    }

    public function test_saklar_dimatikan_sebelum_retry_membatalkan_tanpa_upaya_kirim_baru(): void
    {
        $provider = $this->provider([HasilKirim::sementara(60)]);
        $saklar = Pengaturan::create(['kunci' => 'notifikasi.kanal_email_aktif', 'nilai' => '1', 'tipe' => 'boolean', 'grup' => 'notifikasi']);
        $row = $this->antre();
        $this->kirim($row);
        $this->assertSame('antre', $row->fresh()->status);
        $saklar->update(['nilai' => '0']);
        $this->travel(61)->seconds();
        $this->kirim($row);
        $this->assertSame('dibatalkan', $row->fresh()->status);
        $this->assertSame('kanal_nonaktif', $row->fresh()->kode_hasil);
        $this->assertSame(1, $provider->calls);
        $this->assertSame(1, $row->fresh()->percobaan);
    }

    #[DataProvider('zonaSesiDatabase')]
    public function test_jeda_retry_tidak_bergantung_zona_waktu_sesi_database(string $zona): void
    {
        // Perubahan zona ikut rollback transaksi test.
        DB::statement("SET TIME ZONE '$zona'");
        $provider = $this->provider([HasilKirim::sementara(300), HasilKirim::diterima('zona')]);
        $row = $this->antre();
        $this->kirim($row);
        $this->kirim($row);
        $this->assertSame(1, $provider->calls);
        $this->assertSame('antre', $row->fresh()->status);
        $this->assertSame(now()->addSeconds(300)->timestamp, $row->fresh()->coba_lagi_pada->timestamp);
    }

    public static function zonaSesiDatabase(): array
    {
        return ['WITA' => ['Asia/Makassar'], 'Halifax' => ['America/Halifax']];
    }

    public function test_hak_baca_dicabut_atau_akun_nonaktif_membatalkan_tanpa_mengungkap_detail(): void
    {
        $provider = $this->provider();
        $row = $this->antre();
        $this->denyPermission($this->actor, 'rencana_aksi:read', $this->unit->id);
        $this->kirim($row);
        $this->assertSame('penerima_tidak_berhak', $row->fresh()->kode_hasil);
        $this->assertSame('dibatalkan', $row->fresh()->status);
        $this->assertSame(0, $provider->calls);
        $this->assertNull($row->fresh()->rujukan_provider);
    }

    public function test_akun_dinonaktifkan_sebelum_kirim_dibatalkan(): void
    {
        $row = $this->antre();
        $this->actor->update(['status' => 'nonaktif']);
        $this->kirim($row);
        $this->assertSame('penerima_tidak_berhak', $row->fresh()->kode_hasil);
    }

    public function test_tujuan_pengukuran_memakai_policy_view_yang_sama_dengan_tautan(): void
    {
        $pengukuran = PengukuranKinerja::create([
            'indikator_id' => $this->indikator->id, 'tahun' => 2026,
            'periode_id' => $this->snapshot->periode_mulai_id, 'jadwal_snapshot_id' => $this->snapshot->id,
            'sumber_nilai' => 'manual', 'created_by' => $this->actor->id,
        ]);
        $provider = $this->provider();
        $antre = fn ($event) => app(AntrekanNotifikasiEksternal::class)->antre($event, $pengukuran, (string) $pengukuran->periode_id, [$this->actor->id], ['email'], 'Perbaiki pengukuran', [])->first();
        $pertama = $antre('pengukuran:1');
        $this->kirim($pertama);
        $this->assertSame('diterima_provider', $pertama->fresh()->status);
        $kedua = $antre('pengukuran:2');
        $this->denyPermission($this->actor, 'pengukuran:read', $this->unit->id);
        $this->kirim($kedua);
        $this->assertSame('penerima_tidak_berhak', $kedua->fresh()->kode_hasil);
        $this->assertSame(1, $provider->calls);
    }

    public function test_pergantian_pj_tidak_mengubah_penerima_historis_yang_masih_berhak(): void
    {
        $row = $this->antre();
        $this->createPicPegawai();
        $provider = $this->provider();
        $this->kirim($row);
        $this->assertSame($this->actor->id, $row->fresh()->penerima_user_id);
        $this->assertSame('diterima_provider', $row->fresh()->status);
        $this->assertSame(1, $provider->calls);
    }

    public function test_nomor_kosong_gagal_permanen_tetapi_email_tetap_diantrekan(): void
    {
        config(['notifikasi.kanal.whatsapp.aktif' => true]);
        $rows = $this->antreSemua(['whatsapp', 'email']);
        $wa = $rows->firstWhere('kanal', 'whatsapp');
        $this->assertSame('gagal', $wa->status);
        $this->assertSame('kontak_tidak_tersedia', $wa->kode_hasil);
        $this->assertSame('antre', $rows->firstWhere('kanal', 'email')->status);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_provider_log_menyimpan_simulasi_tanpa_http_atau_alamat_di_log(): void
    {
        Log::spy();
        $row = $this->antre();
        $this->kirim($row);
        $this->assertSame('diterima_provider', $row->fresh()->status);
        $this->assertTrue($row->fresh()->simulasi);
        Log::shouldHaveReceived('info')->once()->with('notifikasi.simulasi_diterima', [
            'pengiriman_id' => $row->id, 'kanal' => 'email', 'rujukan' => 'log:'.$row->id,
        ]);
        Http::assertNothingSent();
    }

    public function test_status_terminal_tidak_memanggil_provider_dua_kali(): void
    {
        $provider = $this->provider();
        $row = $this->antre();
        $this->kirim($row);
        $this->kirim($row);
        $this->assertSame(1, $provider->calls);
        $this->assertSame(1, $row->fresh()->percobaan);
    }

    public function test_penolakan_sementara_lalu_sukses_memakai_identitas_dan_payload_yang_sama(): void
    {
        $provider = $this->provider([HasilKirim::sementara(120), HasilKirim::diterima('aman-001')]);
        $row = $this->antre();
        $this->kirim($row);
        $this->assertSame('antre', $row->fresh()->status);
        $this->assertSame('provider_sementara', $row->fresh()->kode_hasil);
        $this->travel(121)->seconds();
        $this->kirim($row);
        $this->assertSame('diterima_provider', $row->fresh()->status);
        $this->assertSame(2, $row->fresh()->percobaan);
        $this->assertSame($provider->payloads[0], $provider->payloads[1]);
    }

    public function test_respons_terputus_tidak_diretry_dan_exception_mentah_tidak_dilaporkan(): void
    {
        Log::spy();
        $provider = $this->provider([new RuntimeException('token=RAHASIA alamat=private@example.test')]);
        $row = $this->antre();
        $this->kirim($row);
        $this->kirim($row);
        $this->assertSame('perlu_rekonsiliasi', $row->fresh()->status);
        $this->assertSame('perlu_rekonsiliasi', $row->fresh()->kode_hasil);
        $this->assertSame(1, $provider->calls);
        $this->assertDatabaseCount('failed_jobs', 0);
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_penanda_sebelum_provider_melindungi_crash_dan_callback_gagal_terlambat(): void
    {
        $row = $this->antre();
        $this->provider([function (NotifikasiPengiriman $p): HasilKirim {
            $this->assertSame('perlu_rekonsiliasi', $p->fresh()->status);

            return HasilKirim::diterima('aman-002');
        }]);
        $this->kirim($row);
        (new KirimNotifikasiEksternal($row->id))->failed(new RuntimeException('detail tidak aman'));
        $this->assertSame('diterima_provider', $row->fresh()->status);
    }

    public function test_pembaca_memakai_filter_izin_dan_label_simulasi_tanpa_alamat_atau_payload(): void
    {
        $row = $this->antre();
        $this->kirim($row);
        $page = app(PembacaStatusPengiriman::class)->handle($this->actor, ['status' => 'diterima_provider', 'kanal' => 'email', 'penerima' => $this->actor->id]);
        $data = $page->toArray();
        $this->assertSame('Simulasi diterima', $data['data'][0]['label_status']);
        $this->assertSame(1, $data['total']);
        $this->assertArrayNotHasKey('alamat', $data['data'][0]);
        $this->assertArrayNotHasKey('parameter', $data['data'][0]);
        $this->assertArrayNotHasKey('template', $data['data'][0]);
        $this->assertSame(0, app(PembacaStatusPengiriman::class)->handle($this->actor, ['kanal' => 'whatsapp'])->total());
        $this->assertSame(1, app(PembacaStatusPengiriman::class)->handle($this->actor, ['selesai' => now()->toDateString()])->total());
        $this->assertSame(0, app(PembacaStatusPengiriman::class)->handle($this->actor, ['mulai' => now()->addDay()->toDateString()])->total());
        $pegawai = $this->createUserWithRole('pegawai');
        $this->expectException(AuthorizationException::class);
        app(PembacaStatusPengiriman::class)->handle($pegawai, []);
    }

    public function test_reservasi_menunggu_tidak_mengurangi_empat_upaya_kirim_siklus_manual(): void
    {
        $provider = $this->provider(array_fill(0, 5, HasilKirim::sementara(3600)));
        $row = $this->antre();
        $row->update(['percobaan' => 3, 'percobaan_siklus' => 3]);
        DB::table('jobs')->update(['attempts' => 3]);
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $failed = DB::table('failed_jobs')->first();
        $this->assertNotNull($failed);
        $this->artisan('queue:retry', ['id' => [$failed->uuid]])->assertExitCode(0);
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame(0, $row->fresh()->percobaan_siklus);
        for ($i = 1; $i <= 4; $i++) {
            $this->travel(61)->minutes();
            $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
            $this->assertSame($i, $row->fresh()->percobaan_siklus);
            $this->assertSame($i < 4 ? 'antre' : 'gagal', $row->fresh()->status);
        }
        $this->assertSame(5, $provider->calls);
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_redelivery_exhausted_mencatat_failed_job_setelah_crash_sebelum_fail(): void
    {
        $provider = $this->provider([HasilKirim::sementara()]);
        $row = $this->antre();
        $row->update(['percobaan' => 3, 'percobaan_siklus' => 3]);
        $reserved = Queue::connection('notifikasi')->pop('notifikasi');
        $this->assertNotNull($reserved);
        app(PengirimNotifikasiEksternal::class)->kirim($row->id, 4);
        $this->assertSame('gagal', $row->fresh()->status);
        $this->travel(91)->seconds();
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertSame(1, $provider->calls);
        $this->assertSame(4, $row->fresh()->percobaan);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_worker_database_retry_manual_kumulatif_dan_exception_failed_jobs_aman(): void
    {
        $provider = $this->provider([HasilKirim::sementara(), HasilKirim::diterima('aman-akhir')]);
        $row = $this->antre();
        $row->update(['percobaan' => 3, 'percobaan_siklus' => 3]);
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame('gagal', $row->fresh()->status);
        $this->assertSame('percobaan_habis', $row->fresh()->kode_hasil);
        $failed = DB::table('failed_jobs')->first();
        $this->assertNotNull($failed);
        $this->assertStringNotContainsString($this->actor->email, $failed->payload.$failed->exception);
        $this->artisan('queue:retry', ['id' => [$failed->uuid]])->assertExitCode(0);
        $this->assertSame('antre', $row->fresh()->status);
        $this->travel(31)->minutes();
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame('diterima_provider', $row->fresh()->status);
        $this->assertSame(5, $row->fresh()->percobaan);
        $this->assertSame(2, $provider->calls);
    }

    public function test_exception_penyimpanan_tetap_dibatasi_empat_tanpa_membocorkan_detail(): void
    {
        $row = $this->antre();
        $this->mock(PengirimNotifikasiEksternal::class)->shouldReceive('kirim')->with($row->id, 4)->times(4)
            ->andThrow(new RuntimeException('token=RAHASIA alamat=private@example.test'));
        for ($i = 1; $i <= 4; $i++) {
            $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
            $this->assertSame($i < 4 ? 'antre' : 'gagal', $row->fresh()->status);
            $this->travel(31)->minutes();
        }
        $this->assertSame(0, $row->fresh()->percobaan);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 0);
        $failed = DB::table('failed_jobs')->first();
        $this->assertStringContainsString('notifikasi.gangguan_penyimpanan', $failed->exception);
        $this->assertStringNotContainsString('RAHASIA', $failed->exception);
        $this->assertStringNotContainsString('private@example.test', $failed->exception);
    }

    public function test_worker_menghormati_jeda_provider_dan_recovery_tidak_menggandakan_delayed_job(): void
    {
        $this->provider([HasilKirim::sementara(300)]);
        $row = $this->antre();
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame(now()->addSeconds(300)->timestamp, (int) DB::table('jobs')->value('available_at'));
        $this->travel(11)->minutes();
        $this->artisan('notifikasi:antre-ulang')->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame('antre', $row->fresh()->status);
    }

    public function test_redelivery_setelah_worker_putus_sebelum_release_tetap_menjadwalkan_retry(): void
    {
        $provider = $this->provider([HasilKirim::sementara(3600), HasilKirim::diterima('aman-redelivery')]);
        $row = $this->antre();
        $reserved = Queue::connection('notifikasi')->pop('notifikasi');
        $this->assertNotNull($reserved);
        app(PengirimNotifikasiEksternal::class)->kirim($row->id, 4);
        $batas = $row->fresh()->coba_lagi_pada->timestamp;

        // Simulasikan crash sesudah hasil pasti tersimpan, sebelum job melakukan release.
        $this->travel(91)->seconds();
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame($batas, (int) DB::table('jobs')->value('available_at'));
        $this->assertSame(1, $provider->calls);
        $this->assertSame(1, $row->fresh()->percobaan);
        $this->travel(60)->minutes();
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame('diterima_provider', $row->fresh()->status);
        $this->assertSame(2, $provider->calls);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_retry_manual_tetap_menghormati_batas_tunggu_provider(): void
    {
        $provider = $this->provider([HasilKirim::sementara(3600), HasilKirim::diterima('aman-manual-jeda')]);
        $row = $this->antre();
        $row->update(['percobaan' => 3, 'percobaan_siklus' => 3]);
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $batas = $row->fresh()->coba_lagi_pada->timestamp;
        $failed = DB::table('failed_jobs')->first();
        $this->assertNotNull($failed);
        $this->artisan('queue:retry', ['id' => [$failed->uuid]])->assertExitCode(0);
        $this->assertSame($batas, $row->fresh()->coba_lagi_pada?->timestamp);
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame($batas, (int) DB::table('jobs')->value('available_at'));
        $this->assertSame(1, $provider->calls);
        $this->assertSame(4, $row->fresh()->percobaan);
        $this->travel(61)->minutes();
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame('diterima_provider', $row->fresh()->status);
        $this->assertSame(2, $provider->calls);
        $this->assertSame(5, $row->fresh()->percobaan);
    }

    public function test_recovery_setelah_tiga_upaya_tidak_memulai_siklus_retry_baru(): void
    {
        $this->provider([HasilKirim::sementara(), HasilKirim::sementara(), HasilKirim::sementara(), HasilKirim::sementara(), HasilKirim::diterima('aman-manual')]);
        $row = $this->antre();
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
            $this->travel(31)->minutes();
        }
        $this->assertSame(3, $row->fresh()->percobaan);
        DB::table('jobs')->delete();
        $this->artisan('notifikasi:antre-ulang')->assertExitCode(0);
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame(4, $row->fresh()->percobaan);
        $this->assertSame('gagal', $row->fresh()->status);
        $failed = DB::table('failed_jobs')->first();
        $this->assertNotNull($failed);
        $this->artisan('queue:retry', ['id' => [$failed->uuid]])->assertExitCode(0);
        $this->travel(31)->minutes();
        $this->artisan('queue:work', ['connection' => 'notifikasi', '--queue' => 'notifikasi', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame('diterima_provider', $row->fresh()->status);
        $this->assertSame(5, $row->fresh()->percobaan);
    }

    public function test_pemulihan_hanya_orphan_belum_dicoba_dan_bukan_reserved_atau_terminal(): void
    {
        $row = $this->antre();
        $this->travel(11)->minutes();
        DB::table('jobs')->update(['reserved_at' => now()->timestamp]);
        $this->artisan('notifikasi:antre-ulang')->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 1);
        DB::table('jobs')->delete();
        $this->artisan('notifikasi:antre-ulang')->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 1);
        DB::table('jobs')->delete();
        $row->update(['status' => 'perlu_rekonsiliasi', 'kode_hasil' => 'perlu_rekonsiliasi']);
        $this->artisan('notifikasi:antre-ulang')->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_template_parameter_dan_alamat_tidak_berubah_saat_event_diputar_ulang(): void
    {
        $row = $this->antre();
        $this->actor->update(['email' => 'baru@example.test']);
        app(AntrekanNotifikasiEksternal::class)->antre('pengembalian:versi-1', $this->rencanaAksi, (string) $this->jadwal->id, [$this->actor->id], ['email'], 'Template baru', ['alasan' => 'Baru']);
        $this->assertSame('Perbaiki {{alasan}}', $row->fresh()->template);
        $this->assertNotSame('baru@example.test', $row->fresh()->alamat);
        $this->assertSame(['alasan' => 'Narasi perlu diperjelas.'], $row->fresh()->parameter);
    }

    public function test_pemulihan_retry_yatim_menunggu_jeda_dan_tidak_mengulang_hasil_ambigu(): void
    {
        $this->provider([HasilKirim::sementara(1800)]);
        $row = $this->antre();
        $this->kirim($row);
        DB::table('jobs')->delete();
        $this->travel(11)->minutes();
        $this->artisan('notifikasi:antre-ulang')->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 0);
        $this->travel(20)->minutes();
        $this->artisan('notifikasi:antre-ulang')->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame(1, $row->fresh()->percobaan);
    }

    public function test_penolakan_provider_permanen_tidak_diretry(): void
    {
        $provider = $this->provider([HasilKirim::permanen()]);
        $row = $this->antre();
        $this->kirim($row);
        $this->kirim($row);
        $this->assertSame('provider_ditolak', $row->fresh()->kode_hasil);
        $this->assertSame(1, $provider->calls);
    }

    public function test_rentang_tanggal_pembaca_mengikuti_wita_dengan_penyimpanan_utc(): void
    {
        $row = $this->antre();
        $row->update(['created_at' => '2026-03-09 17:00:00+00']);
        $page = app(PembacaStatusPengiriman::class)->handle($this->actor, ['mulai' => '2026-03-10', 'selesai' => '2026-03-10']);
        $this->assertSame(1, $page->total());
        $row->update(['created_at' => '2026-03-10 16:00:00+00']);
        $this->assertSame(0, app(PembacaStatusPengiriman::class)->handle($this->actor, ['mulai' => '2026-03-10', 'selesai' => '2026-03-10'])->total());
    }

    private function antre(string $kejadian = 'pengembalian:versi-1'): NotifikasiPengiriman
    {
        return $this->antreSemua(['email'], $kejadian)->first();
    }

    private function antreSemua(array $kanal, string $kejadian = 'pengembalian:versi-1')
    {
        return app(AntrekanNotifikasiEksternal::class)->antre($kejadian, $this->rencanaAksi, (string) $this->jadwal->id,
            [$this->actor->id], $kanal, 'Perbaiki {{alasan}}', ['alasan' => 'Narasi perlu diperjelas.']);
    }

    private function kirim(NotifikasiPengiriman $row): void
    {
        (new KirimNotifikasiEksternal($row->id))->handle(app(PengirimNotifikasiEksternal::class));
    }

    private function provider(?array $hasil = null): object
    {
        $provider = new class($hasil ?? [HasilKirim::diterima('aman-001')]) implements PenyediaKanalNotifikasi
        {
            public int $calls = 0;

            public array $payloads = [];

            public function __construct(private array $hasil) {}

            public function kirim(NotifikasiPengiriman $pengiriman): HasilKirim
            {
                $this->calls++;
                $this->payloads[] = $pengiriman->only(['kunci_idempoten', 'alamat', 'template', 'parameter']);
                $hasil = array_shift($this->hasil);
                if ($hasil instanceof \Throwable) {
                    throw $hasil;
                }

                return is_callable($hasil) ? $hasil($pengiriman) : $hasil;
            }
        };
        $this->app->instance(PenyediaKanalNotifikasi::class, $provider);

        return $provider;
    }
}
