<?php

namespace Tests\Feature;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class IndexPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture,RefreshDatabase;

    public function test_default_menampilkan_baris_existing_beserta_konteks_jadwal(): void
    {
        $tw1 = $this->pengukuran->periode;

        $this->daftar($this->actor)->assertOk()->assertInertia(fn ($page) => $page
            ->where('konteks', [['jadwal_id' => $this->jadwal->id, 'tahun' => 2026,
                'periode' => ['id' => $tw1->id, 'nama_periode' => 'Triwulan I', 'urutan' => 1, 'tahun' => 2026, 'is_nilai_akhir' => false]]])
            ->where('filters', ['jadwal_id' => null, 'periode_id' => null])->where('can.selectPeriod', true)
            ->where('pilihan_jadwal.0.id', $this->jadwal->id)->has('pilihan_jadwal.0.periode', 1)->missing('periode')
            ->has('pengukurans', 1)->where('pengukurans.0.jenis', 'existing')->where('pengukurans.0.pengukuran.id', $this->pengukuran->id)
            ->where('pengukurans.0.pengukuran.status', 'draft')->where('pengukurans.0.pengukuran.target', 70)
            ->where('pengukurans.0.jadwal_snapshot_id', $this->context->id)->where('pengukurans.0.can.create', false)
            ->where('pengukurans.0.unit', ['id' => $this->unit->id, 'nama' => 'Unit Pengujian'])->where('pengukurans.0.pic.id', $this->actor->id)
            ->where('pengukurans.0.indikator', ['kode' => 'I-UJI', 'nama' => 'Indikator Uji', 'satuan' => 'poin', 'desimal_tampilan' => 2]));
    }

    public function test_daftar_januari_memakai_tahun_jadwal(): void
    {
        $this->jadwal->update(['penutupan' => '2027-01-31']);
        $tw4 = $this->periode(4, '2027-01-01', '2027-01-10');
        $this->travelTo(now()->setDate(2027, 1, 5));

        $this->daftar($this->actor)->assertOk()->assertInertia(fn ($page) => $page
            ->where('konteks.0.tahun', 2026)->where('konteks.0.periode.id', $tw4->id)
            ->has('pengukurans', 1)->where('pengukurans.0.jenis', 'absent')->where('pengukurans.0.berlaku', true)
            ->where('pengukurans.0.tahun', 2026)->where('pengukurans.0.periode.id', $tw4->id)->where('pengukurans.0.pengukuran', null)
            ->where('pengukurans.0.target', null)->where('pengukurans.0.can.create', true));
    }

    public function test_dua_jadwal_aktif_digabung_dalam_satu_daftar(): void
    {
        $next = JadwalTahunan::create([...$this->jadwal->only(['renstra_id', 'renstra_pk_id']), 'tahun' => 2027, 'penutupan' => '2027-12-31', 'status' => 'aktif']);
        PeriodeJadwal::create(['jadwal_id' => $next->id, 'periode_id' => $this->pengukuran->periode_id, 'pengisian_mulai' => '2026-03-10', 'pengisian_selesai' => '2026-03-20', 'reviu_mulai' => '2026-03-20', 'reviu_selesai' => '2026-04-20']);
        JadwalSnapshot::create([...$this->context->only(['indikator_id', 'periode_mulai_id', 'unit_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']), 'jadwal_id' => $next->id]);

        $this->daftar($this->actor)->assertOk()->assertInertia(fn ($page) => $page
            ->has('konteks', 2)->where('konteks.0.tahun', 2026)->where('konteks.1.tahun', 2027)->where('pagination.total', 2)
            ->where('pengukurans.0.tahun', 2026)->where('pengukurans.0.jenis', 'existing')
            ->where('pengukurans.1.tahun', 2027)->where('pengukurans.1.jenis', 'absent')->where('pengukurans.1.jadwal_id', $next->id));
    }

    public function test_konteks_kosong_membedakan_pengisian_belum_dimulai_dan_tanpa_jadwal_aktif(): void
    {
        $this->travelTo(now()->setDate(2026, 2, 15));
        $this->daftar($this->actor)->assertOk()->assertInertia(fn ($page) => $page
            ->where('konteks', [['jadwal_id' => $this->jadwal->id, 'tahun' => 2026, 'periode' => null]])->has('pengukurans', 0)->where('pagination.total', 0));

        $this->jadwal->update(['status' => 'ditutup']);
        $this->daftar($this->actor)->assertOk()->assertInertia(fn ($page) => $page->where('konteks', [])->has('pengukurans', 0));
    }

    public function test_pic_di_luar_jendela_hanya_melihat_status_absent(): void
    {
        $this->pengukuran->delete();
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:create');
        PenugasanIndikator::create(['indikator_id' => $this->context->indikator_id, 'user_id' => $pic->id, 'tanggal_mulai_berlaku' => '2026-02-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        $this->daftar($pic)->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukurans.0.can.create', true)->where('pengukurans.0.pic', ['id' => $pic->id, 'nama' => $pic->nama])
            ->where('can.selectPeriod', false)->missing('pilihan_jadwal'));
        $this->travelTo(now()->setDate(2026, 3, 16));
        $this->daftar($pic)->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukurans.0.jenis', 'absent')->where('pengukurans.0.berlaku', true)->where('pengukurans.0.can.create', false));
    }

    public function test_pembaca_bukan_pj_tetap_melihat_baris_tanpa_aksi(): void
    {
        $this->daftar($this->userWithRole('pegawai'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('pengukurans', 1)->where('pengukurans.0.pengukuran.id', $this->pengukuran->id)->where('pengukurans.0.can.create', false));
    }

    public function test_filter_perencanaan_memilih_periode_sebelum_jendela_dengan_query_yang_sama(): void
    {
        $planner = $this->userWithRole('perencanaan');
        $tw2 = $this->periode(2, '2026-06-01', '2026-06-15');

        $this->daftar($planner, ['jadwal_id' => $this->jadwal->id, 'periode_id' => $tw2->id])->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters', ['jadwal_id' => $this->jadwal->id, 'periode_id' => $tw2->id])->has('konteks', 1)->where('konteks.0.periode.id', $tw2->id)
            ->has('pengukurans', 1)->where('pengukurans.0.jenis', 'absent')->where('pengukurans.0.can.create', true)
            ->where('pilihan_jadwal.0.periode.1.id', $tw2->id));
    }

    public function test_filter_dari_jalur_non_perencanaan_ditolak_403_sebelum_lookup(): void
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        foreach ([['jadwal_id' => $this->jadwal->id, 'periode_id' => $this->pengukuran->periode_id], ['jadwal_id' => 'bukan-uuid'], ['periode_id' => '']] as $query) {
            $this->daftar($pic, $query)->assertForbidden();
        }

        $planner = $this->userWithRole('perencanaan');
        $this->deny($planner, 'pengukuran:update', null);
        $this->daftar($planner, ['jadwal_id' => $this->jadwal->id, 'periode_id' => $this->pengukuran->periode_id])->assertForbidden();
        $this->daftar($planner)->assertOk()->assertInertia(fn ($page) => $page->where('can.selectPeriod', false));
    }

    public function test_filter_tidak_sah_bagi_perencanaan_ditolak_validasi(): void
    {
        $planner = $this->userWithRole('perencanaan');
        $tw1 = $this->pengukuran->periode_id;
        $foreign = Periode::create(['nama' => 'Periode Asing', 'urutan' => 9, 'aktif' => true, 'is_nilai_akhir' => false]);

        $this->daftar($planner, ['jadwal_id' => $this->jadwal->id])->assertSessionHasErrors('periode_id');
        $this->daftar($planner, ['jadwal_id' => 'bukan-uuid', 'periode_id' => $tw1])->assertSessionHasErrors('jadwal_id');
        $this->daftar($planner, ['jadwal_id' => $this->jadwal->id, 'periode_id' => $foreign->id])->assertSessionHasErrors('periode_id');
        $this->actingAs($planner)->getJson('/pengukuran?'.http_build_query(['jadwal_id' => $this->jadwal->id, 'periode_id' => $foreign->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('periode_id');

        $this->travelTo(now()->setDate(2026, 12, 31));
        $this->daftar($planner, ['jadwal_id' => $this->jadwal->id, 'periode_id' => $tw1])->assertOk();
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->daftar($planner, ['jadwal_id' => $this->jadwal->id, 'periode_id' => $tw1])->assertSessionHasErrors('jadwal_id');

        $this->travelTo(now()->setDate(2026, 3, 15));
        $this->jadwal->update(['status' => 'ditutup']);
        $this->daftar($planner, ['jadwal_id' => $this->jadwal->id, 'periode_id' => $tw1])->assertSessionHasErrors('jadwal_id');
    }

    public function test_kapabilitas_filter_tidak_bergantung_header_dan_deny_unit_tetap_pada_baris(): void
    {
        $planner = $this->userWithRole('perencanaan');
        $this->travelTo(now()->setDate(2026, 2, 15));
        $this->daftar($planner)->assertOk()->assertInertia(fn ($page) => $page
            ->has('pengukurans', 0)->where('can.selectPeriod', true)->has('pilihan_jadwal', 1));

        $this->pengukuran->delete();
        $this->deny($planner, 'pengukuran:create', $this->unit->id);
        $query = ['jadwal_id' => $this->jadwal->id, 'periode_id' => $this->context->periode_mulai_id];
        $this->daftar($planner, $query)->assertOk()->assertInertia(fn ($page) => $page->where('pengukurans.0.can.create', false));
        $this->deny($planner, 'pengukuran:read', $this->unit->id);
        $this->daftar($planner, $query)->assertOk()->assertInertia(fn ($page) => $page->has('pengukurans', 0)->where('pagination.total', 0));
    }

    public function test_query_daftar_dibatasi_jumlah_unit_bukan_jumlah_baris(): void
    {
        $planner = $this->userWithRole('perencanaan');
        $tw1 = $this->pengukuran->periode;
        $this->pengukuran->delete();
        [$single, $singleGrants] = $this->hitungQuery($planner);
        foreach (range(1, 20) as $i) {
            $this->indikator('I-UJI-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), $tw1, $this->unit);
        }
        $this->assertSame([$single, $singleGrants], $this->hitungQuery($planner));

        $units = [Unit::create(['nama' => 'Unit B', 'created_by' => $this->actor->id]), Unit::create(['nama' => 'Unit C', 'created_by' => $this->actor->id])];
        foreach ($units as $index => $unit) {
            $this->indikator('A-'.$index, $tw1, $unit);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(PermissionResolver::class)->decide($planner, 'pengukuran:create', $units[0]->id);
        $perUnit = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame([$single + 2 * $perUnit, $singleGrants + 2], $this->hitungQuery($planner));

        $this->daftar($planner)->assertOk()->assertInertia(fn ($page) => $page
            ->has('pengukurans', 20)->where('pagination.total', 23)->where('pagination.last_page', 2));
        $this->deny($planner, 'pengukuran:read', $units[1]->id);
        $this->daftar($planner)->assertOk()->assertInertia(fn ($page) => $page->where('pagination.total', 22));
    }

    public function test_baris_existing_tidak_menambah_query_per_baris(): void
    {
        [$single] = $this->hitungQuery($this->actor);
        foreach (range(1, 19) as $i) {
            [$indicator, $snapshot] = $this->indikator('I-ADA-'.$i, $this->pengukuran->periode, $this->unit);
            PengukuranKinerja::create(['indikator_id' => $indicator->id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id, 'jadwal_snapshot_id' => $snapshot->id, 'sumber_nilai' => 'manual', 'created_by' => $this->actor->id]);
        }

        $this->assertSame($single, $this->hitungQuery($this->actor)[0]);
    }

    public function test_versi_efektif_terpilih_untuk_absent_dan_existing_tetap_dijepit(): void
    {
        $planner = $this->userWithRole('perencanaan');
        $unitB = Unit::create(['nama' => 'Unit B', 'created_by' => $this->actor->id]);
        $tw1 = $this->pengukuran->periode;
        $tw2 = $this->periode(2, '2026-06-01', '2026-06-15');
        $tw3 = $this->periode(3, '2026-09-01', '2026-09-15');
        $tw4 = $this->periode(4, '2026-12-01', '2026-12-15');
        $this->versi(2, $tw3, $unitB);

        $this->assertBaris($planner, $tw2, ['jenis' => 'absent', 'jadwal_snapshot_id' => $this->context->id, 'unit.id' => $this->unit->id]);
        $v3 = $this->versi(3, $tw1, $unitB);
        $this->assertBaris($planner, $tw4, ['jenis' => 'absent', 'jadwal_snapshot_id' => $v3->id, 'unit.id' => $unitB->id, 'berlaku' => true]);
        $this->assertBaris($planner, $tw1, ['jenis' => 'existing', 'jadwal_snapshot_id' => $this->context->id, 'unit.id' => $this->unit->id]);

        $this->actingAs($planner)->post('/pengukuran/ensure-draft', ['jadwal_snapshot_id' => $v3->id, 'periode_id' => $tw4->id])->assertRedirect();
        $this->assertSame($v3->id, PengukuranKinerja::where('periode_id', $tw4->id)->sole()->jadwal_snapshot_id);
    }

    public function test_sebelum_efektif_tidak_berlaku_memakai_versi_terendah(): void
    {
        $planner = $this->userWithRole('perencanaan');
        $tw2 = $this->periode(2, '2026-06-01', '2026-06-15');
        $tw3 = $this->periode(3, '2026-09-01', '2026-09-15');
        [$indicator, $v1] = $this->indikator('A-NA', $tw3, $this->unit);
        JadwalSnapshot::create([...$v1->only(['jadwal_id', 'indikator_id', 'unit_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'nomor_versi' => 2, 'menggantikan_id' => $v1->id, 'alasan_koreksi' => 'Versi sintetis', 'rujukan_koreksi' => 'Fixture', 'periode_mulai_id' => $this->periode(4, '2026-12-01', '2026-12-15')->id]);

        $this->assertBaris($planner, $tw2, ['indikator.kode' => 'A-NA', 'jenis' => 'absent', 'berlaku' => false, 'jadwal_snapshot_id' => $v1->id,
            'target' => null, 'pengukuran' => null, 'can.create' => false]);
        $this->assertSame(0, PengukuranKinerja::where('indikator_id', $indicator->id)->count());
    }

    public function test_indikator_arsip_tanpa_header_dikeluarkan_tetapi_header_existing_tetap(): void
    {
        $planner = $this->userWithRole('perencanaan');
        $tw2 = $this->periode(2, '2026-06-01', '2026-06-15');
        $this->pengukuran->indikator->update(['status' => IndikatorKinerja::STATUS_ARSIP]);

        $this->daftar($planner)->assertOk()->assertInertia(fn ($page) => $page->has('pengukurans', 1)->where('pengukurans.0.jenis', 'existing'));
        $this->daftar($planner, ['jadwal_id' => $this->jadwal->id, 'periode_id' => $tw2->id])->assertOk()
            ->assertInertia(fn ($page) => $page->has('pengukurans', 0)->where('pagination.total', 0));
    }

    public function test_unit_master_berubah_tidak_mengganti_unit_baris_dan_deny_mengikuti_unit_baris(): void
    {
        $unitB = Unit::create(['nama' => 'Unit B', 'created_by' => $this->actor->id]);
        $this->pengukuran->indikator->update(['unit_id' => $unitB->id]);

        $this->daftar($this->actor)->assertOk()->assertInertia(fn ($page) => $page->where('pengukurans.0.unit.id', $this->unit->id));
        $this->deny($this->actor, 'pengukuran:read', $unitB->id);
        $this->daftar($this->actor)->assertOk()->assertInertia(fn ($page) => $page->has('pengukurans', 1));
        $this->deny($this->actor, 'pengukuran:read', $this->unit->id);
        $this->daftar($this->actor)->assertOk()->assertInertia(fn ($page) => $page->has('pengukurans', 0));
    }

    public function test_baris_yang_sudah_diajukan_memakai_metadata_versi_beku_seperti_detail(): void
    {
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75])->assertSessionHasNoErrors();
        // Master boleh berubah setelah pengajuan; daftar harus sama dengan detail yang membaca versi beku.
        $this->pengukuran->indikator->update(['kode' => 'I-BARU']);
        $this->unit->update(['nama' => 'Unit Berganti Nama']);
        // Koreksi nama periode tetap diizinkan walau metadata terkunci (lihat PeriodeManagementTest).
        $this->pengukuran->periode->update(['nama' => 'Triwulan I Koreksi']);

        $this->daftar($this->actor)->assertOk()->assertInertia(fn ($page) => $page->where('pengukurans.0.pengukuran.status', 'diajukan')
            ->where('pengukurans.0.periode', ['id' => $this->pengukuran->periode_id, 'nama_periode' => 'Triwulan I', 'urutan' => 1, 'tahun' => 2026, 'is_nilai_akhir' => false])
            ->where('pengukurans.0.indikator', ['kode' => 'I-UJI', 'nama' => 'Indikator Uji', 'satuan' => 'poin', 'desimal_tampilan' => 2])
            ->where('pengukurans.0.unit', ['id' => $this->unit->id, 'nama' => 'Unit Pengujian']));
    }

    private function daftar(User $user, array $query = []): TestResponse
    {
        return $this->actingAs($user)->get('/pengukuran'.($query === [] ? '' : '?'.http_build_query($query)));
    }

    /** @param array<string, mixed> $expected nilai baris pertama pada filter periode */
    private function assertBaris(User $user, Periode $periode, array $expected): void
    {
        $this->daftar($user, ['jadwal_id' => $this->jadwal->id, 'periode_id' => $periode->id])->assertOk()->assertInertia(function ($page) use ($expected) {
            foreach ($expected as $key => $value) {
                $page->where('pengukurans.0.'.$key, $value);
            }
        });
    }

    /** @return array{0: int, 1: int} jumlah query permintaan daftar dan query grant resolver */
    private function hitungQuery(User $user): array
    {
        // Permintaan pertama mengisi cache proses; hanya permintaan berikutnya yang diukur.
        $this->actingAs($user)->get('/pengukuran')->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/pengukuran')->assertOk();
        $log = collect(DB::getQueryLog());
        DB::disableQueryLog();

        return [$log->count(), $log->filter(fn ($query) => str_contains($query['query'], 'user_permission_granted'))->count()];
    }

    private function periode(int $urutan, string $mulai, string $selesai): Periode
    {
        $periode = Periode::create(['nama' => 'Triwulan '.$urutan, 'urutan' => $urutan, 'aktif' => true, 'is_nilai_akhir' => false]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $periode->id, 'pengisian_mulai' => $mulai, 'pengisian_selesai' => $selesai, 'reviu_mulai' => $selesai, 'reviu_selesai' => now()->parse($selesai)->addMonth()->toDateString()]);

        return $periode;
    }

    private function versi(int $nomor, Periode $mulai, Unit $unit): JadwalSnapshot
    {
        return JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'indikator_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'nomor_versi' => $nomor, 'menggantikan_id' => $this->context->id, 'alasan_koreksi' => 'Versi sintetis', 'rujukan_koreksi' => 'Fixture', 'periode_mulai_id' => $mulai->id, 'unit_id' => $unit->id]);
    }

    /** @return array{0: IndikatorKinerja, 1: JadwalSnapshot} indikator manual baru beserta snapshot V1 pada jadwal fixture */
    private function indikator(string $kode, Periode $mulai, Unit $unit): array
    {
        $master = $this->pengukuran->indikator;
        $indicator = IndikatorKinerja::create([...$master->only(['sasaran_strategis_id', 'satuan', 'tipe_perhitungan', 'status', 'tahun_mulai_berlaku', 'created_by', 'created_by_role']),
            'unit_id' => $unit->id, 'kode' => $kode, 'nama' => 'Indikator '.$kode]);
        $snapshot = JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'indikator_id' => $indicator->id, 'periode_mulai_id' => $mulai->id, 'unit_id' => $unit->id, 'nama' => 'Indikator '.$kode]);

        return [$indicator, $snapshot];
    }

    private function deny(User $user, string $permission, ?string $unitId): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $permission)->value('id'),
            'unit_id' => $unitId, 'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }
}
