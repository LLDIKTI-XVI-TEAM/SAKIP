<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\AuditLog;
use App\Models\Role;
use App\Services\RencanaAksi\GerbangReviewRencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

class RencanaAksiProducerReviewTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    public function test_producer_koreksi_satu_periode_dapat_diverifikasi_dikembalikan_dan_diajukan_ulang(): void
    {
        $f = $this->buatFixtureManual();
        $header = $this->drafManual($f);
        // Provenance PIC dibentuk sebelum jadwal masuk sesi koreksi.
        $this->actingAs($f['pic'])->postJson("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $f['jadwal']->update([
            'penutupan' => '2026-03-15', 'koreksi_mulai' => '2026-03-16 00:00:00', 'koreksi_sampai' => '2026-03-31 23:59:59',
            'lingkup_koreksi' => ['jenis_objek' => ['rencana_aksi'], 'indikator_ids' => [$f['indikator']->id], 'periode_ids' => [$f['periode2']->id]],
        ]);
        $this->travelTo(now()->setDate(2026, 3, 20)->setTime(9, 0));

        $versi = $header->versions()->sole();
        $beku = $versi->getRawOriginal('snapshot');
        $this->assertCount(2, $versi->snapshot['target_periode']);
        $tokens = ['expected_versi' => 3, 'versi_pengajuan_id' => $versi->id];
        $roleId = Role::where('kode', 'perencanaan')->value('id');
        $rolePicId = $f['pic']->roles()->sole()->id;
        $f['pic']->roles()->sync([$roleId => ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $f['perencanaan']->id, 'created_at' => now()]]);
        $this->postJson("/rencana-aksi/{$header->id}/verifikasi", $tokens)->assertUnprocessable()
            ->assertJsonValidationErrors(['businessErrors' => 'F1: pengaju melalui jalur PIC tidak boleh memverifikasi atau mengesahkan pengajuannya sendiri.']);
        $f['pic']->roles()->sync([$rolePicId => ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $f['perencanaan']->id, 'created_at' => now()]]);
        $this->actingAs($f['perencanaan'])->get("/rencana-aksi/{$header->id}/review")->assertInertia(fn (Assert $p) => $p
            ->where('rencanaAksi.can.verifikasi', true)->where('rencanaAksi.can.kembalikan', true));
        $this->postJson("/rencana-aksi/{$header->id}/verifikasi", $tokens)->assertSuccessful();
        $this->assertSame([], app(GerbangReviewRencanaAksi::class)->businessErrors($f['perencanaan'], $header->fresh(), $versi, 'sahkan'));
        $this->postJson("/rencana-aksi/{$header->id}/kembalikan", ['expected_versi' => 4, 'versi_pengajuan_id' => $versi->id, 'alasan' => 'Jelaskan target periode II.'])->assertSuccessful();
        // Lingkup tetap diperiksa sebelum akses review.
        $this->actingAs($f['perencanaan'])->postJson("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 5, 'expected_snapshot_id' => $f['snapshot']->id, 'expected_snapshot_versi' => 1,
            'targets' => [$this->manual($f['periode1'], 50)],
        ])->assertUnprocessable()->assertJsonValidationErrors('jendela');

        $this->simpan($f, $header, 5, [array_merge($this->manual($f['periode2'], 100), ['keterangan' => 'Target periode II telah diperiksa.'])], $f['perencanaan']);
        $this->postJson("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 6])->assertRedirect()->assertSessionHasNoErrors();
        $baru = $header->versions()->reorder('nomor', 'desc')->firstOrFail();
        $this->assertSame(2, $baru->nomor);
        $this->assertSame($beku, $versi->fresh()->getRawOriginal('snapshot'));
        $this->actingAs($f['perencanaan'])->get("/rencana-aksi/{$header->id}/review")->assertInertia(fn (Assert $p) => $p
            ->where('rencanaAksi.target_periode.1.keterangan', 'Target periode II telah diperiksa.')->has('rencanaAksi.histori', 2));
        $this->postJson("/rencana-aksi/{$header->id}/verifikasi", ['expected_versi' => 7, 'versi_pengajuan_id' => $baru->id])->assertSuccessful();
        $this->assertSame('diverifikasi', $header->fresh()->status_alur);
        $this->assertSame(2, AuditLog::where('tindakan', 'rencana_aksi.verifikasi')->count());
    }

    public function test_review_membaca_keterangan_komponen_dan_nama_pengaju_perencanaan_dari_producer(): void
    {
        $f = $this->buatFixtureRasio();
        $header = $this->buatDraf($f);
        $targets = [...$this->rasio($f, $f['periode1'], 1, 1), ...$this->rasio($f, $f['periode2'], 1, 1)];
        $targets[0]['keterangan'] = 'Jumlah keluaran yang ditargetkan.';
        $this->simpan($f, $header, 1, $targets);
        $this->actingAs($f['perencanaan'])->postJson("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $this->get("/rencana-aksi/{$header->id}/review")->assertInertia(fn (Assert $p) => $p
            ->where('rencanaAksi.versi_pengajuan.pengaju.nama', $f['perencanaan']->nama)
            ->where('rencanaAksi.pic.id', $f['pic']->id)
            ->where('rencanaAksi.target_periode.0.keterangan', null)
            ->where('rencanaAksi.target_periode.0.komponen.0.keterangan', 'Jumlah keluaran yang ditargetkan.'));
    }
}
