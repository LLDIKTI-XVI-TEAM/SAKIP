<?php

namespace Tests\Feature\StatusCapaian;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\User;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Kontrak HTTP halaman penetapan status capaian: gerbang izin halaman, otorisasi tulis per unit,
 * allowlist request, dan penolakan versi yang belum disahkan.
 */
class StatusCapaianAccessTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    private const INDEX = '/verifikasi/status-capaian';

    private function urlStore(string $pengukuranId): string
    {
        return "/verifikasi/{$pengukuranId}/status-capaian";
    }

    /** Token konkurensi dibaca dari basis data, bukan dari instance fixture yang belum memuat kolom default. */
    private function token(): int
    {
        return (int) DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->value('versi');
    }

    /** @return array<string, mixed> */
    private function payload(string $versiId, array $tambahan = []): array
    {
        return [
            'pengukuran_versi_id' => $versiId,
            'status' => 'tercapai',
            'versi' => $this->token(),
            ...$tambahan,
        ];
    }

    private function tolak(User $user, ?string $unitId = null): void
    {
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $user->id,
            'permission_id' => Permission::where('kode', PermissionCodes::STATUS_CAPAIAN_UPDATE)->value('id'),
            'unit_id' => $unitId, 'alasan' => 'Fixture pembatasan',
            'ditetapkan_oleh' => $this->actor->id, 'created_at' => now(),
        ]);
    }

    public function test_index_perencanaan_melihat_kandidat_dan_boleh_menetapkan(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)->get(self::INDEX)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Berkas halaman React belum ada di resources/js; kontrak nama komponen tetap diuji.
                ->component('Verifikasi/StatusCapaian', false)
                ->where('can.tetapkan', true)
                ->has('kandidat', 1)
                ->where('kandidat.0.id', $this->pengukuran->id)
                ->where('kandidat.0.versi_pengukuran.id', $versi->id)
                ->where('kandidat.0.can.tetapkan', true));
    }

    public function test_index_superadmin_dibuka(): void
    {
        $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $this->actingAs($this->actor)->get(self::INDEX)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Verifikasi/StatusCapaian', false)
                ->where('can.tetapkan', true));
    }

    public function test_index_ditolak_untuk_peran_tanpa_izin_status_capaian(): void
    {
        $this->sahkan($this->pengukuran, ['nilai' => '80']);

        foreach (['admin', 'pimpinan', 'pegawai'] as $kode) {
            $this->actingAs($this->userWithRole($kode))->get(self::INDEX)
                ->assertForbidden();
        }
    }

    public function test_post_perencanaan_menulis_status_dan_audit_dasar_izin(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id))
            ->assertRedirect();

        $this->assertSame(1, DB::table('status_capaian')->count());
        $baris = DB::table('status_capaian')->first();
        $this->assertNotNull($baris);
        $this->assertSame('tercapai', $baris->status);
        $this->assertSame('manual', $baris->sumber);
        $this->assertSame($perencanaan->id, $baris->ditetapkan_oleh);

        $log = AuditLog::where('tindakan', 'status_capaian.tetapkan')->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->dasar_izin);
        $this->assertNotEmpty($log->dasar_izin);
    }

    public function test_post_superadmin_berhasil(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $this->actingAs($this->actor)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id, ['status' => 'belum_tercapai']))
            ->assertRedirect();

        $this->assertSame(1, DB::table('status_capaian')->count());
        $this->assertSame('belum_tercapai', DB::table('status_capaian')->value('status'));
    }

    public function test_post_ditolak_untuk_peran_tanpa_izin_tanpa_menulis_baris(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);

        foreach (['admin', 'pimpinan', 'pegawai'] as $kode) {
            $this->actingAs($this->userWithRole($kode))
                ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id))
                ->assertForbidden();
        }

        $this->assertSame(0, DB::table('status_capaian')->count());
    }

    public function test_deny_global_menang_atas_peran_perencanaan(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $perencanaan = $this->userWithRole('perencanaan');
        $this->tolak($perencanaan);

        $this->actingAs($perencanaan)->get(self::INDEX)->assertForbidden();
        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id))
            ->assertForbidden();

        $this->assertSame(0, DB::table('status_capaian')->count());
    }

    public function test_deny_per_unit_menolak_tulis_dan_menyembunyikan_kandidat_unit_itu(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $snapshotLain = $this->indikatorBaru('I-LAIN', snapshot: ['unit_id' => $this->unitBaru('Unit Lain')->id]);
        $headerLain = $this->headerUntuk($snapshotLain);
        $this->sahkan($headerLain, ['nilai' => '10']);

        $perencanaan = $this->userWithRole('perencanaan');
        $this->tolak($perencanaan, $this->unit->id);

        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id))
            ->assertForbidden();
        $this->assertSame(0, DB::table('status_capaian')->count());

        $this->actingAs($perencanaan)->get(self::INDEX)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Verifikasi/StatusCapaian', false)
                ->has('kandidat', 1)
                ->where('kandidat.0.id', $headerLain->id));
    }

    public function test_pengguna_nonaktif_ditolak_pada_halaman_dan_tulis(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $perencanaan = $this->userWithRole('perencanaan');
        $perencanaan->update(['status' => 'nonaktif']);

        $this->actingAs($perencanaan)->get(self::INDEX)->assertRedirect(route('auth.pending'));

        // Mutasi dari klien Inertia ditolak 403 oleh EnsureUserIsActive; tanpa header, middleware juga fail-closed.
        $this->withHeader('X-Inertia', 'true')
            ->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id))
            ->assertForbidden();

        $this->assertSame(0, DB::table('status_capaian')->count());
    }

    public function test_field_di_luar_kontrak_ditolak(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id, [
                'pengukuran_id' => $this->pengukuran->id,
                'sumber' => 'otomatis',
                'ditetapkan_oleh' => $this->actor->id,
                'can' => ['tetapkan' => true],
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('status_capaian');

        $this->assertSame(0, DB::table('status_capaian')->count());
    }

    public function test_status_di_luar_enum_ditolak(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id, ['status' => 'lunas']))
            ->assertRedirect()
            ->assertSessionHasErrors(['status']);

        $this->assertSame(0, DB::table('status_capaian')->count());
    }

    /**
     * m2: alasan adalah teks audit opsional, jadi panjangnya mengikuti kontrak `AuditReason`/`AlasanAudit` (1000),
     * bukan teks bebas. 1001 karakter harus ditolak sebelum Action menyentuh basis data.
     */
    public function test_alasan_lebih_dari_seribu_karakter_ditolak_tanpa_menulis_baris(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id, [
                'alasan' => str_repeat('a', 1001),
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('alasan');

        $this->assertSame(0, DB::table('status_capaian')->count());
    }

    public function test_alasan_tepat_seribu_karakter_diterima_dan_tercatat_utuh(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $perencanaan = $this->userWithRole('perencanaan');
        $alasan = str_repeat('b', 1000);

        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id, ['alasan' => $alasan]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('status_capaian')->count());
        $log = AuditLog::where('tindakan', 'status_capaian.tetapkan')->sole();
        $this->assertSame($alasan, $log->alasan, 'Alasan tepat pada batas tidak boleh dipotong sebelum masuk audit.');
    }

    /** m2: karakter kontrol ilegal tidak boleh lolos ke jejak audit yang append-only. */
    public function test_alasan_dengan_karakter_kontrol_ditolak(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id, [
                'alasan' => "Alasan berisi \x01 karakter kontrol",
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('alasan');

        $this->assertSame(0, DB::table('status_capaian')->count());
        $log = AuditLog::where('tindakan', 'status_capaian.tetapkan')->first();
        $this->assertNull($log, 'Penolakan input tidak boleh menghasilkan audit penetapan sukses.');
    }

    /**
     * m2: alasan tetap opsional (Q78 §78.1 butir 4). Tanpa alasan, penetapan sukses tetapi audit wajib memakai
     * alasan baku server yang deskriptif, bukan string kosong dan bukan fallback generik `AuditLogger`.
     */
    public function test_post_tanpa_alasan_sukses_dengan_alasan_baku_server(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('status_capaian')->count());
        $log = AuditLog::where('tindakan', 'status_capaian.tetapkan')->sole();
        $this->assertIsString($log->alasan);
        $this->assertNotSame('', trim((string) $log->alasan), 'Alasan audit tidak boleh kosong.');
        $this->assertStringContainsStringIgnoringCase(
            'menetapkan status capaian',
            (string) $log->alasan,
            'Alasan baku harus menjelaskan tindakan penetapan, bukan fallback generik AuditLogger.',
        );
    }

    public function test_post_untuk_header_belum_disahkan_ditolak(): void
    {
        $versi = $this->ajukan($this->pengukuran, ['nilai' => '80'], 'draft');
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id))
            ->assertRedirect()
            ->assertSessionHasErrors('status_capaian');

        $this->assertSame(0, DB::table('status_capaian')->count());
    }

    /** AC-5: periode tanpa target tidak boleh diberi status capaian walau dikirim lewat request langsung. */
    public function test_post_langsung_ditolak_untuk_periode_tanpa_target(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['tanpa_target_periode' => true]);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id))
            ->assertRedirect()
            ->assertSessionHasErrors('status_capaian');

        $this->assertSame(0, DB::table('status_capaian')->count());
    }

    /** AC-5: realisasi belum dapat diukur tidak dihitung sebagai capaian 0 dan tidak boleh diberi status capaian. */
    public function test_post_langsung_ditolak_untuk_belum_dapat_diukur(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['status_perhitungan' => 'belum_dapat_diukur']);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id))
            ->assertRedirect()
            ->assertSessionHasErrors('status_capaian');

        $this->assertSame(0, DB::table('status_capaian')->count());
    }

    /** AC-5 + keputusan PM 28 Oktober 2026 (Q64 §64.1 butir 5): `tidak_dapat_dihitung` pada periode bertarget juga ditolak. */
    public function test_post_langsung_ditolak_untuk_tidak_dapat_dihitung(): void
    {
        $versi = $this->sahkan($this->pengukuran, [
            'status_perhitungan' => 'tidak_dapat_dihitung',
            'tanpa_target_periode' => false,
        ]);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)
            ->post($this->urlStore($this->pengukuran->id), $this->payload($versi->id))
            ->assertRedirect()
            ->assertSessionHasErrors('status_capaian');

        $this->assertSame(0, DB::table('status_capaian')->count());
    }
}
