<?php

namespace Tests\Feature;

use App\Models\BuktiDukung;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Permission;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class ClaimedActivityEvidenceTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_frozen_activity_file_requires_parent_access_and_honors_evidence_deny(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('kegiatan/frozen.txt', 'Bukti beku');
        $activity = Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'nama' => 'Kegiatan uji', 'tujuan' => 'Verifikasi izin', 'created_by' => $this->actor->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $this->plan->id, 'kegiatan_id' => $activity->id, 'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->actor->id, 'created_at' => now()]);
        $file = BuktiDukung::create(['berkasable_type' => 'kegiatan', 'berkasable_id' => $activity->id, 'mode' => 'file',
            'nama_asli' => 'frozen.txt', 'path' => 'kegiatan/frozen.txt', 'mime' => 'text/plain', 'ukuran_bytes' => 10, 'uploaded_by' => $this->actor->id, 'created_at' => now()]);
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85])->assertSessionHasNoErrors();
        $file->update(['path' => 'kegiatan/master-berubah.txt']);
        $url = '/pengukuran/'.$this->pengukuran->id.'/bukti-klaim/'.$file->id;
        $detail = '/pengukuran/'.$this->pengukuran->id.'/edit';
        $this->get($url)->assertOk()->assertStreamedContent('Bukti beku');
        $this->get('/pengukuran/'.$this->pengukuran->id.'/bukti-klaim/'.Str::uuid())->assertNotFound();

        // Allow berkas global tidak mengalahkan deny induk kegiatan.
        UserPermissionDeny::create(['user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'kegiatan:read')->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Uji deny induk.', 'ditetapkan_oleh' => $this->actor->id]);
        $this->get($url)->assertForbidden();
        $this->get($detail)->assertInertia(fn ($page) => $page->where('pengukuran.can.viewClaims', false)->has('pengukuran.klaim', 0));

        $reader = $this->userWithRole('pegawai');
        $this->actingAs($reader)->get($url)->assertForbidden();
        UserPermissionGrant::create(['user_id' => $reader->id, 'permission_id' => Permission::where('kode', 'kegiatan:read')->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Uji akses scoped.', 'diberikan_oleh' => $this->actor->id]);
        $this->get($url)->assertOk()->assertStreamedContent('Bukti beku');
        $this->get($detail)->assertInertia(fn ($page) => $page->where('pengukuran.can.claimEvidence', true)
            ->where('pengukuran.klaim.0.kegiatan.bukti_dukungs.0.download_url', url($url))
            ->missing('pengukuran.klaim.0.kegiatan.bukti_dukungs.0.path'));

        UserPermissionDeny::create(['user_id' => $reader->id, 'permission_id' => Permission::where('kode', 'berkas:read')->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Uji deny berkas.', 'ditetapkan_oleh' => $this->actor->id]);
        $this->get($url)->assertForbidden();
        $this->get($detail)->assertInertia(fn ($page) => $page->where('pengukuran.can.viewClaims', true)
            ->where('pengukuran.can.claimEvidence', false)->has('pengukuran.klaim', 1)->has('pengukuran.klaim.0.kegiatan.bukti_dukungs', 0));
    }
}
