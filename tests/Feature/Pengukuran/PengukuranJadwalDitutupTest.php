<?php

namespace Tests\Feature\Pengukuran;

use App\Policies\PengukuranKinerjaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/** Penutupan resmi jadwal menghentikan seluruh perintah pengukuran; detail tahun itu tetap terbaca. */
class PengukuranJadwalDitutupTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private const PESAN = 'Jadwal tahunan harus aktif.';

    private const PERINTAH = ['draft', 'ajukan', 'verifikasi', 'sahkan', 'kembalikan'];

    public function test_jadwal_ditutup_sebelum_tanggal_menolak_seluruh_perintah_tanpa_sesi_koreksi(): void
    {
        $policy = app(PengukuranKinerjaPolicy::class);
        foreach (self::PERINTAH as $perintah) {
            $this->assertNotContains(self::PESAN, $policy->businessErrors($this->actor, $this->pengukuran->fresh(), $perintah));
        }
        $this->jadwal->update(['status' => 'ditutup', 'closed_at' => now()]);
        foreach (self::PERINTAH as $perintah) {
            $this->assertContains(self::PESAN, $policy->businessErrors($this->actor, $this->pengukuran->fresh(), $perintah));
        }
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'draft', 'nilai' => 75])->assertSessionHasErrors('versi');
        $this->assertNull($this->pengukuran->fresh()->nilai);
    }

    public function test_detail_tahun_ditutup_tetap_terbaca_tanpa_kemampuan_mutasi(): void
    {
        $url = "/pengukuran/{$this->pengukuran->id}/edit";
        // Kontrol positif: sebelum penutupan, Superadmin jalur Perencanaan dapat mengubah.
        $this->actingAs($this->actor)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('pengukuran.can.update', true));
        $this->jadwal->update(['status' => 'ditutup', 'closed_at' => now()]);
        $this->actingAs($this->actor)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('pengukuran.can.view', true)->where('pengukuran.can.update', false)->where('pengukuran.can.submit', false)
            ->where('pengukuran.can.verify', false)->where('pengukuran.can.ratify', false)->where('pengukuran.can.return', false));
    }
}
