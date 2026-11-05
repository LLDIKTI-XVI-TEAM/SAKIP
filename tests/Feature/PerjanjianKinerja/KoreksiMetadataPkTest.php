<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Actions\Jadwal\KoreksiSnapshot;
use App\Actions\PerjanjianKinerja\ShowPerjanjianKinerja;
use App\Actions\PerjanjianKinerja\UpdatePerjanjianKinerja;
use App\Models\KinerjaSnapshot;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\StatusCapaian;
use App\Models\TargetKinerja;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class KoreksiMetadataPkTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $changes = []): array
    {
        $pk = RenstraPk::findOrFail($this->jadwal->renstra_pk_id);

        return [...$pk->only(['nomor_pk']), 'tanggal_pk' => $pk->tanggal_pk->toDateString(),
            'expected_updated_at' => $pk->updated_at->toISOString(), 'alasan' => 'Koreksi sesuai dokumen resmi', ...$changes];
    }

    public static function alasanRujukan(): array
    {
        return ['alasan kosong' => ['alasan', ''], 'rujukan kosong' => ['rujukan', ''],
            'rujukan spasi' => ['rujukan', '  '], 'rujukan terlalu panjang' => ['rujukan', str_repeat('x', 256)],
            'rujukan kontrol' => ['rujukan', "Surat\0resmi"]];
    }

    #[DataProvider('alasanRujukan')]
    public function test_http_menolak_dan_mengaudit_koreksi_tidak_valid(string $field, string $value): void
    {
        $pk = RenstraPk::findOrFail($this->jadwal->renstra_pk_id);
        $this->actingAs($this->actor)->putJson('/perjanjian-kinerja/'.$pk->id,
            $this->payload(['nomor_pk' => 'PK-KOREKSI', 'rujukan' => 'Dokumen 2026', $field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame('PK-UJI', $pk->fresh()->nomor_pk);
        $audit = DB::table('audit_log')->where('tindakan', 'renstra_pk.ubah_ditolak')->sole();
        $this->assertNotEmpty($audit->dasar_izin);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'renstra_pk.ubah']);
    }

    public function test_action_memeriksa_rujukan_setelah_lock_dan_penolakan_tetap_teraudit(): void
    {
        $pk = RenstraPk::findOrFail($this->jadwal->renstra_pk_id);
        try {
            app(UpdatePerjanjianKinerja::class)->handle($pk, $this->payload(['nomor_pk' => 'PK-BARU']), 'Koreksi resmi', $this->actor);
            $this->fail('Koreksi metadata tanpa rujukan harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rujukan', $e->errors());
        }
        $this->assertSame('PK-UJI', $pk->fresh()->nomor_pk);
        $this->assertSame(1, DB::table('audit_log')->where('tindakan', 'renstra_pk.ubah_ditolak')->count());
    }

    public function test_dua_koreksi_memiliki_rujukan_per_peristiwa_dan_nomor_boleh_dikosongkan(): void
    {
        $pk = RenstraPk::findOrFail($this->jadwal->renstra_pk_id);
        $sebelumnya = 'PK-UJI';
        foreach (['Surat pertama' => 'PK-PERTAMA', 'PK 2026 tanpa nomor, tanggal 21 April' => null] as $rujukan => $nomor) {
            $this->actingAs($this->actor)->putJson('/perjanjian-kinerja/'.$pk->id,
                $this->payload(['nomor_pk' => $nomor, 'rujukan' => $rujukan]))->assertRedirect();
            $audit = DB::table('audit_log')->where('tindakan', 'renstra_pk.ubah')->get()
                ->first(fn ($row) => (json_decode($row->nilai_baru, true)['rujukan'] ?? null) === $rujukan);
            $this->assertNotNull($audit);
            $this->assertSame($nomor, json_decode($audit->nilai_baru, true)['nomor_pk']);
            $this->assertSame($sebelumnya, json_decode($audit->nilai_lama, true)['nomor_pk']);
            $sebelumnya = $nomor;
            $this->assertNotEmpty($audit->dasar_izin);
            $this->assertSame('Koreksi sesuai dokumen resmi', $audit->alasan);
        }
        $this->assertSame(2, DB::table('audit_log')->where('tindakan', 'renstra_pk.ubah')->count());
        $this->assertNull($pk->fresh()->nomor_pk);
    }

    public function test_audit_penolakan_memakai_dasar_izin_setelah_lock(): void
    {
        $pk = RenstraPk::findOrFail($this->jadwal->renstra_pk_id);
        $actorId = $this->actor->id;
        $perencanaan = Role::where('kode', 'perencanaan')->value('id');
        $injected = false;
        $basisTerkunci = [];
        User::retrieved(function ($user) use ($actorId, $perencanaan, &$injected, &$basisTerkunci) {
            if (! $injected && $user->id === $actorId) {
                $injected = true;
                DB::table('user_roles')->where('user_id', $actorId)->update(['role_id' => $perencanaan]);
                $basisTerkunci = app(PermissionResolver::class)->resolve($user, 'pk:update')->toAuditBasis();
            }
        });
        try {
            app(UpdatePerjanjianKinerja::class)->handle($pk, $this->payload(['nomor_pk' => 'PK-BARU']), 'Koreksi resmi', $this->actor);
            $this->fail('Rujukan kosong harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rujukan', $e->errors());
        }
        $audit = DB::table('audit_log')->where('tindakan', 'renstra_pk.ubah_ditolak')->sole();
        $this->assertTrue($injected);
        $this->assertNotEmpty($basisTerkunci);
        $this->assertEquals($basisTerkunci, json_decode($audit->dasar_izin, true));
    }

    public function test_lampiran_saja_dan_tanpa_perubahan_tidak_memerlukan_rujukan(): void
    {
        $pk = RenstraPk::findOrFail($this->jadwal->renstra_pk_id);
        $this->actingAs($this->actor)->putJson('/perjanjian-kinerja/'.$pk->id, $this->payload([
            'lampiran' => [['mode' => 'tautan', 'tautan' => 'https://contoh.go.id/pk', 'keterangan' => 'PK perubahan anggaran']],
        ]))->assertRedirect();
        $this->assertDatabaseHas('berkas', ['berkasable_id' => $pk->id, 'keterangan' => 'PK perubahan anggaran']);
        $this->putJson('/perjanjian-kinerja/'.$pk->id, $this->payload())->assertRedirect();
        $this->assertSame(2, DB::table('audit_log')->where('tindakan', 'renstra_pk.ubah')->count());
    }

    public function test_jadwal_ditutup_dan_pernah_aktif_tetap_mewajibkan_rujukan_untuk_tanggal_berubah(): void
    {
        foreach (['ditutup', 'draft'] as $status) {
            $this->jadwal->update(['status' => $status]);
            $this->actingAs($this->actor)->putJson('/perjanjian-kinerja/'.$this->jadwal->renstra_pk_id,
                $this->payload(['tanggal_pk' => '2026-01-02']))->assertUnprocessable()->assertJsonValidationErrors('rujukan');
        }
    }

    public function test_nomor_setelah_normalisasi_tidak_memerlukan_rujukan_dan_tautan_snapshot_mengikuti_izin(): void
    {
        $pk = RenstraPk::findOrFail($this->jadwal->renstra_pk_id);
        app(UpdatePerjanjianKinerja::class)->handle($pk, $this->payload(['nomor_pk' => ' PK-UJI ']), 'Pemeriksaan data', $this->actor);
        $this->assertSame('PK-UJI', $pk->fresh()->nomor_pk);
        $this->assertSame('/jadwal/'.$this->jadwal->id.'/snapshot', app(ShowPerjanjianKinerja::class)->handle($this->actor, $pk)['snapshot_url']);
        $this->assertNull(app(ShowPerjanjianKinerja::class)->handle($this->userWithRole('pegawai'), $pk)['snapshot_url']);
    }

    public function test_sebelum_dipakai_tanpa_alasan_dan_rujukan_diterima(): void
    {
        $pk = RenstraPk::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2027,
            'nomor_pk' => null, 'tanggal_pk' => '2027-01-01', 'created_by' => $this->actor->id]);
        $this->actingAs($this->actor)->putJson('/perjanjian-kinerja/'.$pk->id, [
            'tanggal_pk' => '2027-01-02', 'expected_updated_at' => $pk->updated_at->toISOString(),
        ])->assertRedirect();
        $this->assertDatabaseHas('audit_log', ['objek_id' => $pk->id, 'tindakan' => 'renstra_pk.ubah',
            'alasan' => UpdatePerjanjianKinerja::ALASAN_BAKU]);
    }

    public function test_koreksi_metadata_dan_snapshot_mempertahankan_hasil_resmi_lama(): void
    {
        $this->pengukuran->update(['status_alur' => 'disahkan']);
        $versi = KinerjaSnapshot::create(['pengukuran_id' => $this->pengukuran->id,
            'rencana_aksi_versi_id' => $this->planVersion->id, 'jadwal_snapshot_id' => $this->context->id,
            'nomor' => 1, 'diajukan_by' => $this->actor->id, 'diajukan_at' => now(),
            'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'],
            'disahkan_by' => $this->actor->id, 'disahkan_at' => now(), 'snapshot' => ['nilai' => '75', 'target_pk' => '70']]);
        StatusCapaian::create(['pengukuran_id' => $this->pengukuran->id, 'pengukuran_versi_id' => $versi->id,
            'status' => 'tercapai', 'sumber' => 'manual', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $tables = ['rencana_aksi_versi', 'pengukuran_versi', 'status_capaian', 'pengukuran_kinerjas'];
        $fingerprint = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $lama = $fingerprint();
        $snapshot = DB::table('jadwal_snapshot')->where('id', $this->context->id)->first();
        $pk = RenstraPk::findOrFail($this->jadwal->renstra_pk_id);
        $this->actingAs($this->actor)->putJson('/perjanjian-kinerja/'.$pk->id, $this->payload([
            'nomor_pk' => 'PK-KOREKSI', 'tanggal_pk' => '2026-01-02', 'rujukan' => 'PK resmi tanggal 2 Januari',
        ]))->assertRedirect();
        $this->assertEquals($snapshot, DB::table('jadwal_snapshot')->where('id', $this->context->id)->first());
        $this->assertSame($lama, $fingerprint());
        TargetKinerja::create(['indikator_kinerja_id' => $this->context->indikator_id, 'tahun' => 2026,
            'target_tahunan' => 80, 'updated_by' => $this->actor->id]);
        $baru = app(KoreksiSnapshot::class)->handle($this->actor, $this->jadwal->id, $this->context->id, [
            'periode_mulai_id' => $this->context->periode_mulai_id, 'expected_snapshot_id' => $this->context->id,
            'expected_snapshot_versi' => 1, 'alasan_koreksi' => 'Perbaikan salah input target', 'rujukan_koreksi' => 'PK resmi 2026',
        ]);
        $this->assertNotSame($this->context->id, $baru);
        $this->assertEquals($snapshot, DB::table('jadwal_snapshot')->where('id', $this->context->id)->first());
        $this->assertSame($lama, $fingerprint());
    }
}
