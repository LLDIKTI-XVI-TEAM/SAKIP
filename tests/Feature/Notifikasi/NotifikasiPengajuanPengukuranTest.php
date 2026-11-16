<?php

namespace Tests\Feature\Notifikasi;

use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/** Pengajuan pengukuran menulis item antrean untuk Perencanaan yang lolos baca unit dan izin verifikasi; deny menang. */
class NotifikasiPengajuanPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private function deny(User $user, string $kode): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $kode)->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Pengujian deny', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    /** PIC operasional: Pegawai dengan grant unit `pengukuran:update` dan penugasan PJ yang efektif hari ini. */
    private function picPegawai(): User
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => today(config('app.business_timezone'))->toDateString(), 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        return $pic;
    }

    public function test_pengukuran_diajukan_pic_menulis_item_bertautan_halaman_verifikasi(): void
    {
        $pic = $this->picPegawai();
        $p1 = $this->userWithRole('perencanaan');
        $this->deny($this->userWithRole('perencanaan'), 'pengukuran:read');
        $this->deny($this->userWithRole('perencanaan'), 'pengukuran:verifikasi');

        $this->actingAs($pic)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75])->assertRedirect('/pengukuran');

        $this->assertSame('pic', $this->pengukuran->fresh()->latestVersion->jalur_pengajuan);
        $item = DB::table('notifications')->sole();
        $this->assertSame([$p1->id, 'pengukuran.diajukan'], [$item->notifiable_id, $item->type]);
        $this->assertSame(['judul' => 'Pengukuran I-UJI Triwulan I 2026 menunggu verifikasi', 'ringkasan' => "Diajukan oleh {$pic->nama}",
            'route_name' => 'verifikasi.show', 'route_params' => ['id' => $this->pengukuran->id]], json_decode($item->data, true));
    }
}
