<?php

namespace Tests\Feature\Audit;

use App\Actions\Access\AssignRole;
use App\Actions\Audit\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogPresenter;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->reader = $this->user('admin');
    }

    /** @param  array<string, mixed>  $attributes */
    private function user(?string $role = null, array $attributes = []): User
    {
        $user = User::factory()->create(['status' => 'aktif', ...$attributes]);
        if ($role !== null) {
            $user->roles()->attach(Role::where('kode', $role)->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        }

        return $user;
    }

    private function deny(User $user, string $code): string
    {
        $id = (string) Str::uuid();
        DB::table('user_permission_denied')->insert(['id' => $id, 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $code)->value('id'),
            'unit_id' => null, 'alasan' => 'Pembatasan sintetis', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);

        return $id;
    }

    /**
     * Fixture ditulis lewat writer produksi pada waktu UTC tahun 2031 agar urutan tidak bergantung jam test.
     *
     * @param  array<string, mixed>  $extra
     */
    private function audit(User $actor, string $tindakan, string $waktuUtc, array $extra = []): AuditLog
    {
        $this->travelTo(Carbon::parse($waktuUtc, 'UTC'));
        $audit = app(WriteAuditLog::class)->handle([
            'actor_type' => 'user', 'actor_id' => $actor->id, 'sumber' => 'manual', 'tindakan' => $tindakan,
            'objek_tipe' => 'renstra', 'objek_id' => (string) Str::uuid(), 'alasan' => 'Catatan sintetis.', ...$extra,
        ]);
        $this->travelBack();

        return $audit;
    }

    /** @param  list<AuditLog>  $expected */
    private function assertIds(string $url, array $expected): void
    {
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Audit/Index')
            ->where('audit.data', fn ($rows) => collect($rows)->pluck('id')->all() === array_map(fn (AuditLog $audit) => $audit->id, $expected)));
    }

    public function test_daftar_terbaru_lebih_dulu_dan_filter_waktu_aktor_tindakan_objek(): void
    {
        $budi = $this->user('perencanaan', ['nama' => 'Budi Perencana', 'email' => 'budi@lldikti16.test']);
        $sari = $this->user('pegawai', ['nama' => 'Sari Pegawai', 'email' => 'sari@lldikti16.test']);
        $objek = (string) Str::uuid();
        // Batas tanggal mengikuti Asia/Makassar (UTC+8): 15:30 UTC = 23:30 WITA hari sebelumnya.
        $lama = $this->audit($budi, 'renstra.ubah', '2031-03-15 15:30:00', ['objek_id' => $objek]);
        $awal = $this->audit($budi, 'renstra.ubah_ditolak', '2031-03-15 16:30:00', ['objek_id' => $objek, 'alasan' => 'Renstra aktif tidak dapat diubah.']);
        $akhir = $this->audit($sari, 'rencana_aksi.ajukan', '2031-03-16 15:59:00', ['objek_tipe' => 'rencana_aksi']);
        $luar = $this->audit($sari, 'rencana_aksi.ajukan', '2031-03-16 16:00:00', ['objek_tipe' => 'rencana_aksi']);
        $this->actingAs($this->reader);

        $this->get('/audit')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Audit/Index')
            ->where('audit.data.0.id', $luar->id)->where('audit.data.1.id', $akhir->id)
            ->where('audit.data.2.id', $awal->id)->where('audit.data.3.id', $lama->id)
            ->where('audit.data.2.ditolak', true)->where('audit.data.2.alasan', 'Renstra aktif tidak dapat diubah.')
            ->where('audit.data.0.ditolak', false)->where('audit.data.0.aktor.nama', 'Sari Pegawai')
            ->missing('audit.data.0.nilai_lama')->missing('audit.data.0.nilai_baru')->missing('audit.data.0.dasar_izin')
            ->where('filters', ['dari' => '', 'sampai' => '', 'aktor' => '', 'tindakan' => '', 'objek_tipe' => '', 'objek_id' => '']));

        $this->assertIds('/audit?dari=2031-03-16&sampai=2031-03-16', [$akhir, $awal]);
        $this->assertIds('/audit?aktor=BUDI', [$awal, $lama]);
        $this->assertIds('/audit?aktor=sari@lldikti16', [$luar, $akhir]);
        $this->assertIds('/audit?tindakan=ditolak', [$awal]);
        $this->assertIds('/audit?tindakan=%25', []);
        $this->assertIds('/audit?objek_tipe=rencana_aksi&aktor=Sari', [$luar, $akhir]);
        $this->assertIds('/audit?objek_tipe=renstra&objek_id='.$objek, [$awal, $lama]);
    }

    public function test_filter_aktor_mencakup_provenance_operator_dan_sistem(): void
    {
        $writer = app(WriteAuditLog::class);
        $operator = $writer->handle(['actor_type' => 'operator', 'actor_id' => null, 'sumber' => 'bootstrap', 'operator_reference' => 'tiket-OPS-77',
            'runtime_identity' => 'cli@sakip-uji', 'tindakan' => 'auth.bootstrap', 'objek_tipe' => 'users', 'objek_id' => $this->reader->id, 'alasan' => 'Bootstrap sintetis.']);
        $sistem = $writer->handle(['actor_type' => 'system', 'actor_id' => null, 'sumber' => 'preset_release', 'runtime_identity' => 'rilis-preset-uji',
            'tindakan' => 'role_permissions.ubah', 'objek_tipe' => 'roles', 'objek_id' => Role::where('kode', 'admin')->value('id'), 'alasan' => 'Rilis preset sintetis.']);
        $this->actingAs($this->reader);

        $this->assertIds('/audit?aktor=ops-77', [$operator]);
        $this->assertIds('/audit?aktor=cli@sakip-uji', [$operator]);
        $this->assertIds('/audit?aktor=rilis-preset-uji', [$sistem]);
        // Pencarian provenance tidak menyeret baris pengguna yang tidak cocok.
        $this->get('/audit?aktor=rilis-preset-uji')->assertInertia(fn (Assert $page) => $page
            ->where('audit.data.0.aktor', null)->where('audit.data.0.actor_type', 'system'));
    }

    public function test_filter_tidak_sah_ditolak_validasi(): void
    {
        $this->actingAs($this->reader);
        $this->get('/audit?objek_id=bukan-uuid')->assertSessionHasErrors('objek_id');
        $this->get('/audit?dari=16-03-2031')->assertSessionHasErrors('dari');
        $this->get('/audit?dari=2031-03-16&sampai=2031-03-15')->assertSessionHasErrors('sampai');
        $this->get('/audit?tindakan='.str_repeat('a', 101))->assertSessionHasErrors('tindakan');
        $this->get('/audit?page=0')->assertSessionHasErrors('page');
        $this->get('/audit?sampai=2031-03-15')->assertOk();
    }

    public function test_detail_memuat_dasar_izin_allow_dan_deny_pemicu_beserta_percobaan_ditolak(): void
    {
        $superadmin = $this->user('superadmin', ['nama' => 'Super Penguji']);
        $target = $this->user();
        app(AssignRole::class)->handle($superadmin, $target->id, Role::where('kode', 'pegawai')->value('id'), 'Penetapan awal pegawai.', null);
        $allow = AuditLog::where('tindakan', 'user_roles.tambah')->where('objek_id', $target->id)->sole();

        $admin = $this->user('admin');
        $deny = $this->deny($admin, 'akses:update');
        try {
            app(AssignRole::class)->handle($admin, $target->id, Role::where('kode', 'perencanaan')->value('id'), 'Percobaan penggantian peran.', null);
            $this->fail('Deny akses:update harus menolak penetapan peran.');
        } catch (AuthorizationException) {
        }
        $ditolak = AuditLog::where('tindakan', 'user_roles.ditolak')->where('objek_id', $target->id)->sole();
        $this->actingAs($this->reader);

        $this->get('/audit/'.$allow->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Audit/Show')
            ->where('audit.tindakan', 'user_roles.tambah')->where('audit.ditolak', false)
            ->where('audit.aktor.nama', 'Super Penguji')->where('audit.actor_type', 'user')->where('audit.sumber', 'manual')
            ->where('audit.nilai_baru.role_kode', 'pegawai')
            ->where('audit.dasar_izin.akses_update.allowed', true)->has('audit.dasar_izin.akses_update.roles', 1)
            ->where('audit.dasar_izin.akses_update.denies', []));

        $this->get('/audit/'.$ditolak->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.ditolak', true)->where('audit.alasan', 'Anda tidak berwenang menetapkan peran.')
            ->where('audit.dasar_izin.akses_update.allowed', false)->where('audit.dasar_izin.akses_update.denies', [$deny]));

        $this->get('/audit?tindakan=ditolak&objek_id='.$target->id)->assertInertia(fn (Assert $page) => $page
            ->has('audit.data', 1)->where('audit.data.0.id', $ditolak->id)
            ->where('audit.data.0.alasan', 'Anda tidak berwenang menetapkan peran.'));
    }

    public function test_tidak_ada_endpoint_ubah_atau_hapus_audit(): void
    {
        $audit = $this->audit($this->reader, 'renstra.ubah', '2031-01-01 00:00:00');
        $this->actingAs($this->reader);
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->{$method}('/audit')->assertMethodNotAllowed();
            $this->{$method}('/audit/'.$audit->id)->assertMethodNotAllowed();
        }
        $methods = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'audit'))
            ->flatMap(fn ($route) => $route->methods())->unique()->sort()->values()->all();
        $this->assertSame(['GET', 'HEAD'], $methods);

        $this->assertThrows(fn () => $audit->update(['alasan' => 'Diubah']), LogicException::class);
        $this->assertThrows(fn () => $audit->delete(), LogicException::class);
        $this->assertSame('Catatan sintetis.', AuditLog::findOrFail($audit->id)->alasan);
    }

    public function test_kunci_rahasia_disamarkan_dan_payload_modul_tidak_memuat_rahasia(): void
    {
        $audit = $this->audit($this->reader, 'pengaturan.ubah', '2031-01-01 00:00:00', [
            'nilai_lama' => ['nama' => 'Lama', 'access_token' => 'canary-token-lama'],
            'nilai_baru' => ['nama' => 'Baru', 'Authorization' => 'Bearer canary-bearer', 'password' => 'canary-password', 'api_key' => 'canary-apikey',
                'lampiran' => [['path' => 'berkas/x/canary-path.pdf', 'nama_asli' => 'surat.pdf', 'storage_path' => 'canary-storage']]],
            'dasar_izin' => ['permission' => 'pengaturan:update', 'client_secret' => 'canary-secret', 'credentials' => ['user' => 'canary-credential']],
        ]);
        $response = $this->actingAs($this->reader)->get('/audit/'.$audit->id)->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('audit.nilai_lama', ['nama' => 'Lama', 'access_token' => AuditLogPresenter::DISAMARKAN])
            ->where('audit.nilai_baru.Authorization', AuditLogPresenter::DISAMARKAN)->where('audit.nilai_baru.password', AuditLogPresenter::DISAMARKAN)
            ->where('audit.nilai_baru.api_key', AuditLogPresenter::DISAMARKAN)
            ->where('audit.nilai_baru.lampiran.0', ['path' => AuditLogPresenter::DISAMARKAN, 'nama_asli' => 'surat.pdf', 'storage_path' => AuditLogPresenter::DISAMARKAN])
            ->where('audit.dasar_izin', ['permission' => 'pengaturan:update', 'client_secret' => AuditLogPresenter::DISAMARKAN, 'credentials' => AuditLogPresenter::DISAMARKAN]));
        foreach (['canary-token-lama', 'canary-bearer', 'canary-password', 'canary-apikey', 'canary-path', 'canary-storage', 'canary-secret', 'canary-credential'] as $canary) {
            $this->assertStringNotContainsString($canary, $response->getContent());
        }
        // Variasi penamaan kunci kredensial ikut disamarkan, termasuk kunci setelan bertitik.
        $this->assertSame(array_fill_keys(['apikey', 'API-Key', 'private_key', 'accessKey', 'integrasi.api_key'], AuditLogPresenter::DISAMARKAN),
            AuditLogPresenter::samarkan(array_fill_keys(['apikey', 'API-Key', 'private_key', 'accessKey', 'integrasi.api_key'], 'canary')));
        // Kunci yang hanya mirip tidak ikut disamarkan.
        $this->assertSame(['jalur_pengajuan' => 'pic', 'pathway' => 'x', 'primary_key' => 'id', 'kunci' => 'nama'],
            AuditLogPresenter::samarkan(['jalur_pengajuan' => 'pic', 'pathway' => 'x', 'primary_key' => 'id', 'kunci' => 'nama']));

        $superadmin = $this->user('superadmin');
        app(AssignRole::class)->handle($superadmin, $this->user()->id, Role::where('kode', 'pegawai')->value('id'), 'Penetapan awal.', null);
        // Writer menyamarkan sejak Q58, sehingga kunci rahasia dari producer terlihat dari penandanya yang tersimpan.
        $this->assertStringContainsString(AuditLogPresenter::DISAMARKAN, (string) json_encode(AuditLog::findOrFail($audit->id)->nilai_baru));
        $rows = AuditLog::whereKeyNot($audit->id)->get();
        $this->assertGreaterThan(1, $rows->count());
        foreach ($rows as $row) {
            foreach (['nilai_lama', 'nilai_baru', 'dasar_izin'] as $kolom) {
                $this->assertStringNotContainsString(AuditLogPresenter::DISAMARKAN, (string) json_encode($row->{$kolom}), "{$row->tindakan}.{$kolom} memuat kunci rahasia.");
            }
        }
    }

    public function test_penulisan_audit_menyamarkan_kunci_rahasia_pada_nilai_tersimpan(): void
    {
        $d = AuditLogPresenter::DISAMARKAN;
        $audit = $this->audit($this->reader, 'pengaturan.ubah', '2031-01-01 00:00:00', [
            'nilai_lama' => ['nama' => 'Lama', 'access_token' => 'canary-lama', 'kosong' => null],
            'nilai_baru' => ['nama' => 'Baru', 'Authorization' => 'Bearer canary-bearer', 'password' => 'canary-password',
                'API-Key' => 'canary-apikey', 'integrasi.api_key' => 'canary-bertitik',
                'lampiran' => [['path' => 'berkas/canary-path.pdf', 'nama_asli' => 'surat.pdf', 'storage_path' => 'canary-storage']],
                'koleksi' => collect(['client_secret' => 'canary-koleksi', 'nama' => 'x'])],
            'dasar_izin' => ['permission' => 'pengaturan:update', 'keputusan' => 'diizinkan', 'credentials' => ['user' => 'canary-credential']],
        ]);

        // Dibaca dari kolom jsonb mentah agar yang diuji nilai tersimpan, bukan hasil tampilan.
        $raw = DB::table('audit_log')->where('id', $audit->id)->sole(['nilai_lama', 'nilai_baru', 'dasar_izin']);
        foreach (['canary-lama', 'canary-bearer', 'canary-password', 'canary-apikey', 'canary-bertitik', 'canary-path', 'canary-storage', 'canary-koleksi', 'canary-credential'] as $canary) {
            $this->assertStringNotContainsString($canary, $raw->nilai_lama.$raw->nilai_baru.$raw->dasar_izin);
        }
        $this->assertEquals(['nama' => 'Lama', 'access_token' => $d, 'kosong' => null], json_decode($raw->nilai_lama, true));
        $this->assertEquals(['nama' => 'Baru', 'Authorization' => $d, 'password' => $d, 'API-Key' => $d, 'integrasi.api_key' => $d,
            'lampiran' => [['path' => $d, 'nama_asli' => 'surat.pdf', 'storage_path' => $d]],
            'koleksi' => ['client_secret' => $d, 'nama' => 'x']], json_decode($raw->nilai_baru, true));
        $this->assertEquals(['permission' => 'pengaturan:update', 'keputusan' => 'diizinkan', 'credentials' => $d], json_decode($raw->dasar_izin, true));
    }

    public function test_penulisan_audit_menyimpan_utuh_kunci_yang_hanya_mirip(): void
    {
        // Termasuk kunci yang dibaca ulang kode aplikasi dari payload audit.
        $baru = ['primary_key' => 'id', 'jalur_pengajuan' => 'pic', 'pathway' => 'x', 'kunci' => 'nama',
            'penanda_audit_id' => 'a-1', 'versi_pengajuan' => ['id' => 'v-1'], 'self_approval' => true, 'reviu_terlambat' => false,
            'role_id' => 'r-1', 'sumber_pemberian' => 'sso_onboarding', 'diberikan_oleh' => null, 'is_active' => false];
        $izin = ['permission' => 'akses:update', 'keputusan' => 'ditolak', 'roles' => [], 'grants' => [], 'denies' => [], 'sumber_allow' => null, 'alasan' => 'inactive_user'];
        $audit = $this->audit($this->reader, 'renstra.ubah', '2031-01-01 00:00:00', ['nilai_baru' => $baru, 'dasar_izin' => $izin]);

        $raw = DB::table('audit_log')->where('id', $audit->id)->sole(['nilai_lama', 'nilai_baru', 'dasar_izin']);
        $this->assertNull($raw->nilai_lama);
        $this->assertEquals($baru, json_decode($raw->nilai_baru, true));
        $this->assertEquals($izin, json_decode($raw->dasar_izin, true));
    }

    public function test_detail_audit_menyamarkan_baris_lama_yang_tersimpan_sebelum_q58(): void
    {
        $id = (string) Str::uuid();
        DB::table('audit_log')->insert([
            'id' => $id, 'actor_id' => $this->reader->id, 'actor_type' => 'user', 'sumber' => 'manual', 'waktu' => now(),
            'tindakan' => 'pengaturan.ubah', 'objek_tipe' => 'pengaturan', 'objek_id' => (string) Str::uuid(),
            'nilai_baru' => json_encode(['nama' => 'Lama', 'access_token' => 'canary-baris-lama']),
            'dasar_izin' => json_encode(['permission' => 'pengaturan:update', 'client_secret' => 'canary-baris-lama-secret']),
            'alasan' => 'Baris sebelum Q58.',
        ]);

        $response = $this->actingAs($this->reader)->get('/audit/'.$id)->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('audit.nilai_baru', ['nama' => 'Lama', 'access_token' => AuditLogPresenter::DISAMARKAN])
            ->where('audit.dasar_izin', ['permission' => 'pengaturan:update', 'client_secret' => AuditLogPresenter::DISAMARKAN]));
        $this->assertStringNotContainsString('canary-baris-lama', $response->getContent());
    }

    public function test_hanya_pemegang_audit_read_tanpa_deny_yang_dapat_membuka_audit(): void
    {
        $audit = $this->audit($this->reader, 'renstra.ubah', '2031-01-01 00:00:00');
        foreach (['superadmin', 'admin', 'perencanaan', 'pimpinan'] as $role) {
            $this->actingAs($this->user($role))->get('/audit')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.can.audit', true));
            $this->get('/audit/'.$audit->id)->assertOk();
        }

        $this->actingAs($this->user('pegawai'));
        $this->get('/audit')->assertForbidden();
        $this->get('/audit/'.$audit->id)->assertForbidden();
        // Otorisasi diperiksa sebelum pencarian baris agar keberadaan ID tidak terbaca.
        $this->get('/audit/'.Str::uuid())->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.can.audit', false));

        $this->deny($this->reader, 'audit:read');
        $this->actingAs($this->reader);
        $this->get('/audit')->assertForbidden();
        $this->get('/audit/'.$audit->id)->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.can.audit', false));
    }

    public function test_detail_tidak_dikenal_atau_id_tidak_sah_menghasilkan_404(): void
    {
        $this->actingAs($this->reader);
        $this->get('/audit/'.Str::uuid())->assertNotFound();
        $this->get('/audit/bukan-uuid')->assertNotFound();
    }

    public function test_pagination_server_side_dan_index_pencarian_tersedia(): void
    {
        $aktor = $this->user('perencanaan', ['nama' => 'Penguji Paginasi']);
        $this->travelTo(Carbon::parse('2031-02-01 00:00:00', 'UTC'));
        for ($i = 0; $i < 26; $i++) {
            app(WriteAuditLog::class)->handle(['actor_type' => 'user', 'actor_id' => $aktor->id, 'sumber' => 'manual', 'tindakan' => 'renstra.ubah',
                'objek_tipe' => 'renstra', 'objek_id' => (string) Str::uuid(), 'alasan' => 'Catatan sintetis.']);
        }
        $this->travelBack();
        $this->actingAs($this->reader);

        $this->get('/audit?aktor=Penguji+Paginasi')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.total', 26)->where('audit.per_page', 25)->has('audit.data', 25)
            ->where('audit.next_page_url', fn (?string $url) => $url !== null && str_contains($url, 'aktor=Penguji')));
        $this->get('/audit?aktor=Penguji+Paginasi&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page->has('audit.data', 1));

        $indexes = DB::table('pg_indexes')->where('tablename', 'audit_log')->pluck('indexdef')->implode("\n");
        $this->assertMatchesRegularExpression('/USING btree \(waktu\)/', $indexes);
        $this->assertMatchesRegularExpression('/USING btree \(actor_id, waktu\)/', $indexes);
    }
}
