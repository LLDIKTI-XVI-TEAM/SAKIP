<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\KinerjaSnapshot;
use App\Models\Pengaturan;
use App\Models\Permission;
use App\Models\UserPermissionDeny;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class EvidenceCorrectionTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_resubmission_selects_replacement_without_changing_previous_evidence_or_version(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('berkas/original.txt', 'Bukti awal');
        $old = $this->evidence(['mode' => 'file', 'isi_teks' => null, 'path' => 'berkas/original.txt', 'nama_asli' => 'original.txt', 'mime' => 'text/plain', 'ukuran_bytes' => 10]);
        $url = '/pengukuran/'.$this->pengukuran->id;
        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85])->assertSessionHasNoErrors();
        $first = KinerjaSnapshot::sole();
        $snapshot = $first->snapshot;
        $this->post('/verifikasi/'.$this->pengukuran->id.'/kembalikan', ['versi' => 2, 'catatan' => 'Perbaiki bukti.'])->assertSessionHasNoErrors();
        $this->post($url, ['versi' => 3, 'action' => 'ajukan', 'nilai' => 85, 'bukti' => [
            'mode' => 'teks', 'isi_teks' => 'Bukti koreksi', 'menggantikan_id' => $old->id, 'alasan_koreksi' => 'Lampiran semula tidak sesuai.',
        ]])->assertSessionHasNoErrors();
        $replacement = BuktiDukung::where('menggantikan_id', $old->id)->sole();
        $latest = $this->pengukuran->fresh()->latestVersion;
        $this->assertSame([$replacement->id], array_column($latest->snapshot['bukti_dukungs'], 'id'));
        $this->assertSame($old->id, $latest->snapshot['bukti_dukungs'][0]['menggantikan_id']);
        $this->assertSame($snapshot, $first->fresh()->snapshot);
        $this->assertNull($old->fresh()->dihapus_pada);
        Storage::disk('local')->assertExists('berkas/original.txt');
        $this->assertSame('Bukti awal', Storage::disk('local')->get('berkas/original.txt'));
        $this->get($url.'/edit')->assertInertia(fn ($page) => $page->has('pengukuran.bukti_dukungs', 1)
            ->where('pengukuran.bukti_dukungs.0.menggantikan_id', $old->id));
        $this->get(route('pengukuran.bukti', ['id' => $this->pengukuran->id, 'buktiId' => $old->id]))->assertOk()->assertStreamedContent('Bukti awal');
        $audit = AuditLog::where('tindakan', 'pengukuran.ajukan')->orderByDesc('waktu')->get()->firstWhere('nilai_baru.versi', 4);
        $this->assertContains($old->id, array_column($audit->nilai_baru['bukti_dukungs'], 'menggantikan_id'));
        $this->assertStringNotContainsString('Bukti koreksi', json_encode($audit->nilai_baru));
        UserPermissionDeny::create(['user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'berkas:read')->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Deny unduhan historis sintetis.', 'ditetapkan_oleh' => $this->actor->id]);
        $this->get(route('pengukuran.bukti', ['id' => $this->pengukuran->id, 'buktiId' => $old->id]))->assertForbidden();
    }

    public function test_replacement_requires_reason_and_current_evidence_of_the_same_parent_and_requirement(): void
    {
        $current = $this->evidence();
        $wrongParent = $this->evidence(['berkasable_id' => (string) Str::uuid()]);
        $wrongType = $this->evidence(['berkasable_type' => 'kegiatan']);
        $deleted = $this->evidence(['dihapus_pada' => now(), 'dihapus_oleh' => $this->actor->id]);
        $superseded = $this->evidence();
        $this->evidence(['menggantikan_id' => $superseded->id, 'alasan_koreksi' => 'Sudah dikoreksi.']);
        $requirement = JenisBerkas::create(['nama' => 'Persyaratan lain', 'tahap' => 'pengukuran', 'izinkan_teks' => true, 'created_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $differentRequirement = $this->evidence(['jenis_berkas_id' => $requirement->id]);
        $payload = ['versi' => 1, 'action' => 'draft', 'nilai' => 85, 'bukti' => ['mode' => 'teks', 'isi_teks' => 'Koreksi', 'menggantikan_id' => $current->id]];
        $url = '/pengukuran/'.$this->pengukuran->id;
        $this->actingAs($this->actor)->post($url, $payload)->assertSessionHasErrors('bukti.alasan_koreksi');
        $payload['bukti']['alasan_koreksi'] = 'Koreksi sintetis.';
        foreach ([$wrongParent->id, $wrongType->id, $deleted->id, $superseded->id, $differentRequirement->id, (string) Str::uuid()] as $id) {
            $payload['bukti']['menggantikan_id'] = $id;
            $this->post($url, $payload)->assertSessionHasErrors('bukti.menggantikan_id');
        }
        $this->assertSame(1, $this->pengukuran->fresh()->versi);
        $this->assertDatabaseCount('berkas', 7);
    }

    public function test_failed_waiver_audit_rolls_back_submission_and_evidence(): void
    {
        JenisBerkas::create(['nama' => 'Bukti file wajib', 'tahap' => 'pengukuran', 'wajib' => true, 'izinkan_file' => true, 'created_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now()]);
        Pengaturan::create(['kunci' => 'berkas.unggahan_aktif', 'nilai' => 'false', 'tipe' => 'boolean', 'grup' => 'berkas', 'updated_at' => now()]);
        AuditLog::creating(function ($audit): void {
            if ($audit->tindakan === 'berkas.tandai_tidak_dapat_dipenuhi') {
                throw new \RuntimeException('Kegagalan audit sintetis');
            }
        });
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85,
            'bukti' => ['mode' => 'teks', 'isi_teks' => 'Lampiran bebas']])->assertStatus(500);
        $this->assertSame('draft', $this->pengukuran->fresh()->status_alur);
        $this->assertSame(1, $this->pengukuran->fresh()->versi);
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertDatabaseCount('berkas', 0);
    }

    public function test_superseded_mode_does_not_satisfy_required_evidence(): void
    {
        $requirement = JenisBerkas::create(['nama' => 'Tautan dan teks', 'tahap' => 'pengukuran', 'wajib' => true,
            'izinkan_file' => false, 'izinkan_tautan' => true, 'izinkan_teks' => true, 'semua_mode_wajib' => true, 'created_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $old = $this->evidence(['jenis_berkas_id' => $requirement->id, 'mode' => 'tautan', 'isi_teks' => null, 'tautan' => 'https://example.test/bukti']);
        $url = '/pengukuran/'.$this->pengukuran->id;
        $this->actingAs($this->actor)->post($url, ['versi' => 1, 'action' => 'draft', 'nilai' => 85,
            'bukti' => ['jenis_berkas_id' => $requirement->id, 'mode' => 'teks', 'isi_teks' => 'Bukti terbaru',
                'menggantikan_id' => $old->id, 'alasan_koreksi' => 'Tautan semula tidak relevan.']])->assertSessionHasNoErrors();
        $this->post($url, ['versi' => 2, 'action' => 'ajukan', 'nilai' => 85])->assertSessionHasErrors('pengajuan');
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->get($url.'/edit')->assertInertia(fn ($page) => $page->has('pengukuran.bukti_dukungs', 1)
            ->where('pengukuran.persyaratan_bukti.0.pemenuhan.mode_kurang', ['tautan']));
    }

    private function evidence(array $attributes = []): BuktiDukung
    {
        return BuktiDukung::create([...['berkasable_type' => 'pengukuran', 'berkasable_id' => $this->pengukuran->id,
            'mode' => 'teks', 'isi_teks' => 'Bukti semula', 'uploaded_by' => $this->actor->id, 'created_at' => now()], ...$attributes]);
    }
}
