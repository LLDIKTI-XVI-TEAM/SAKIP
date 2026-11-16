<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\PenugasanIndikator;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

/** Q54: detail Rencana Aksi hanya untuk peran pembaca seluruh unit atau pemegang grant unit aktif. */
class RencanaAksiUnitBerizinTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        $this->createRencanaAksiSubmission();
    }

    public function test_pegawai_tanpa_grant_unit_tidak_menerima_detail_rencana_aksi(): void
    {
        $this->assertTidakTerbaca($this->createUserWithRole('pegawai'));
    }

    public function test_grant_unit_lain_dan_penugasan_pj_tanpa_grant_tidak_membuka_unit(): void
    {
        $unitLain = Unit::create(['nama' => 'Unit Lain', 'created_by' => $this->actor->id]);
        $pegawaiUnitLain = $this->createUserWithRole('pegawai');
        $this->grantUnitPermission($pegawaiUnitLain, 'rencana_aksi:update', $unitLain->id);
        $this->assertTidakTerbaca($pegawaiUnitLain);

        $pjTanpaGrant = $this->createUserWithRole('pegawai');
        PenugasanIndikator::create(['indikator_id' => $this->indikator->id, 'user_id' => $pjTanpaGrant->id, 'tanggal_mulai_berlaku' => '2026-03-10',
            'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->assertTidakTerbaca($pjTanpaGrant);
    }

    public function test_grant_unit_aktif_modul_mana_pun_membuka_unit_itu(): void
    {
        $pegawai = $this->createUserWithRole('pegawai');
        $this->grantUnitPermission($pegawai, 'pengukuran:update');
        $this->assertTerbaca($pegawai);
    }

    public function test_grant_pada_unit_nonaktif_tidak_dihitung(): void
    {
        $pegawai = $this->createUserWithRole('pegawai');
        $this->grantUnitPermission($pegawai, 'rencana_aksi:update');
        $this->unit->update(['status' => 'nonaktif']);
        $this->actingAs($pegawai)->get("/rencana-aksi/{$this->rencanaAksi->id}")->assertForbidden();
    }

    public function test_perencanaan_dan_pimpinan_membaca_seluruh_unit_tanpa_grant(): void
    {
        foreach (['perencanaan', 'pimpinan'] as $peran) {
            $this->assertTerbaca($this->createUserWithRole($peran));
        }
    }

    public function test_admin_dengan_grant_tulis_tanpa_izin_baca_tetap_ditolak(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->grantUnitPermission($admin, 'rencana_aksi:update');
        $this->actingAs($admin)->get('/rencana-aksi')->assertForbidden();
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}")->assertForbidden();
    }

    public function test_deny_unit_dan_deny_global_tetap_menang_atas_grant(): void
    {
        $pegawai = $this->createUserWithRole('pegawai');
        $this->grantUnitPermission($pegawai, 'rencana_aksi:update');
        $this->denyPermission($pegawai, 'rencana_aksi:read', $this->unit->id);
        $this->assertTidakTerbaca($pegawai);

        $this->denyPermission($pegawai, 'rencana_aksi:read');
        $this->actingAs($pegawai)->get('/rencana-aksi')->assertForbidden();
    }

    private function assertTerbaca(User $user): void
    {
        $this->actingAs($user)->get('/rencana-aksi')->assertOk()->assertInertia(fn (Assert $p) => $p->has('daftar', 1)->where('daftar.0.rencana_aksi.id', $this->rencanaAksi->id));
        // Q54 juga berlaku pada tampilan berfilter status (antrean review menyatu dengan daftar, Q59).
        $this->get('/rencana-aksi?status=diajukan')->assertOk()->assertInertia(fn (Assert $p) => $p->has('daftar', 1)
            ->where('daftar.0.rencana_aksi.id', $this->rencanaAksi->id));
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}")->assertOk();
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertOk();
    }

    private function assertTidakTerbaca(User $user): void
    {
        // Halaman daftar tetap terbuka (izin baca global), tetapi baris unit yang tidak berizin tidak dikirim.
        $this->actingAs($user)->get('/rencana-aksi')->assertOk()->assertInertia(fn (Assert $p) => $p->has('daftar', 0));
        $this->get('/rencana-aksi?status=diajukan')->assertOk()->assertInertia(fn (Assert $p) => $p->has('daftar', 0));
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}")->assertForbidden();
        $this->get("/rencana-aksi/{$this->rencanaAksi->id}/review")->assertForbidden();
    }
}
