<?php

namespace Tests\Feature\Notifikasi;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\PengukuranKinerja;
use App\Models\User;
use App\Notifications\ItemNotifikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

/**
 * Daftar, counter, dan buka item hanya atas item milik aktor; akun tanpa peran aktif ditolak;
 * membuka item menandainya dibaca sekali lalu mengalihkan ke route tujuan yang tetap memeriksa izin.
 */
class NotifikasiHttpTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
    }

    private function item(User $user, string $judul = 'Judul', string $routeName = 'pengukuran.index', array $params = [], string $jenis = 'pengukuran.dikembalikan'): string
    {
        $user->notify(new ItemNotifikasi($jenis, $judul, 'Ringkasan '.$judul, $routeName, $params));

        return DB::table('notifications')->where('notifiable_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')->value('id');
    }

    private function readAt(string $id): ?string
    {
        return DB::table('notifications')->where('id', $id)->value('read_at');
    }

    public function test_daftar_hanya_milik_aktor_terbaru_dulu_tanpa_route_dan_tidak_menandai_dibaca(): void
    {
        $lain = $this->createUserWithRole('pegawai');
        $this->item($lain, 'Milik orang lain');
        $lama = $this->item($this->actor, 'Lama');
        $this->travel(1)->minute();
        $baru = $this->item($this->actor, 'Baru');

        $this->actingAs($this->actor)->get('/notifikasi')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Notifikasi/Index')->has('notifikasi.data', 2)
            ->where('notifikasi.data.0.id', $baru)->where('notifikasi.data.0.judul', 'Baru')->where('notifikasi.data.0.dibaca', false)
            ->where('notifikasi.data.0.jenis', 'pengukuran.dikembalikan')->where('notifikasi.data.0.ringkasan', 'Ringkasan Baru')
            ->missing('notifikasi.data.0.route_name')->missing('notifikasi.data.0.route_params')
            ->where('notifikasi.data.1.id', $lama)->where('notifikasi.total', 2)
            ->where('notifikasi_belum_dibaca', 2));

        $this->assertNull($this->readAt($baru));
    }

    public function test_21_item_bertimestamp_sama_terbagi_20_dan_1_tanpa_irisan(): void
    {
        // Waktu dibekukan agar seluruh item benar-benar bertimestamp sama dan pemutus seri `id` teruji.
        $this->freezeTime();
        for ($i = 0; $i < 21; $i++) {
            $this->item($this->actor, "Item {$i}");
        }
        // Urutan yang diharapkan halaman: pemutus seri `id` menurun saat timestamp sama.
        $ids = DB::table('notifications')->orderByDesc('id')->pluck('id')->all();
        $halaman = fn (int $page): array => $this->actingAs($this->actor)->get("/notifikasi?page={$page}")->assertOk()
            ->viewData('page')['props']['notifikasi']['data'];
        $satu = array_column($halaman(1), 'id');
        $dua = array_column($halaman(2), 'id');

        $this->assertCount(20, $satu);
        $this->assertCount(1, $dua);
        $this->assertSame([], array_intersect($satu, $dua));
        $this->assertSame($ids, [...$satu, ...$dua]);
    }

    public function test_buka_menandai_dibaca_sekali_dan_mengalihkan_ke_route_tersimpan(): void
    {
        $id = $this->item($this->actor, 'Pengembalian', 'pengukuran.index');
        $this->actingAs($this->actor)->get('/notifikasi')->assertInertia(fn (Assert $page) => $page->where('notifikasi_belum_dibaca', 1));

        $this->actingAs($this->actor)->post("/notifikasi/{$id}/buka")->assertRedirect(route('pengukuran.index'));

        $pertama = $this->readAt($id);
        $this->assertNotNull($pertama);
        $this->get('/notifikasi')->assertInertia(fn (Assert $page) => $page->where('notifikasi_belum_dibaca', 0)->where('notifikasi.data.0.dibaca', true));

        $this->travel(5)->minutes();
        $this->post("/notifikasi/{$id}/buka")->assertRedirect(route('pengukuran.index'));
        $this->assertSame($pertama, $this->readAt($id));
    }

    public function test_item_pengguna_lain_dan_id_bukan_uuid_404_tanpa_mengubah_read_at(): void
    {
        $lain = $this->createUserWithRole('pegawai');
        $id = $this->item($lain, 'Milik orang lain');

        $this->actingAs($this->actor)->post("/notifikasi/{$id}/buka")->assertNotFound();
        $this->post('/notifikasi/bukan-uuid/buka')->assertNotFound();

        $this->assertNull($this->readAt($id));
        $this->assertSame(1, DB::table('notifications')->whereNull('read_at')->count());
    }

    public function test_counter_hanya_milik_aktor_dan_null_untuk_tamu_akun_tanpa_peran_dan_nonaktif(): void
    {
        $this->item($this->createUserWithRole('pegawai'), 'Milik orang lain');
        $this->actingAs($this->actor)->get('/notifikasi')->assertInertia(fn (Assert $page) => $page->where('notifikasi_belum_dibaca', 0));

        $tamu = app(HandleInertiaRequests::class)->share(Request::create('/'))['notifikasi_belum_dibaca'];
        $this->assertNull($tamu());

        $tanpaPeran = User::factory()->create(['status' => 'aktif']);
        $id = $this->item($tanpaPeran, 'Item lama');
        $this->actingAs($tanpaPeran)->get('/auth/pending')->assertOk()->assertInertia(fn (Assert $page) => $page->where('notifikasi_belum_dibaca', null));
        $this->get('/notifikasi')->assertForbidden();
        $this->post("/notifikasi/{$id}/buka")->assertForbidden();
        $this->assertNull($this->readAt($id));

        $nonaktif = $this->createUserWithRole('pegawai');
        $nonaktif->update(['status' => 'nonaktif']);
        $this->actingAs($nonaktif)->get('/auth/pending')->assertOk()->assertInertia(fn (Assert $page) => $page->where('notifikasi_belum_dibaca', null));
    }

    public function test_tautan_rencana_aksi_ditolak_403_bila_grant_unit_pj_dicabut(): void
    {
        $pic = $this->createPicPegawai();
        $id = $this->item($pic, 'RA dikembalikan', 'rencana-aksi.show', ['rencanaAksi' => $this->rencanaAksi->id], 'rencana_aksi.dikembalikan');
        DB::table('user_permission_granted')->where('user_id', $pic->id)->delete();

        $tujuan = $this->actingAs($pic)->post("/notifikasi/{$id}/buka")->assertRedirect()->headers->get('Location');

        $this->assertSame(route('rencana-aksi.show', $this->rencanaAksi->id), $tujuan);
        $this->get($tujuan)->assertForbidden();
        $this->assertNotNull($this->readAt($id));
    }

    public function test_tautan_pengukuran_ditolak_403_bila_pj_dideny_baca_unit(): void
    {
        $pic = $this->createPicPegawai();
        $p = PengukuranKinerja::create(['indikator_id' => $this->indikator->id, 'tahun' => 2026, 'periode_id' => $this->snapshot->periode_mulai_id,
            'jadwal_snapshot_id' => $this->snapshot->id, 'sumber_nilai' => 'manual', 'status_alur' => 'dikembalikan', 'created_by' => $this->actor->id]);
        $id = $this->item($pic, 'Pengukuran dikembalikan', 'pengukuran.edit', ['id' => $p->id]);
        $this->denyPermission($pic, 'pengukuran:read', $this->unit->id);

        $tujuan = $this->actingAs($pic)->post("/notifikasi/{$id}/buka")->assertRedirect()->headers->get('Location');

        $this->assertSame(route('pengukuran.edit', $p->id), $tujuan);
        $this->get($tujuan)->assertForbidden();
    }

    public function test_item_akun_baru_tetap_tampil_dan_halaman_peran_menolak_admin_yang_izinnya_dicabut(): void
    {
        $admin = $this->createUserWithRole('admin');
        $baru = User::factory()->create(['status' => 'aktif']);
        $id = $this->item($admin, 'Akun baru menunggu peran', 'role-assignment.index', ['user' => $baru->id], 'pengguna.menunggu_peran');
        $this->denyPermission($admin, 'akses:update');

        $this->actingAs($admin)->get('/notifikasi')->assertOk()->assertInertia(fn (Assert $page) => $page->where('notifikasi.data.0.id', $id));
        $tujuan = $this->post("/notifikasi/{$id}/buka")->assertRedirect()->headers->get('Location');

        $this->assertSame(route('role-assignment.index', ['user' => $baru->id]), $tujuan);
        $this->get($tujuan)->assertForbidden();
    }

    public function test_seluruh_route_tujuan_terdaftar(): void
    {
        foreach (['rencana-aksi.show', 'rencana-aksi.review', 'pengukuran.edit', 'verifikasi.show', 'pengukuran.index', 'role-assignment.index', 'notifikasi.index', 'notifikasi.buka'] as $nama) {
            $this->assertTrue(Route::has($nama), $nama);
        }
    }
}
