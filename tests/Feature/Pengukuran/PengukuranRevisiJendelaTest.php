<?php

namespace Tests\Feature\Pengukuran;

use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/** Guard PIC pengukuran membaca jendela hasil revisi resmi (Q18) tanpa melonggarkan syarat lain. */
class PengukuranRevisiJendelaTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private function pic(): User
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $pic->id, 'tanggal_mulai_berlaku' => '2026-02-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        return $pic;
    }

    /** Revisi lewat endpoint resmi oleh Perencanaan; TW I tetap satu-satunya periode jadwal. */
    private function revisiJendela(string $pengisianSelesai): void
    {
        $this->actingAs($this->userWithRole('perencanaan'))->post("/jadwal/{$this->jadwal->id}/revisi-jendela", [
            'expected_revisi' => $this->jadwal->fresh()->revisi,
            'alasan' => 'Perpanjangan resmi TW I.',
            'periode' => [['periode_id' => $this->pengukuran->periode_id, 'pengisian_mulai' => '2026-03-01',
                'pengisian_selesai' => $pengisianSelesai]],
        ])->assertSessionHasNoErrors()->assertRedirect("/jadwal/{$this->jadwal->id}");
        $this->assertSame($pengisianSelesai, $this->jadwal->periode()->sole()->pengisian_selesai->toDateString());
    }

    private function simpanDraf(User $user, int $nilai = 70): TestResponse
    {
        return $this->actingAs($user)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => $this->pengukuran->fresh()->versi, 'action' => 'draft', 'nilai' => $nilai]);
    }

    public function test_pic_ditolak_di_luar_jendela_lalu_bekerja_lagi_sampai_batas_baru(): void
    {
        $pic = $this->pic();
        $this->travelTo(now()->setDate(2026, 3, 20));

        $this->simpanDraf($pic)->assertSessionHasErrors('versi');
        $this->assertNull($this->pengukuran->fresh()->nilai);

        $this->revisiJendela('2026-03-25');
        $this->simpanDraf($pic)->assertSessionHasNoErrors();
        $this->assertEquals(70, $this->pengukuran->fresh()->nilai);

        $this->travelTo(now()->setDate(2026, 3, 26));
        $this->simpanDraf($pic, 71)->assertSessionHasErrors('versi');
        $this->assertEquals(70, $this->pengukuran->fresh()->nilai);
    }

    public function test_perpanjangan_tidak_melonggarkan_grant_pj_deny_status_dan_gerbang(): void
    {
        $pic = $this->pic();
        $this->travelTo(now()->setDate(2026, 3, 20));
        $this->revisiJendela('2026-03-25');

        $this->simpanDraf($this->userWithRole('pegawai'))->assertForbidden();
        $bukanPj = $this->userWithRole('pegawai');
        $this->grant($bukanPj, 'pengukuran:update');
        $this->simpanDraf($bukanPj)->assertSessionHasErrors('versi');

        DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->update(['status_alur' => 'diajukan']);
        $this->simpanDraf($pic)->assertSessionHasErrors('versi');
        DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->update(['status_alur' => 'draft']);

        DB::table('rencana_aksi')->where('id', $this->plan->id)->update(['status_alur' => 'draft']);
        $this->actingAs($pic)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => $this->pengukuran->fresh()->versi, 'action' => 'ajukan', 'nilai' => 70])
            ->assertSessionHasErrors();
        $this->assertSame('draft', $this->pengukuran->fresh()->status_alur);
        $this->assertNull($this->pengukuran->fresh()->nilai);

        // Kontrol positif: PIC sah dalam jendela baru tetap dapat menyimpan.
        $this->simpanDraf($pic)->assertSessionHasNoErrors();
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $pic->id, 'permission_id' => Permission::where('kode', 'pengukuran:update')->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->simpanDraf($pic, 72)->assertForbidden();
        $this->assertEquals(70, $this->pengukuran->fresh()->nilai);
    }

    public function test_sesi_koreksi_tanpa_revisi_jendela_tidak_membuka_akses_pic(): void
    {
        $pic = $this->pic();
        $this->travelTo(now()->setDate(2027, 1, 5));
        $this->jadwal->update(['koreksi_mulai' => now()->subDay(), 'koreksi_sampai' => now()->addDay(), 'lingkup_koreksi' => [
            'indikator_ids' => [$this->pengukuran->indikator_id], 'periode_ids' => [$this->pengukuran->periode_id], 'jenis_objek' => ['pengukuran'],
        ]]);

        $this->simpanDraf($pic)->assertSessionHasErrors('versi');
        $this->assertNull($this->pengukuran->fresh()->nilai);
        // Kontrol positif: sesi koreksi yang sama tetap terbuka bagi Perencanaan.
        $this->simpanDraf($this->userWithRole('perencanaan'))->assertSessionHasNoErrors();
    }

    public function test_revisi_tidak_mengubah_penanda_reviu_terlambat_yang_sudah_tercatat(): void
    {
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85])->assertSessionHasNoErrors();
        // Penanda hanya ada pada jejak sebelum Q70; aksi reviu baru tidak lagi membuatnya.
        $audit = app(AuditLogger::class)->catat(actor: $this->actor, tindakan: 'pengukuran.verifikasi', objekTipe: 'pengukuran', objekId: $this->pengukuran->id,
            nilaiBaru: ['versi_pengajuan' => ['id' => $this->pengukuran->fresh()->latestVersion->id], 'self_approval' => false,
                'reviu_terlambat' => true, 'reviu_selesai' => '2026-04-15', 'tanggal_reviu' => '2026-04-16'],
            dasarIzin: ['permission' => 'pengukuran:verifikasi', 'fixture' => 'jejak sebelum Q70']);
        $tersimpan = $audit->fresh()->nilai_baru;
        $this->travelTo(Carbon::parse('2026-04-16 09:00:00', 'Asia/Makassar'));

        $this->revisiJendela('2026-04-20');

        $this->assertSame($tersimpan, $audit->fresh()->nilai_baru);
        $this->actingAs($this->actor)->get('/verifikasi/'.$this->pengukuran->id)->assertInertia(fn ($page) => $page->where('pengukuran.reviu_terlambat', true));
    }
}
