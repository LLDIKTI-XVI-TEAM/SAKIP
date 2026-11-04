<?php

namespace Tests\Feature;

use App\Actions\Jadwal\BukaKembaliJadwal;
use App\Actions\Jadwal\TutupJadwal;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\User;
use App\Services\Pengingat\PengingatKontekstual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class PengingatSesiKoreksiTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    /** Pembukaan memakai writer resmi; PIC hanya memiliki grant unit, bukan jalur Perencanaan. @return array{User, PeriodeJadwal} */
    private function bukaSesi(): array
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:create');
        $this->grant($pic, 'pengukuran:update');
        $lain = $this->indikatorBaru('I-DI-LUAR-LINGKUP');
        $this->headerUntuk($lain);
        foreach ([$this->context->indikator_id, $lain->indikator_id] as $id) {
            PenugasanIndikator::create(['indikator_id' => $id, 'user_id' => $pic->id,
                'tanggal_mulai_berlaku' => '2026-03-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        }
        $tw2 = $this->tambahPeriode(2, '2026-06-01', '2026-06-15');
        $this->travelTo(Carbon::parse('2027-01-05 09:00:00', 'Asia/Makassar'));
        app(TutupJadwal::class)->handle($this->actor, $this->jadwal->id,
            ['expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => 'Penutupan tahun pengujian.']);
        app(BukaKembaliJadwal::class)->handle($this->actor, $this->jadwal->id, [
            'expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => 'Koreksi indikator TW I.',
            'koreksi_mulai' => '2027-01-05T08:00', 'koreksi_sampai' => '2027-01-08T17:00',
            'lingkup_koreksi' => ['jenis_objek' => ['pengukuran'], 'indikator_ids' => [$this->context->indikator_id],
                'periode_ids' => [$this->pengukuran->periode_id]],
        ]);

        return [$pic, $tw2];
    }

    public function test_pembukaan_resmi_pic_mengintegrasikan_banner_fokus_dan_policy_pada_lingkup_yang_sama(): void
    {
        [$pic, $tw2] = $this->bukaSesi();
        $evaluator = app(PengingatKontekstual::class);
        $window = $this->jadwal->periode()->where('periode_id', $this->pengukuran->periode_id)->firstOrFail();
        // Tanggal yang kebetulan berada di dalam sesi bukan penanda pembukaan resmi.
        $window->update(['pengisian_mulai' => '2027-01-05', 'pengisian_selesai' => '2027-01-07']);
        $this->assertSame([], $evaluator->handle($pic)['pribadi']);
        $this->assertFalse(Gate::forUser($pic)->allows('update', $this->pengukuran->fresh()));

        $this->actingAs($this->actor)->post('/jadwal/'.$this->jadwal->id.'/revisi-jendela', [
            'expected_revisi' => $this->jadwal->fresh()->revisi, 'alasan' => 'Pembukaan PIC resmi selama sesi koreksi.',
            'periode' => [['periode_id' => $window->periode_id, 'pengisian_mulai' => '2027-01-05', 'pengisian_selesai' => '2027-01-08'],
                ['periode_id' => $tw2->periode_id, 'pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-15']],
        ])->assertSessionHasNoErrors();

        $items = $evaluator->handle($pic)['pribadi'];
        $this->assertCount(1, $items);
        $this->assertStringContainsString('1 Dalam proses · Draf', $items[0]['keterangan']);
        $this->assertSame('warning', $items[0]['tingkat']);
        $this->assertTrue(Gate::forUser($pic)->allows('update', $this->pengukuran->fresh()));
        $this->assertTrue(Gate::forUser($pic)->allows('submit', $this->pengukuran->fresh()));
        $this->actingAs($pic)->get($items[0]['href'])->assertOk()->assertInertia(fn ($page) => $page
            ->where('pagination.total', 1)->where('pengukurans.0.indikator_id', $this->context->indikator_id));
        $this->get('/pengukuran?'.http_build_query(['fokus' => 'kewajiban', 'jadwal_id' => $this->jadwal->id,
            'periode_id' => $tw2->periode_id]))->assertForbidden();

        $scope = $this->jadwal->fresh()->lingkup_koreksi;
        $this->jadwal->update(['lingkup_koreksi' => [...$scope, 'jenis_objek' => ['rencana_aksi']]]);
        $this->assertSame([], $evaluator->handle($pic)['pribadi']);
        $this->jadwal->update(['lingkup_koreksi' => $scope]);
        DB::table('user_permission_granted')->where('user_id', $pic->id)
            ->where('permission_id', Permission::where('kode', 'pengukuran:update')->value('id'))->delete();
        $info = $evaluator->handle($pic)['pribadi'][0];
        $this->assertNull($info['href']);
        $this->assertStringContainsString('Hak input pada unit ini belum tersedia', $info['keterangan']);
        $this->grant($pic, 'pengukuran:update');

        $this->travelTo(Carbon::parse('2027-01-08 17:00:00', 'Asia/Makassar'));
        $this->assertCount(1, $evaluator->handle($pic)['pribadi']);
        $this->assertTrue(Gate::forUser($pic)->allows('update', $this->pengukuran->fresh()));
        $this->travelTo(Carbon::parse('2027-01-08 17:01:00', 'Asia/Makassar'));
        $this->assertSame([], $evaluator->handle($pic)['pribadi']);
        $this->assertFalse(Gate::forUser($pic)->allows('update', $this->pengukuran->fresh()));
        $this->travelTo(Carbon::parse('2027-01-05 09:00:00', 'Asia/Makassar'));
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $pic->id,
            'permission_id' => Permission::where('kode', 'pengukuran:read')->value('id'), 'unit_id' => $this->unit->id,
            'alasan' => 'Pengujian deny baca sesi', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->assertSame([], $evaluator->handle($pic)['pribadi']);
        $this->get($items[0]['href'])->assertForbidden();
    }
}
