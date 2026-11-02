<?php

namespace Tests\Feature\Pengukuran;

use App\Models\PenugasanIndikator;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class SesiKoreksiPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_lingkup_sesi_tetap_diperiksa_sebelum_penutupan_asli_dan_tutup_ulang_efektif(): void
    {
        $this->bukaSesi(['rencana_aksi']);
        $payload = ['versi' => 1, 'action' => 'draft', 'nilai' => 75];
        $this->actingAs($this->actor)->post("/pengukuran/{$this->pengukuran->id}", $payload)->assertSessionHasErrors('versi');
        $this->assertNull($this->pengukuran->fresh()->nilai);
        $this->bukaSesi(['pengukuran']);
        $this->actingAs($this->actor)->post("/pengukuran/{$this->pengukuran->id}", $payload)->assertSessionHasNoErrors();
        $this->assertEquals(75, $this->pengukuran->fresh()->nilai);
        $this->actingAs($this->actor)->post("/jadwal/{$this->jadwal->id}/tutup", [
            'expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => 'Koreksi selesai',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->actor)->post("/pengukuran/{$this->pengukuran->id}", ['versi' => 2, 'action' => 'draft', 'nilai' => 80])->assertSessionHasErrors('versi');
        $this->assertEquals(75, $this->pengukuran->fresh()->nilai);
    }

    public function test_sesi_tidak_membuka_pic_meski_jendela_lama_masih_berjalan(): void
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        PenugasanIndikator::where('indikator_id', $this->context->indikator_id)->update(['user_id' => $pic->id]);
        $this->bukaSesi(['pengukuran']);
        $this->actingAs($pic)->post("/pengukuran/{$this->pengukuran->id}", ['versi' => 1, 'action' => 'draft', 'nilai' => 75])->assertSessionHasErrors('versi');
        $this->assertNull($this->pengukuran->fresh()->nilai);
        $this->travelTo(now()->addHours(2));
        $this->actingAs($this->actor)->post("/pengukuran/{$this->pengukuran->id}", ['versi' => 1, 'action' => 'draft', 'nilai' => 80])->assertSessionHasErrors('versi');
        $this->assertNull($this->pengukuran->fresh()->nilai);
    }

    private function bukaSesi(array $jenis): void
    {
        $this->actingAs($this->actor)->post("/jadwal/{$this->jadwal->id}/tutup", [
            'expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => 'Penutupan untuk koreksi',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->actor)->post("/jadwal/{$this->jadwal->id}/buka-kembali", [
            'expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => 'Koreksi pengukuran resmi',
            'koreksi_mulai' => now()->subHour()->setTimezone('Asia/Makassar')->format('Y-m-d\TH:i'),
            'koreksi_sampai' => now()->addHour()->setTimezone('Asia/Makassar')->format('Y-m-d\TH:i'),
            'lingkup_koreksi' => ['jenis_objek' => $jenis, 'indikator_ids' => [$this->context->indikator_id], 'periode_ids' => [$this->pengukuran->periode_id]],
        ])->assertSessionHasNoErrors();
    }

    public function test_sesi_pascapenutupan_tetap_dapat_dipilih_di_daftar_pengukuran(): void
    {
        $this->travelTo(now()->setDate(2027, 1, 5));
        $url = '/pengukuran?'.http_build_query(['jadwal_id' => $this->jadwal->id, 'periode_id' => $this->pengukuran->periode_id]);
        $this->actingAs($this->actor)->get($url)->assertSessionHasErrors('jadwal_id');
        $this->bukaSesi(['pengukuran']);
        $this->actingAs($this->actor)->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.jadwal_id', $this->jadwal->id)->has('pilihan_jadwal', 1)
            ->where('pengukurans.0.pengukuran.id', $this->pengukuran->id));
        $this->travelTo(now()->addHours(2));
        $this->actingAs($this->actor)->get($url)->assertOk()->assertInertia(fn ($page) => $page->has('pilihan_jadwal', 1));
        $this->actingAs($this->actor)->post("/pengukuran/{$this->pengukuran->id}", ['versi' => 1, 'action' => 'draft', 'nilai' => 80])->assertSessionHasErrors('versi');
        $this->assertNull($this->pengukuran->fresh()->nilai);
    }

    public function test_deny_input_tidak_mencabut_izin_review_berbeda_dalam_sesi_koreksi(): void
    {
        $this->actingAs($this->actor)->post("/pengukuran/{$this->pengukuran->id}", ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75])->assertSessionHasNoErrors();
        $this->bukaSesi(['pengukuran']);
        $reviewer = $this->userWithRole('perencanaan');
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $reviewer->id,
            'permission_id' => Permission::where('kode', 'pengukuran:update')->value('id'), 'unit_id' => $this->unit->id,
            'alasan' => 'Pisahkan izin input dari review', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->actingAs($reviewer)->post("/pengukuran/{$this->pengukuran->id}", ['versi' => 2, 'action' => 'draft', 'nilai' => 80])->assertForbidden();
        $this->actingAs($reviewer)->post("/verifikasi/{$this->pengukuran->id}/verifikasi", ['versi' => 2])->assertSessionHasNoErrors();
        $this->assertSame('diverifikasi', $this->pengukuran->fresh()->status_alur);
        $this->actingAs($reviewer)->post("/verifikasi/{$this->pengukuran->id}/kembalikan", ['versi' => 3, 'catatan' => 'Lengkapi keterangan koreksi'])->assertSessionHasNoErrors();
        $this->assertSame('dikembalikan', $this->pengukuran->fresh()->status_alur);
    }
}
