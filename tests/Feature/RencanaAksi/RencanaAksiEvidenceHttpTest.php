<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

class RencanaAksiEvidenceHttpTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
    }

    /**
     * TEST-1 / AC-1: Given persyaratan tahap rencana_aksi, When bukti mode valid disimpan, Then berkas terhubung ke RA dan jenis persyaratan yang tepat.
     */
    public function test_stores_valid_evidence_and_links_to_rencana_aksi_and_requirement(): void
    {
        Storage::fake('local');

        $jb = $this->createJenisBerkas([
            'nama' => 'KAK Kegiatan RA',
            'wajib' => true,
            'izinkan_file' => true,
            'izinkan_tautan' => true,
            'izinkan_teks' => true,
        ]);

        $file = UploadedFile::fake()->create('kak-ra.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->actor)->post(
            route('rencana-aksi.bukti.store', $this->rencanaAksi->id),
            [
                'jenis_berkas_id' => $jb->id,
                'mode' => 'file',
                'file' => $file,
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $berkas = Berkas::where('berkasable_id', $this->rencanaAksi->id)
            ->where('berkasable_type', 'rencana_aksi')
            ->first();

        $this->assertNotNull($berkas);
        $this->assertSame($jb->id, $berkas->jenis_berkas_id);
        $this->assertSame('file', $berkas->mode);
        $this->assertSame('kak-ra.pdf', $berkas->nama_asli);
        $this->assertNotNull($berkas->path);
        Storage::disk('local')->assertExists($berkas->path);

        // Audit log tercatat
        $audit = AuditLog::where('tindakan', 'berkas.unggah')
            ->where('objek_id', $berkas->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame($this->actor->id, $audit->actor_id);
    }

    /**
     * TEST-2 / AC-2: Given mode tidak diizinkan, When bukti dikirim, Then server menolak.
     */
    public function test_rejects_evidence_when_mode_is_not_allowed_by_requirement(): void
    {
        $jb = $this->createJenisBerkas([
            'nama' => 'Dokumen Teks Saja',
            'izinkan_file' => false,
            'izinkan_tautan' => false,
            'izinkan_teks' => true,
        ]);

        // Mencoba mengirimkan mode tautan padahal hanya teks yang diizinkan
        $response = $this->actingAs($this->actor)->post(
            route('rencana-aksi.bukti.store', $this->rencanaAksi->id),
            [
                'jenis_berkas_id' => $jb->id,
                'mode' => 'tautan',
                'tautan' => 'https://lldikti16.kemdikbud.go.id/dokumen',
            ]
        );

        $response->assertSessionHasErrors('mode');
        $this->assertDatabaseMissing('berkas', [
            'berkasable_id' => $this->rencanaAksi->id,
            'jenis_berkas_id' => $jb->id,
        ]);
    }

    /**
     * TEST-4 / AC-4: Given deny/capability induk tidak mengizinkan upload, When request dipanggil langsung, Then 403.
     */
    public function test_denies_evidence_upload_when_user_has_explicit_deny_or_lacks_capability(): void
    {
        // Pengguna pegawai biasa tanpa grant unit
        $unauthorizedUser = $this->createUserWithRole('pegawai');

        $responseUnauthorized = $this->actingAs($unauthorizedUser)->post(
            route('rencana-aksi.bukti.store', $this->rencanaAksi->id),
            [
                'mode' => 'teks',
                'isi_teks' => 'Keterangan tanpa izin.',
            ]
        );
        $responseUnauthorized->assertForbidden();

        // Pengguna memiliki peran tetapi dikenakan explicit deny pada berkas:upload
        $this->denyPermission($this->actor, 'berkas:upload');

        $responseDenied = $this->actingAs($this->actor)->post(
            route('rencana-aksi.bukti.store', $this->rencanaAksi->id),
            [
                'mode' => 'teks',
                'isi_teks' => 'Keterangan bukti uji deny.',
            ]
        );
        $responseDenied->assertForbidden();

        // Audit penolakan tercatat
        $auditDenial = AuditLog::where('tindakan', 'berkas.unggah_ditolak')
            ->where('objek_id', $this->rencanaAksi->id)
            ->first();
        $this->assertNotNull($auditDenial);
    }

    /**
     * TEST-5 / AC-5: Given bukti sudah dibekukan dalam versi pengajuan, Then perubahan setelahnya tidak mengubah snapshot versi tersebut.
     */
    public function test_changes_after_submission_do_not_alter_frozen_evidence_snapshot(): void
    {
        $jb = $this->createJenisBerkas();
        $buktiAwal = $this->createBuktiDukung([
            'jenis_berkas_id' => $jb->id,
            'mode' => 'teks',
            'isi_teks' => 'Bukti teks versi 1.',
        ]);

        // Simulasi pembekuan snapshot pada rencana_aksi_versi
        $frozenSnapshot = [
            'bukti_dukungs' => [
                [
                    'id' => $buktiAwal->id,
                    'jenis_berkas_id' => $jb->id,
                    'mode' => 'teks',
                    'isi_teks' => 'Bukti teks versi 1.',
                ],
            ],
        ];

        $versi = RencanaAksiVersi::create([
            'rencana_aksi_id' => $this->rencanaAksi->id,
            'jadwal_snapshot_id' => $this->snapshot->id,
            'nomor' => 1,
            'diajukan_by' => $this->actor->id,
            'diajukan_at' => now(),
            'jalur_pengajuan' => 'perencanaan',
            'dasar_izin_pengajuan' => ['sumber' => 'uji_pengajuan'],
            'snapshot' => $frozenSnapshot,
        ]);

        // Melakukan penambahan bukti baru dan penggantian pada tabel berkas
        $this->actingAs($this->actor)->post(
            route('rencana-aksi.bukti.store', $this->rencanaAksi->id),
            [
                'jenis_berkas_id' => $jb->id,
                'mode' => 'teks',
                'isi_teks' => 'Bukti koreksi versi baru.',
                'menggantikan_id' => $buktiAwal->id,
                'alasan_koreksi' => 'Penyempurnaan dokumen rencana aksi.',
            ]
        )->assertSessionHasNoErrors();

        // Bukti baru tersimpan di tabel berkas
        $this->assertDatabaseHas('berkas', [
            'menggantikan_id' => $buktiAwal->id,
            'isi_teks' => 'Bukti koreksi versi baru.',
        ]);

        // Snapshot pada baris rencana_aksi_versi tidak berubah sama sekali (tetap immutable)
        $this->assertSame($frozenSnapshot, $versi->fresh()->snapshot);
    }

    /**
     * Immutability Guard: Given Rencana Aksi sudah disahkan, When bukti diunggah atau dihapus, Then ditolak.
     */
    public function test_rejects_evidence_mutation_when_rencana_aksi_is_disahkan(): void
    {
        $this->rencanaAksi->update([
            'status_alur' => RencanaAksi::STATUS_DISAHKAN,
            'disahkan_at' => now(),
            'disahkan_by' => $this->actor->id,
        ]);

        $bukti = $this->createBuktiDukung();

        // Percobaan upload pada RA yang disahkan
        $responseUpload = $this->actingAs($this->actor)->post(
            route('rencana-aksi.bukti.store', $this->rencanaAksi->id),
            [
                'mode' => 'teks',
                'isi_teks' => 'Percobaan upload pada RA disahkan.',
            ]
        );
        $responseUpload->assertForbidden();

        // Percobaan hapus pada RA yang disahkan
        $responseDelete = $this->actingAs($this->actor)->delete(
            route('rencana-aksi.bukti.destroy', [
                'rencana_aksi' => $this->rencanaAksi->id,
                'bukti' => $bukti->id,
            ]),
            ['alasan' => 'Hapus berkas RA yang sudah disahkan.']
        );
        $responseDelete->assertForbidden();

        $this->assertNull($bukti->fresh()->dihapus_pada);
    }

    /**
     * Download Stream Test: File dapat diunduh dari storage privat melalui route berizin.
     */
    public function test_downloads_evidence_file_from_private_storage(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('berkas/test-dokumen.pdf', 'Konten PDF Rahasia');

        $berkas = $this->createBuktiDukung([
            'mode' => 'file',
            'nama_asli' => 'test-dokumen.pdf',
            'path' => 'berkas/test-dokumen.pdf',
            'mime' => 'application/pdf',
            'ukuran_bytes' => 19,
        ]);

        $response = $this->actingAs($this->actor)->get(
            route('rencana-aksi.bukti.download', [
                'rencana_aksi' => $this->rencanaAksi->id,
                'bukti' => $berkas->id,
            ])
        );

        $response->assertOk();
        $this->assertSame('Konten PDF Rahasia', $response->streamedContent());
    }

    /**
     * Soft Delete Test: Penghapusan bukti dukung berhasil dengan pencatatan audit log.
     */
    public function test_soft_deletes_evidence_with_audit_log(): void
    {
        $berkas = $this->createBuktiDukung([
            'mode' => 'tautan',
            'tautan' => 'https://lldikti16.kemdikbud.go.id/dokumen-lama',
        ]);

        $response = $this->actingAs($this->actor)->delete(
            route('rencana-aksi.bukti.destroy', [
                'rencana_aksi' => $this->rencanaAksi->id,
                'bukti' => $berkas->id,
            ]),
            ['alasan' => 'Tautan sudah tidak relevan dengan target rencana aksi.']
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertNotNull($berkas->fresh()->dihapus_pada);
        $this->assertSame($this->actor->id, $berkas->fresh()->dihapus_oleh);

        $audit = AuditLog::where('tindakan', 'berkas.hapus')
            ->where('objek_id', $berkas->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('Tautan sudah tidak relevan dengan target rencana aksi.', $audit->alasan);
    }
}
