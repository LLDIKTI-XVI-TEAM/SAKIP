<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use App\Models\KinerjaSnapshot;
use App\Models\KlaimKegiatan;
use App\Models\Pengaturan;
use App\Models\RencanaAksiVersi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class CanonicalPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture,RefreshDatabase;

    public function test_review_preserves_operational_definition_from_the_submission_snapshot(): void
    {
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id,
            ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85])->assertSessionHasNoErrors();
        $this->pengukuran->indikator->update(['definisi_operasional' => 'Definisi master terbaru.']);
        $this->get('/verifikasi/'.$this->pengukuran->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukuran.penugasan_indikator.indikator_kinerja.definisi_operasional', 'Definisi operasional beku.'));
    }

    private function ratioContext(): array
    {
        $context = JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'indikator_id', 'periode_mulai_id', 'unit_id', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'target']),
            'nomor_versi' => 2, 'menggantikan_id' => $this->context->id, 'alasan_koreksi' => 'Konteks rasio sintetis', 'rujukan_koreksi' => 'Fixture', 'tipe_perhitungan' => 'rasio_persen']);
        $ids = [];
        foreach (['pembilang', 'penyebut'] as $index => $role) {
            $c = IndikatorKomponen::create(['indikator_id' => $this->pengukuran->indikator_id, 'kode' => $role, 'label' => $role, 'peran' => $role, 'bobot' => 1, 'urutan' => $index + 1, 'created_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now()]);
            JadwalSnapshotKomponen::create(['jadwal_snapshot_id' => $context->id, 'komponen_id' => $c->id, 'kode' => $role, 'label' => $role, 'peran' => $role, 'bobot' => 1, 'urutan' => $index + 1]);
            $ids[] = $c->id;
        }
        $this->plan->update(['jadwal_snapshot_id' => $context->id]);
        RencanaAksiVersi::create(['rencana_aksi_id' => $this->plan->id, 'jadwal_snapshot_id' => $context->id, 'nomor' => 2, 'diajukan_by' => $this->actor->id, 'diajukan_at' => now(),
            'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'], 'snapshot' => ['target_periode' => [['periode_id' => $this->pengukuran->periode_id, 'nilai' => 80, 'status_perhitungan' => 'terhitung', 'komponen' => [['komponen_id' => $ids[0], 'nilai' => 8], ['komponen_id' => $ids[1], 'nilai' => 10]]]]],
            'disahkan_by' => $this->actor->id, 'disahkan_at' => now()]);
        $this->pengukuran->update(['jadwal_snapshot_id' => $context->id, 'sumber_nilai' => 'komponen']);
        $this->pengukuran->refresh();

        return $ids;
    }

    public function test_normal_ratio_rejects_missing_components_but_accepts_explained_zero_denominator(): void
    {
        [$n,$t] = $this->ratioContext();
        $this->actingAs($this->actor)->get('/pengukuran')->assertOk()
            ->assertInertia(fn ($page) => $page->where('pengukurans.0.target', 80));
        $url = '/pengukuran/'.$this->pengukuran->id;
        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'ajukan', 'komponen' => [['komponen_id' => $n, 'nilai' => 5]]])->assertSessionHasErrors('pengajuan');
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'ajukan', 'komponen' => [['komponen_id' => $n, 'nilai' => 5], ['komponen_id' => $t, 'nilai' => 0]], 'alasan_tidak_dapat_dihitung' => 'Tidak ada populasi faktual.'])->assertSessionHasNoErrors()->assertRedirect('/pengukuran');
        $this->assertNull($this->pengukuran->fresh()->nilai);
        $this->assertSame('tidak_dapat_dihitung', $this->pengukuran->fresh()->status_perhitungan);
        $snapshot = KinerjaSnapshot::firstOrFail()->snapshot;
        $this->assertNull($snapshot['nilai']);
        $this->assertSame(80.0, (float) $snapshot['target']);
        $this->get('/verifikasi/'.$this->pengukuran->id)->assertInertia(fn ($page) => $page
            ->where('pengukuran.target', 80)->where('pengukuran.target_pk', 70));
    }

    public function test_file_waiver_preserves_required_nonfile_modes_and_freezes_requirements(): void
    {
        $requirement = JenisBerkas::create(['nama' => 'Bukti wajib', 'tahap' => 'pengukuran', 'wajib' => true, 'izinkan_file' => true, 'izinkan_teks' => true, 'semua_mode_wajib' => true, 'created_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now()]);
        Pengaturan::create(['kunci' => 'berkas.unggahan_aktif', 'nilai' => 'false', 'tipe' => 'boolean', 'grup' => 'berkas', 'updated_at' => now()]);
        $url = '/pengukuran/'.$this->pengukuran->id;
        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85])->assertSessionHasErrors('pengajuan');
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'berkas.tandai_tidak_dapat_dipenuhi']);
        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85, 'bukti' => ['jenis_berkas_id' => $requirement->id, 'mode' => 'teks', 'isi_teks' => 'Bukti sintetis memenuhi persyaratan.']])->assertSessionHasNoErrors();
        $requirement->update(['izinkan_teks' => false, 'semua_mode_wajib' => false]);
        $version = KinerjaSnapshot::firstOrFail();
        $waiver = AuditLog::where('tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')->sole();
        $this->assertSame($this->actor->id, $waiver->actor_id);
        $this->assertSame($this->pengukuran->id, $waiver->objek_id);
        $this->assertSame($version->id, $waiver->nilai_baru['versi_pengajuan_id']);
        $this->assertSame($requirement->id, $waiver->nilai_baru['pengecualian'][0]['jenis_berkas_id']);
        $this->assertSame(['file'], $waiver->nilai_baru['pengecualian'][0]['mode']);
        $this->assertNotEmpty($waiver->alasan);
        $this->assertNotEmpty($waiver->dasar_izin);
        $this->assertSame(['file'], $version->snapshot['persyaratan_bukti'][0]['pemenuhan']['mode_dikecualikan']);
        $this->assertTrue($version->snapshot['persyaratan_bukti'][0]['izinkan_teks']);
        $this->actingAs($this->actor)->post('/verifikasi/'.$this->pengukuran->id.'/verifikasi', ['versi' => 2])->assertSessionHasNoErrors();
    }

    public function test_required_note_and_component_overflow_are_validation_failures(): void
    {
        $this->pengukuran->indikator->update(['wajib_catatan' => true]);
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85])->assertSessionHasErrors('pengajuan');
        [$n,$t] = $this->ratioContext();
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'draft', 'komponen' => [['komponen_id' => $n, 'nilai' => 1e17], ['komponen_id' => $t, 'nilai' => 1]]])->assertSessionHasErrors('nilai');
        $this->assertSame(1, $this->pengukuran->fresh()->versi);
        $this->assertDatabaseCount('pengukuran_komponen', 0);
    }

    public function test_submission_freezes_claimed_activity_narrative_and_evidence(): void
    {
        $activity = Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'nama' => 'Kegiatan sintetis', 'tujuan' => 'Pengujian snapshot', 'uraian_pelaksanaan' => 'Narasi semula', 'created_by' => $this->actor->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $this->plan->id, 'kegiatan_id' => $activity->id, 'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->actor->id, 'created_at' => now()]);
        $old = BuktiDukung::create(['berkasable_type' => 'kegiatan', 'berkasable_id' => $activity->id, 'mode' => 'teks', 'isi_teks' => 'Bukti salah', 'uploaded_by' => $this->actor->id, 'created_at' => now()]);
        $replacement = BuktiDukung::create([...$old->only(['berkasable_type', 'berkasable_id', 'mode', 'uploaded_by']),
            'menggantikan_id' => $old->id, 'alasan_koreksi' => 'Salah periode.', 'isi_teks' => 'Bukti koreksi', 'created_at' => now()]);
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85])->assertSessionHasNoErrors();
        $activity->update(['uraian_pelaksanaan' => 'Narasi berubah']);
        $snapshot = KinerjaSnapshot::firstOrFail()->snapshot;
        $this->assertSame('Narasi semula', $snapshot['klaim'][0]['kegiatan']['uraian_pelaksanaan']);
        $this->assertSame([$replacement->id], array_column($snapshot['klaim'][0]['kegiatan']['bukti_dukungs'], 'id'));
        $this->assertNull($old->fresh()->dihapus_pada);
        $this->get('/verifikasi/'.$this->pengukuran->id)->assertInertia(fn ($page) => $page
            ->where('pengukuran.klaim.0.kegiatan.uraian_pelaksanaan', 'Narasi semula')
            ->where('pengukuran.klaim.0.kegiatan.bukti_dukungs.0.isi_teks', 'Bukti koreksi')
            ->missing('pengukuran.klaim.0.kegiatan.bukti_dukungs.0.path'));
    }

    public function test_download_uses_the_file_frozen_at_submission(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('berkas/semula.txt', 'Isi semula');
        Storage::disk('local')->put('berkas/berubah.txt', 'Isi berubah');
        $evidence = BuktiDukung::create(['berkasable_type' => 'pengukuran', 'berkasable_id' => $this->pengukuran->id, 'mode' => 'file', 'nama_asli' => 'bukti.txt', 'path' => 'berkas/semula.txt', 'mime' => 'text/plain', 'ukuran_bytes' => 10, 'uploaded_by' => $this->actor->id, 'created_at' => now()]);
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85])->assertSessionHasNoErrors();
        $evidence->update(['path' => 'berkas/berubah.txt']);
        $this->actingAs($this->actor)->get(route('pengukuran.bukti', ['id' => $this->pengukuran->id, 'buktiId' => $evidence->id]))->assertOk()->assertStreamedContent('Isi semula');
    }
}
