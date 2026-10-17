<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\Pengukuran\SubmissionPrerequisites;
use App\Models\BuktiDukung;
use App\Models\PengukuranKinerja;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Versi pengajuan beku: tidak ikut berubah saat draf/bukti berubah, tidak dapat
 * dimutasi di tempat (trigger), dan terbaca oleh prasyarat Pengukuran.
 */
class RencanaAksiVersiBekuTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    public function test_versi_beku_tidak_berubah_saat_draf_dan_bukti_berubah(): void
    {
        $fixture = $this->buatFixtureManual();
        $jenis = $this->buatPersyaratanWajib($fixture);
        $header = $this->drafManual($fixture);
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/bukti", [
            'jenis_berkas_id' => $jenis->id,
            'mode' => 'teks',
            'isi_teks' => 'Ringkasan kerangka acuan kerja.',
        ])->assertSessionHasNoErrors();
        $bukti = BuktiDukung::where('berkasable_type', 'rencana_aksi')->where('berkasable_id', $header->id)->sole();
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versi = RencanaAksiVersi::sole();
        $snapshotAwal = $versi->snapshot;

        DB::table('rencana_aksi')->where('id', $header->id)->update(['status_alur' => RencanaAksi::STATUS_DIKEMBALIKAN]);
        $this->simpan($fixture, $header, 3, [$this->manual($fixture['periode2'], 50)]);
        $this->actingAs($fixture['pic'])->delete("/rencana-aksi/{$header->id}/bukti/{$bukti->id}", [
            'alasan' => 'Bukti diganti setelah dikembalikan.',
        ])->assertSessionHasNoErrors();

        $this->assertSame($snapshotAwal, $versi->fresh()->snapshot);
        $this->assertSame($bukti->id, $versi->fresh()->snapshot['bukti_dukungs'][0]['id']);

        $this->assertDitolakTrigger(fn () => DB::table('rencana_aksi_versi')->where('id', $versi->id)->update(['snapshot' => '{}']));
        DB::table('rencana_aksi_versi')->where('id', $versi->id)->update(['disahkan_by' => $fixture['perencanaan']->id, 'disahkan_at' => now()]);
        $this->assertDitolakTrigger(fn () => DB::table('rencana_aksi_versi')->where('id', $versi->id)->update(['disahkan_by' => $fixture['pic']->id, 'disahkan_at' => now()->addMinute()]));
        $this->assertSame($fixture['perencanaan']->id, $versi->fresh()->disahkan_by);
    }

    public function test_versi_beku_terbaca_oleh_prasyarat_pengukuran(): void
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();
        $versi = RencanaAksiVersi::sole();

        // Pengesahan disimulasikan (milik alur lanjutan); pembaca hanya butuh versi sah.
        DB::table('rencana_aksi_versi')->where('id', $versi->id)->update(['disahkan_by' => $fixture['perencanaan']->id, 'disahkan_at' => now()]);
        DB::table('rencana_aksi')->where('id', $header->id)->update(['status_alur' => RencanaAksi::STATUS_DISAHKAN]);
        $pengukuran = PengukuranKinerja::create([
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
            'periode_id' => $fixture['periode2']->id,
            'jadwal_snapshot_id' => $fixture['snapshot']->id,
            'nilai' => 100,
            'sumber_nilai' => 'manual',
            'status_perhitungan' => 'terhitung',
            'created_by' => $fixture['pic']->id,
        ]);

        $hasil = app(SubmissionPrerequisites::class)->handle($pengukuran, null);

        $this->assertSame('100.00', $hasil['target']);
        $this->assertSame($versi->id, $hasil['rencana_aksi_versi']->id);
        foreach ($hasil['alasan'] as $alasan) {
            $this->assertStringNotContainsString('Target pada versi RA', $alasan);
            $this->assertStringNotContainsString('Versi rencana aksi sah', $alasan);
        }
    }

    /** Savepoint menjaga transaksi RefreshDatabase tetap hidup setelah trigger menolak. */
    private function assertDitolakTrigger(callable $mutasi): void
    {
        try {
            DB::transaction($mutasi);
        } catch (QueryException) {
            return;
        }

        $this->fail('Trigger imutabilitas rencana_aksi_versi tidak menolak perubahan.');
    }
}
