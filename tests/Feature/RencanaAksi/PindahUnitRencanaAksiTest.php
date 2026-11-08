<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\Kegiatan\DaftarKegiatanRencanaAksi;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

class PindahUnitRencanaAksiTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    public function test_perencanaan_memindahkan_header_ke_unit_indikator_dan_mengauditnya(): void
    {
        $this->setUpRencanaAksiFixture();
        $perencanaan = $this->createUserWithRole('perencanaan');
        $tujuan = Unit::create(['nama' => 'Unit Tujuan', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->indikator->update(['unit_id' => $tujuan->id]);

        $this->actingAs($perencanaan)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/pindah-unit", [
            'expected_versi' => 1,
            'alasan' => 'Perubahan penanggung jawab indikator',
        ])->assertRedirect();

        $this->assertSame($tujuan->id, $this->rencanaAksi->fresh()->unit_id);
        $this->assertSame(2, $this->rencanaAksi->fresh()->versi);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.pindah_unit')->where('objek_id', $this->rencanaAksi->id)->exists());
    }

    public function test_kegiatan_rencana_ikut_pindah(): void
    {
        $this->setUpRencanaAksiFixture();
        $perencanaan = $this->createUserWithRole('perencanaan');
        $kegiatan = Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->snapshot->periode_mulai_id, 'nama' => 'Kegiatan', 'created_by' => $this->actor->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $this->rencanaAksi->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah', 'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->actor->id, 'created_at' => now()]);
        $tujuan = Unit::create(['nama' => 'Tujuan', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->indikator->update(['unit_id' => $tujuan->id]);
        $this->actingAs($perencanaan)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/pindah-unit", ['expected_versi' => 1, 'alasan' => 'Pemindahan unit'])->assertRedirect();
        $this->assertSame($tujuan->id, $kegiatan->fresh()->unit_id);
    }

    public function test_riwayat_pengajuan_mencegah_pemindahan(): void
    {
        $this->setUpRencanaAksiFixture();
        $perencanaan = $this->createUserWithRole('perencanaan');
        $this->createRencanaAksiSubmission();
        $this->rencanaAksi->update(['status_alur' => 'dikembalikan']);
        $tujuan = Unit::create(['nama' => 'Tujuan', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->indikator->update(['unit_id' => $tujuan->id]);
        $this->actingAs($perencanaan)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/pindah-unit", ['expected_versi' => 1, 'alasan' => 'Pemindahan unit'])->assertUnprocessable();
        $this->assertSame($this->unit->id, $this->rencanaAksi->fresh()->unit_id);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.pindah_unit_ditolak')->exists());
    }

    public function test_token_usang_dan_unit_sama_ditolak(): void
    {
        $this->setUpRencanaAksiFixture();
        $perencanaan = $this->createUserWithRole('perencanaan');
        $url = "/rencana-aksi/{$this->rencanaAksi->id}/pindah-unit";
        $this->actingAs($perencanaan)->postJson($url, ['expected_versi' => 2, 'alasan' => 'Pemindahan unit'])->assertStatus(409);
        $this->postJson($url, ['expected_versi' => 1, 'alasan' => 'Pemindahan unit'])->assertUnprocessable();
        $this->assertSame($this->unit->id, $this->rencanaAksi->fresh()->unit_id);
    }

    public function test_deny_unit_tujuan_ditolak_dan_diaudit(): void
    {
        $this->setUpRencanaAksiFixture();
        $perencanaan = $this->createUserWithRole('perencanaan');
        $tujuan = Unit::create(['nama' => 'Tujuan', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->denyPermission($perencanaan, 'rencana_aksi:update', $tujuan->id);
        $this->indikator->update(['unit_id' => $tujuan->id]);
        $this->actingAs($perencanaan)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/pindah-unit", ['expected_versi' => 1, 'alasan' => 'Pemindahan unit'])->assertForbidden();
        $this->assertSame($this->unit->id, $this->rencanaAksi->fresh()->unit_id);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.pindah_unit_ditolak')->exists());
    }

    public function test_kegiatan_dengan_status_final_menolak_seluruh_pemindahan(): void
    {
        $this->setUpRencanaAksiFixture();
        $perencanaan = $this->createUserWithRole('perencanaan');
        $kegiatan = Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->snapshot->periode_mulai_id, 'nama' => 'Kegiatan', 'status' => 'batal', 'justifikasi' => 'Dibatalkan', 'created_by' => $this->actor->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $this->rencanaAksi->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah', 'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->actor->id, 'created_at' => now()]);
        $tujuan = Unit::create(['nama' => 'Tujuan', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->indikator->update(['unit_id' => $tujuan->id]);
        $this->actingAs($perencanaan)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/pindah-unit", ['expected_versi' => 1, 'alasan' => 'Pemindahan unit'])->assertUnprocessable();
        $this->assertSame($this->unit->id, $this->rencanaAksi->fresh()->unit_id);
        $this->assertSame($this->unit->id, $kegiatan->fresh()->unit_id);
    }

    public function test_kegiatan_berklaim_ra_lain_tidak_dipindahkan(): void
    {
        $this->setUpRencanaAksiFixture();
        $perencanaan = $this->createUserWithRole('perencanaan');
        $indikatorLain = $this->indikator->replicate();
        $indikatorLain->kode = 'IKU-LAIN';
        $indikatorLain->save();
        $raLain = $this->rencanaAksi->replicate();
        $raLain->indikator_id = $indikatorLain->id;
        $raLain->save();
        $kegiatan = Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->snapshot->periode_mulai_id, 'nama' => 'Kegiatan bersama', 'created_by' => $this->actor->id]);
        foreach ([$this->rencanaAksi, $raLain] as $header) {
            KlaimKegiatan::create(['rencana_aksi_id' => $header->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah', 'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->actor->id, 'created_at' => now()]);
        }
        $tujuan = Unit::create(['nama' => 'Tujuan', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->indikator->update(['unit_id' => $tujuan->id]);
        $this->actingAs($perencanaan)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/pindah-unit", ['expected_versi' => 1, 'alasan' => 'Pemindahan unit'])->assertUnprocessable();
        $this->assertSame($this->unit->id, $this->rencanaAksi->fresh()->unit_id);
        $this->assertSame($this->unit->id, $kegiatan->fresh()->unit_id);
    }

    public function test_tanpa_alasan_ditolak_dan_diaudit(): void
    {
        $this->setUpRencanaAksiFixture();
        $perencanaan = $this->createUserWithRole('perencanaan');
        $tujuan = Unit::create(['nama' => 'Tujuan', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->indikator->update(['unit_id' => $tujuan->id]);
        $this->actingAs($perencanaan)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/pindah-unit", ['expected_versi' => 1, 'alasan' => ''])->assertUnprocessable();
        $this->assertSame($this->unit->id, $this->rencanaAksi->fresh()->unit_id);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.pindah_unit_ditolak')->exists());
    }

    public function test_kegiatan_dengan_riwayat_beku_ditolak(): void
    {
        $this->setUpRencanaAksiFixture();
        $perencanaan = $this->createUserWithRole('perencanaan');
        $indikatorLain = $this->indikator->replicate();
        $indikatorLain->kode = 'IKU-LAIN';
        $indikatorLain->save();
        $raLain = $this->rencanaAksi->replicate();
        $raLain->indikator_id = $indikatorLain->id;
        $raLain->save();
        $kegiatan = Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->snapshot->periode_mulai_id, 'nama' => 'Kegiatan', 'created_by' => $this->actor->id]);
        KlaimKegiatan::create(['rencana_aksi_id' => $this->rencanaAksi->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah', 'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->actor->id, 'created_at' => now()]);
        $raLain->versions()->create(['jadwal_snapshot_id' => $this->snapshot->id, 'nomor' => 1, 'diajukan_by' => $this->actor->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => [], 'snapshot' => ['klaim' => [['kegiatan_id' => $kegiatan->id]]]]);
        $tujuan = Unit::create(['nama' => 'Tujuan', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->indikator->update(['unit_id' => $tujuan->id]);
        $this->actingAs($perencanaan)->postJson("/rencana-aksi/{$this->rencanaAksi->id}/pindah-unit", ['expected_versi' => 1, 'alasan' => 'Pemindahan unit'])->assertUnprocessable();
        $this->assertSame($this->unit->id, $kegiatan->fresh()->unit_id);
        $this->assertSame($this->unit->id, $this->rencanaAksi->fresh()->unit_id);
    }

    public function test_tombol_hanya_untuk_pemegang_izin_kedua_unit(): void
    {
        $this->setUpRencanaAksiFixture();
        $perencanaan = $this->createUserWithRole('perencanaan');
        $tujuan = Unit::create(['nama' => 'Tujuan', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->indikator->update(['unit_id' => $tujuan->id]);
        $url = "/rencana-aksi/{$this->rencanaAksi->id}";
        $this->actingAs($perencanaan)->get($url)->assertOk()->assertInertia(fn ($page) => $page->where('rencanaAksi.can.pindah_unit', true));
        $this->denyPermission($perencanaan, 'rencana_aksi:update', $tujuan->id);
        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->where('rencanaAksi.can.pindah_unit', false));
    }

    public function test_daftar_kegiatan_menolak_unit_yang_berubah_saat_baca(): void
    {
        $this->setUpRencanaAksiFixture();
        $tujuan = Unit::create(['nama' => 'Tujuan', 'status' => 'aktif', 'created_by' => $this->actor->id]);
        $jumlah = 0;
        $dipindah = false;
        $id = $this->rencanaAksi->id;
        DB::connection()->beforeExecuting(function (string $sql) use (&$jumlah, &$dipindah, $id, $tujuan): void {
            if (str_contains($sql, 'from "rencana_aksi"') && str_contains($sql, 'limit 1') && ++$jumlah === 2) {
                $dipindah = true;
                RencanaAksi::whereKey($id)->update(['unit_id' => $tujuan->id]);
            }
        });

        $result = app(DaftarKegiatanRencanaAksi::class)->handle($this->actor, $id, []);
        $this->assertTrue($dipindah);
        $this->assertNull($result);
    }
}
