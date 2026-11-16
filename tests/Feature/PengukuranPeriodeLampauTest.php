<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use App\Models\KinerjaSnapshot;
use App\Models\KlaimKegiatan;
use App\Models\PengukuranKinerja;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class PengukuranPeriodeLampauTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private function periode(int $tahun = 2026, bool $lampau = true): void
    {
        RenstraPk::whereKey($this->jadwal->renstra_pk_id)->update(['tahun' => $tahun, 'tanggal_pk' => "$tahun-01-01"]);
        $this->jadwal->update(['tahun' => $tahun, 'penutupan' => "$tahun-12-31", 'activated_at' => $lampau ? "$tahun-03-16 00:00:00" : "$tahun-03-15 01:00:00"]);
        foreach ($this->jadwal->periode as $window) {
            $window->update(collect($window->only(['pengisian_mulai', 'pengisian_selesai']))
                ->map(fn ($tanggal) => $tanggal->setYear($tahun)->toDateString())->all());
        }
        $this->plan->update(['tahun' => $tahun, 'snapshot_draf_id' => $this->context->id]);
        $this->pengukuran->update(['tahun' => $tahun, ...array_fill_keys(PengukuranKinerja::NARASI, null)]);
        $this->travelTo(now()->setDate($tahun, 4, 1)->setTime(9, 0));
    }

    private function buktiWajib(): void
    {
        JenisBerkas::create(['indikator_id' => $this->pengukuran->indikator_id, 'nama' => 'Bukti historis', 'tahap' => 'pengukuran',
            'wajib' => true, 'izinkan_file' => false, 'izinkan_teks' => true, 'created_by' => $this->actor->id]);
    }

    private function tanpaRa(): void
    {
        $indicator = $this->pengukuran->indikator->replicate();
        $indicator->kode = 'I-LAMPAU-TANPA-RA';
        $indicator->save();
        $context = $this->context->replicate();
        $context->indikator_id = $indicator->id;
        $context->save();
        $this->pengukuran = PengukuranKinerja::create(['indikator_id' => $indicator->id, 'tahun' => $this->jadwal->tahun,
            'periode_id' => $this->pengukuran->periode_id, 'jadwal_snapshot_id' => $context->id, 'sumber_nilai' => 'manual', 'created_by' => $this->actor->id]);
    }

    #[DataProvider('kondisiRa')]
    public function test_lampau_mengecualikan_gerbang_dan_membekukan_konteks_yang_tersedia(string $kondisi): void
    {
        $this->periode();
        if ($kondisi === 'absent') {
            $this->tanpaRa();
        } elseif ($kondisi === 'draft') {
            $this->plan->update(['status_alur' => 'draft']);
        } elseif ($kondisi === 'unit-lain') {
            $unit = Unit::create(['nama' => 'Unit lain', 'created_by' => $this->actor->id]);
            $this->plan->update(['unit_id' => $unit->id]);
            $activity = Kegiatan::create(['unit_id' => $unit->id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
                'nama' => 'Kegiatan unit lain', 'tujuan' => 'Tidak boleh terbaca', 'created_by' => $this->actor->id]);
            KlaimKegiatan::create(['rencana_aksi_id' => $this->plan->id, 'kegiatan_id' => $activity->id,
                'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->actor->id, 'created_at' => now()]);
        }
        $this->buktiWajib();
        $url = '/pengukuran/'.$this->pengukuran->id;
        $this->actingAs($this->actor)->get($url.'/edit')->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukuran.prasyarat.siap', true)->where('pengukuran.prasyarat.alasan', [])
            ->where('pengukuran.persyaratan_bukti.0.pemenuhan.terpenuhi', false));
        $this->postJson($url, ['versi' => 1, 'action' => 'ajukan', 'nilai' => null])->assertRedirect('/pengukuran');
        $version = KinerjaSnapshot::sole();
        $this->assertSame($kondisi === 'valid' ? $this->planVersion->id : null, $version->rencana_aksi_versi_id);
        $this->assertSame($kondisi === 'valid' ? '70.00' : null, $version->snapshot['target']);
        $this->assertSame($kondisi !== 'valid', $version->snapshot['tanpa_target_periode']);
        $this->assertNull($version->snapshot['nilai']);
        $this->assertSame('belum_diisi', $version->snapshot['status_perhitungan']);
        $this->assertSame([], $version->snapshot['klaim']);
        $this->assertFalse($version->snapshot['persyaratan_bukti'][0]['pemenuhan']['terpenuhi']);
        $this->assertSame(['diajukan', 2], [$this->pengukuran->fresh()->status_alur, $this->pengukuran->fresh()->versi]);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ajukan')->count());
        $this->assertDatabaseCount('riwayat_pengukurans', 1);
        $this->get('/verifikasi/'.$this->pengukuran->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukuran.prasyarat.siap', true)->where('pengukuran.nilai', null)->where('pengukuran.klaim', []));
    }

    public static function kondisiRa(): array
    {
        return ['tanpa RA' => ['absent'], 'RA draft' => ['draft'], 'RA unit lain' => ['unit-lain'], 'RA sah' => ['valid']];
    }

    #[DataProvider('nilaiKomponen')]
    public function test_komponen_lampau_boleh_belum_lengkap_tetapi_alasan_penyebut_nol_tetap_wajib(?int $penyebut, ?string $alasan, bool $diterima): void
    {
        $this->periode();
        $context = JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'indikator_id', 'periode_mulai_id', 'unit_id', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'target']),
            'nomor_versi' => 2, 'menggantikan_id' => $this->context->id, 'alasan_koreksi' => 'Fixture lampau', 'rujukan_koreksi' => 'Pengujian', 'tipe_perhitungan' => 'rasio_persen']);
        $input = [];
        foreach (['pembilang', 'penyebut'] as $index => $role) {
            $component = IndikatorKomponen::create(['indikator_id' => $this->pengukuran->indikator_id, 'kode' => $role, 'label' => $role,
                'peran' => $role, 'bobot' => 1, 'urutan' => $index + 1, 'created_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now()]);
            JadwalSnapshotKomponen::create(['jadwal_snapshot_id' => $context->id, 'komponen_id' => $component->id,
                'kode' => $role, 'label' => $role, 'peran' => $role, 'bobot' => 1, 'urutan' => $index + 1]);
            $input[] = ['komponen_id' => $component->id, 'nilai' => $index === 0 ? 5 : $penyebut];
        }
        $this->pengukuran->update(['jadwal_snapshot_id' => $context->id, 'sumber_nilai' => 'komponen']);
        $this->buktiWajib();
        $response = $this->actingAs($this->actor)->postJson('/pengukuran/'.$this->pengukuran->id,
            ['versi' => 1, 'action' => 'ajukan', 'komponen' => $input, 'alasan_tidak_dapat_dihitung' => $alasan]);
        if (! $diterima) {
            $response->assertUnprocessable()->assertJsonValidationErrors('pengajuan');
            $this->assertContains('Alasan penyebut nol wajib diisi.', $response->json('errors.pengajuan'));
            $this->assertDatabaseCount('pengukuran_versi', 0);
            $this->assertDatabaseCount('pengukuran_komponen', 0);
            $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());

            return;
        }
        $response->assertRedirect('/pengukuran');
        $version = KinerjaSnapshot::sole();
        $this->assertNull($version->rencana_aksi_versi_id);
        $this->assertNull($version->snapshot['target']);
        $this->assertNull($version->snapshot['nilai']);
        $this->assertSame($penyebut === null ? 'belum_diisi' : 'tidak_dapat_dihitung', $version->snapshot['status_perhitungan']);
        $this->assertSame($penyebut === null ? null : '0.000000000000', $version->snapshot['komponen'][1]['nilai']);
    }

    public static function nilaiKomponen(): array
    {
        return ['komponen belum lengkap' => [null, null, true], 'penyebut nol tanpa alasan' => [0, null, false], 'penyebut nol beralasan' => [0, 'Populasi nol.', true]];
    }

    #[DataProvider('jepitDraf')]
    public function test_klaim_ra_draft_tetap_dibekukan_tanpa_memalsukan_versi_ra_sah(bool $jepitTerbaru): void
    {
        $this->periode();
        $this->plan->update(['status_alur' => 'draft']);
        if ($jepitTerbaru) {
            $context = JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'indikator_id', 'periode_mulai_id', 'unit_id', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'target', 'tipe_perhitungan']),
                'nomor_versi' => 2, 'menggantikan_id' => $this->context->id, 'alasan_koreksi' => 'Fixture jepit RA terbaru', 'rujukan_koreksi' => 'Pengujian']);
            $this->plan->update(['snapshot_draf_id' => $context->id]);
        }
        $activity = Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'nama' => 'Kegiatan periode lampau', 'tujuan' => 'Pengujian klaim', 'uraian_pelaksanaan' => 'Narasi saat pengajuan.', 'created_by' => $this->actor->id]);
        $claim = KlaimKegiatan::create(['rencana_aksi_id' => $this->plan->id, 'kegiatan_id' => $activity->id,
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->actor->id, 'created_at' => now()]);
        $this->actingAs($this->actor)->postJson('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75])->assertRedirect('/pengukuran');
        $version = KinerjaSnapshot::sole();
        $this->assertNull($version->rencana_aksi_versi_id);
        $this->assertSame($claim->id, $version->snapshot['klaim'][0]['id']);
        $this->assertSame('Narasi saat pengajuan.', $version->snapshot['klaim'][0]['kegiatan']['uraian_pelaksanaan']);
        $activity->update(['uraian_pelaksanaan' => 'Narasi sesudah pengajuan.']);
        $this->assertSame($version->snapshot, $version->fresh()->snapshot);
    }

    public static function jepitDraf(): array
    {
        return ['jepit sama' => [false], 'jepit sudah maju' => [true]];
    }

    #[DataProvider('periodeNormal')]
    public function test_gerbang_tetap_berlaku_di_luar_pengecualian_2026(int $tahun, bool $lampau): void
    {
        $this->periode($tahun, $lampau);
        $this->plan->update(['status_alur' => 'draft']);
        $this->buktiWajib();
        $this->actingAs($this->actor)->postJson('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['pengajuan', 'progres_kegiatan', 'kendala_masalah', 'strategi_tindak_lanjut']);
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame(['draft', 1], [$this->pengukuran->fresh()->status_alur, $this->pengukuran->fresh()->versi]);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    public static function periodeNormal(): array
    {
        return ['2026 bukan lampau saat aktivasi' => [2026, false], 'aktivasi terlambat 2027' => [2027, true]];
    }

    public function test_pic_tetap_ditolak_pada_periode_lampau(): void
    {
        $this->periode();
        $this->actor->roles()->detach();
        $this->actor->roles()->attach(Role::where('kode', 'pegawai')->sole()->id,
            ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->grant($this->actor, 'pengukuran:update');
        $this->actingAs($this->actor)->postJson('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75])
            ->assertUnprocessable()->assertJsonValidationErrors('versi');
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    public function test_perencanaan_tetap_ditolak_setelah_penutupan_jadwal(): void
    {
        $this->periode();
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->actingAs($this->actor)->postJson('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75])
            ->assertUnprocessable()->assertJsonValidationErrors('versi');
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }
}
