<?php

namespace Tests\Feature;

use App\Models\Berkas;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\RenstraPk;
use App\Models\TargetKinerja;
use App\Services\Pengingat\PengingatKontekstual;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class PengingatKontekstualTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_ambang_harian_dan_hari_terakhir_inklusif(): void
    {
        foreach ([7 => null, 8 => 'info', 11 => 'info', 12 => 'warning', 13 => 'warning', 14 => 'danger', 15 => 'danger', 16 => 'danger'] as $day => $level) {
            $this->travelTo(now()->setDate(2026, 3, $day));
            $items = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'];
            if ($level === null) {
                $this->assertSame([], $items);
            } else {
                $this->assertSame($level, $items[0]['tingkat']);
                if ($day === 15) {
                    $this->assertStringContainsString('Batas pengisian hari ini', $items[0]['judul']);
                }
                if ($day === 16) {
                    $this->assertNull($items[0]['href']);
                    $this->assertStringContainsString('Hubungi Tim Perencanaan.', $items[0]['keterangan']);
                }
            }
        }
    }

    public function test_sudah_diajukan_tuntas_dikembalikan_muncul_lagi(): void
    {
        foreach (['diajukan', 'diverifikasi', 'disahkan', 'dikembalikan', 'draft'] as $status) {
            $this->pengukuran->update(['status_alur' => $status]);
            $items = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'];
            $this->assertCount(in_array($status, ['draft', 'dikembalikan']) ? 1 : 0, $items);
            if ($status === 'draft' || $status === 'dikembalikan') {
                $this->assertStringContainsString($status === 'draft' ? '1 Dalam proses · Draf' : '1 Dalam proses · Dikembalikan', $items[0]['keterangan']);
            }
        }
    }

    public function test_header_absen_dihitung_belum_mengisi_dan_memakai_hak_create(): void
    {
        $this->pengukuran->delete();
        $item = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'][0];
        $this->assertStringContainsString('1 Belum mengisi.', $item['keterangan']);
        $this->assertStringNotContainsString('Draf', $item['keterangan']);
        $this->assertNotNull($item['href']);
        $this->actingAs($this->actor)->get($item['href'])->assertOk()->assertInertia(fn ($page) => $page
            ->where('pagination.total', 1)->where('pengukurans.0.jenis', 'absent')->where('pengukurans.0.can.create', true));

        foreach (['pengukuran:update', 'pengukuran:create'] as $code) {
            DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id,
                'permission_id' => Permission::where('kode', $code)->value('id'), 'unit_id' => $this->unit->id,
                'alasan' => 'Pengujian hak header absen', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
            $after = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'][0];
            if ($code === 'pengukuran:update') {
                $this->assertNotNull($after['href']);
                $this->assertStringNotContainsString('Hak input', $after['keterangan']);
            } else {
                $this->assertNull($after['href']);
                $this->assertStringContainsString('Hak input pada unit ini belum tersedia', $after['keterangan']);
            }
        }
        $this->get($item['href'])->assertOk()->assertInertia(fn ($page) => $page->where('pengukurans.0.can.create', false));
        $this->assertDatabaseCount('pengukuran_kinerjas', 0);
    }

    public function test_penutupan_dan_sesi_koreksi_tidak_membuka_banner(): void
    {
        $this->jadwal->update(['penutupan' => '2026-03-14']);
        $this->assertSame([], app(PengingatKontekstual::class)->handle($this->actor)['pribadi']);
        $this->jadwal->update(['penutupan' => '2026-12-31', 'status' => 'ditutup']);
        $this->assertSame([], app(PengingatKontekstual::class)->handle($this->actor)['pribadi']);
    }

    public function test_bukan_pj_tidak_menerima_kewajiban_pribadi(): void
    {
        $this->assertSame([], app(PengingatKontekstual::class)->handle($this->userWithRole('pegawai'))['pribadi']);
    }

    public function test_jendela_direvisi_memulihkan_countdown_dan_signature(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 16));
        $before = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'][0];
        $this->jadwal->periode()->first()->update(['pengisian_selesai' => '2026-03-19']);
        $after = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'][0];
        $this->assertSame('warning', $after['tingkat']);
        $this->assertNotNull($after['href']);
        $this->assertNotSame($before['signature'], $after['signature']);
    }

    public function test_pic_tanpa_hak_input_tetap_mendapat_informasi(): void
    {
        $pic = $this->userWithRole('pegawai');
        PenugasanIndikator::create(['indikator_id' => $this->context->indikator_id, 'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-03-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $item = app(PengingatKontekstual::class)->handle($pic)['pribadi'][0];
        $this->assertStringContainsString('Hak input pada unit ini belum tersedia', $item['keterangan']);
        $this->assertNull($item['href']);
        $this->grant($pic, 'pengukuran:update');
        $ready = app(PengingatKontekstual::class)->handle($pic)['pribadi'][0];
        $this->assertStringNotContainsString('Hak input', $ready['keterangan']);
        $this->actingAs($pic)->get($ready['href'])->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.fokus', 'kewajiban')->where('pagination.total', 1)->where('can.selectPeriod', false));
    }

    public function test_mode_fokus_mempertahankan_guard_dan_tidak_membuat_header(): void
    {
        $query = ['fokus' => 'kewajiban', 'jadwal_id' => $this->jadwal->id, 'periode_id' => $this->pengukuran->periode_id];
        $before = PengukuranKinerja::count();
        $this->actingAs($this->actor)->get('/pengukuran?'.http_build_query($query))->assertOk()
            ->assertInertia(fn ($page) => $page->where('filters.fokus', 'kewajiban')->where('pagination.total', 1));
        $this->assertSame($before, PengukuranKinerja::count());
        $pic = $this->userWithRole('pegawai');
        $this->actingAs($pic)->get('/pengukuran?'.http_build_query($query))->assertForbidden();
        unset($query['fokus']);
        $this->actingAs($pic)->get('/pengukuran?'.http_build_query($query))->assertForbidden();
    }

    public function test_fokus_tetap_dapat_dibuka_setelah_kewajiban_terakhir_diajukan(): void
    {
        $url = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'][0]['href'];
        $this->actingAs($this->actor)->get($url)->assertOk();
        $this->pengukuran->update(['status_alur' => 'diajukan']);

        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.fokus', 'kewajiban')->where('pagination.total', 0)
            ->where('pengukurans', [])->where('pengingat.pribadi', []));
    }

    public function test_tally_perencanaan_tidak_menghitung_draft_terlambat_dua_kali(): void
    {
        $p = $this->jadwal->periode()->with('periode')->first();
        $items = app(PengingatKontekstual::class)->ringkasan($this->actor, $this->jadwal, $p,
            ['total' => 10, 'belum_mengisi' => 2, 'draft' => 3, 'dikembalikan' => 1, 'tidak_mengisi' => 2]);
        $this->assertStringContainsString('6 dari 10 indikator belum diajukan', $items[0]['keterangan']);
        $this->assertSame([], app(PengingatKontekstual::class)->ringkasan($this->userWithRole('pegawai'), $this->jadwal, $p,
            ['total' => 10, 'belum_mengisi' => 2, 'draft' => 3, 'dikembalikan' => 1]));
    }

    public function test_periode_lampau_dan_koreksi_tidak_menghasilkan_banner_normal(): void
    {
        $this->jadwal->update(['activated_at' => now()->addDay()]);
        $this->assertSame([], app(PengingatKontekstual::class)->handle($this->actor)['pribadi']);
        $this->jadwal->update(['activated_at' => now(), 'koreksi_mulai' => now(), 'koreksi_sampai' => now()->addDay(),
            'lingkup_koreksi' => ['jenis_objek' => ['pengukuran'], 'indikator_ids' => [$this->context->indikator_id], 'periode_ids' => [$this->pengukuran->periode_id]]]);
        $this->assertSame([], app(PengingatKontekstual::class)->handle($this->actor)['pribadi']);
    }

    public function test_pk_draft_dan_penerima_mengikuti_permission(): void
    {
        $this->jadwal->update(['status' => 'draft']);
        $items = app(PengingatKontekstual::class)->handle($this->actor)['pk'];
        $this->assertCount(1, $items);
        $this->assertStringContainsString('2026 belum lengkap', $items[0]['judul']);
        $this->assertStringContainsString('belum memiliki lampiran', $items[0]['keterangan']);
        $pegawai = $this->userWithRole('pegawai');
        $this->grant($pegawai, 'berkas:upload');
        $this->assertSame([], app(PengingatKontekstual::class)->handle($pegawai)['pk']);
    }

    public function test_pk_absen_memunculkan_pesan_g1_tanpa_pesan_lampiran_g4(): void
    {
        $pkId = $this->jadwal->renstra_pk_id;
        $this->jadwal->update(['status' => 'draft', 'renstra_pk_id' => null]);
        RenstraPk::findOrFail($pkId)->delete();
        TargetKinerja::create(['indikator_kinerja_id' => $this->context->indikator_id, 'tahun' => 2026,
            'target_tahunan' => 70, 'updated_by' => $this->actor->id]);

        $items = app(PengingatKontekstual::class)->handle($this->actor)['pk'];

        $this->assertCount(1, $items);
        $this->assertSame('Perjanjian Kinerja tahun ini belum dibuat.', $items[0]['keterangan']);
    }

    public function test_target_pk_null_memunculkan_pesan_g2_sedangkan_nol_sudah_lengkap(): void
    {
        $this->jadwal->update(['status' => 'draft']);
        Berkas::forceCreate(['berkasable_type' => 'renstra_pk', 'berkasable_id' => $this->jadwal->renstra_pk_id,
            'mode' => 'teks', 'isi_teks' => 'Lampiran PK sintetis', 'uploaded_by' => $this->actor->id]);
        $target = TargetKinerja::create(['indikator_kinerja_id' => $this->context->indikator_id, 'tahun' => 2026,
            'target_tahunan' => null, 'updated_by' => $this->actor->id]);
        $items = app(PengingatKontekstual::class)->handle($this->actor)['pk'];
        $this->assertCount(1, $items);
        $this->assertSame('1 indikator belum memiliki target tahunan.', $items[0]['keterangan']);

        $target->update(['target_tahunan' => 0]);
        $this->assertSame([], app(PengingatKontekstual::class)->handle($this->actor)['pk']);
    }

    public function test_deny_baca_unit_menghapus_banner_dan_menolak_fokus(): void
    {
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', 'pengukuran:read')->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Pengujian deny baca', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now(),
        ]);
        $this->assertSame([], app(PengingatKontekstual::class)->handle($this->actor)['pribadi']);
        $this->actingAs($this->actor)->get('/pengukuran?'.http_build_query(['fokus' => 'kewajiban',
            'jadwal_id' => $this->jadwal->id, 'periode_id' => $this->pengukuran->periode_id]))->assertForbidden();
    }

    public function test_batas_hari_memakai_wita_bukan_utc(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-14 16:01:00', 'UTC'));
        $item = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'][0];
        $this->assertStringContainsString('Batas pengisian hari ini', $item['judul']);
    }

    public function test_jadwal_tahun_sebelumnya_tetap_muncul_di_januari(): void
    {
        $this->jadwal->update(['penutupan' => '2027-01-31']);
        $this->jadwal->periode()->first()->update(['pengisian_mulai' => '2027-01-01', 'pengisian_selesai' => '2027-01-15']);
        $this->travelTo(now()->setDate(2027, 1, 12));
        $item = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'][0];
        $this->assertStringContainsString('2026', $item['judul']);
        $this->assertSame('warning', $item['tingkat']);
    }

    public function test_props_tiga_halaman_dan_keterangan_ra_tanpa_mutasi(): void
    {
        foreach (['/dashboard', '/pengukuran', '/rencana-aksi'] as $url) {
            $this->actingAs($this->actor)->get($url)->assertOk()->assertInertia(fn ($page) => $page
                ->has('pengingat.pribadi', 1)->where('pengingat.pk', []));
        }
        $this->assertDatabaseCount('pengukuran_kinerjas', 1);
        $this->plan->update(['status_alur' => 'draft']);
        $items = app(PengingatKontekstual::class)->handle($this->actor, 'rencana_aksi:read', false);
        $this->assertStringContainsString('Rencana Aksi belum disahkan.', $items['pribadi'][0]['keterangan']);
        $this->assertSame([], $items['ringkasan']);
        $this->assertSame([], $items['pk']);
    }

    public function test_setiap_izin_pk_atau_aktivasi_cukup_dan_deny_menang(): void
    {
        $this->jadwal->update(['status' => 'draft']);
        $user = $this->userWithRole('pegawai');
        $role = $user->roles->first();
        foreach (['pk:create', 'pk:update', 'jadwal:aktivasi'] as $code) {
            $permission = Permission::where('kode', $code)->firstOrFail();
            $role->permissions()->attach($permission->id, ['id' => (string) Str::uuid(), 'created_at' => now()]);
            $items = app(PengingatKontekstual::class)->handle($user)['pk'];
            $this->assertCount(1, $items);
            $this->assertSame($code === 'jadwal:aktivasi' ? route('jadwal.show', $this->jadwal->id) : route('perjanjian-kinerja.index'), $items[0]['href']);
            $denyId = (string) Str::uuid();
            DB::table('user_permission_denied')->insert(['id' => $denyId, 'user_id' => $user->id, 'permission_id' => $permission->id,
                'unit_id' => null, 'alasan' => 'Pengujian deny PK', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
            $this->assertSame([], app(PengingatKontekstual::class)->handle($user)['pk']);
            DB::table('user_permission_denied')->where('id', $denyId)->delete();
            $role->permissions()->detach($permission->id);
        }
    }
}
