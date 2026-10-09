<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\RencanaAksi\DaftarRencanaAksi;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JadwalTahunan;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RencanaAksiIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_peringatan_turun_tidak_memblokir_penyimpanan(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $header->fresh()->versi);

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.periode.0.id', $fixture['periode1']->id)
                ->where('rencanaAksi.periode.0.efektif', true)
                ->where('rencanaAksi.periode.0.skor.nilai', '20.00')
                ->where('rencanaAksi.periode.0.skor.status_perhitungan', 'terhitung')
                ->where('rencanaAksi.periode.0.peringatan_turun', false)
                ->where('rencanaAksi.periode.1.id', $fixture['periode2']->id)
                ->where('rencanaAksi.periode.1.skor.nilai', '10.00')
                ->where('rencanaAksi.periode.1.peringatan_turun', true)
                ->where('rencanaAksi.periode.1.komponen_turun', [null]));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 2,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.periode.1.peringatan_turun', false)
                ->where('rencanaAksi.periode.1.komponen_turun', []));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->fresh()->id}/target", [
            'expected_versi' => 3,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => null, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.periode.1.skor.nilai', null)
                ->where('rencanaAksi.periode.1.skor.status_perhitungan', 'belum_diisi')
                ->where('rencanaAksi.periode.1.peringatan_turun', false));
    }

    public function test_deviasi_pk_butuh_alasan_dan_tersimpan_sebagai_simpan(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 60, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 80, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.target_pk', '100.000000000000')
                ->where('rencanaAksi.deviasi_pk.dapat_dinilai', true)
                ->where('rencanaAksi.deviasi_pk.ada', true)
                ->where('rencanaAksi.deviasi_pk.alasan_diperlukan', true)
                ->where('rencanaAksi.deviasi_pk.alasan_terisi', false)
                ->where('rencanaAksi.deviasi_pk.skor_periode_terakhir', '80.00')
                ->where('rencanaAksi.deviasi_pk.periode_id', $fixture['periode2']->id));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->fresh()->id}/target", [
            'expected_versi' => 2,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'alasan_deviasi_pk' => 'Realisasi lapangan di bawah target PK tahunan.',
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 60, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 80, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $header->refresh();
        $this->assertSame('draft', $header->status_alur);
        $this->assertSame('Realisasi lapangan di bawah target PK tahunan.', $header->alasan_deviasi_pk);

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.deviasi_pk.ada', true)
                ->where('rencanaAksi.deviasi_pk.alasan_terisi', true)
                ->where('rencanaAksi.alasan_deviasi_pk', 'Realisasi lapangan di bawah target PK tahunan.'));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->fresh()->id}/target", [
            'expected_versi' => 3,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 60, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.deviasi_pk.ada', false)
                ->where('rencanaAksi.deviasi_pk.alasan_diperlukan', false));
    }

    public function test_nonmanual_menampilkan_skor_turunan_peringatan_dan_deviasi(): void
    {
        $fixture = $this->buatFixtureRasio();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 50, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 80, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.tipe_perhitungan', 'rasio_persen')
                ->has('rencanaAksi.komponen', 2)
                ->where('rencanaAksi.periode.0.skor.nilai', '50.00')
                ->where('rencanaAksi.periode.0.skor.status_perhitungan', 'terhitung')
                ->where('rencanaAksi.periode.0.peringatan_turun', false)
                ->where('rencanaAksi.periode.1.skor.nilai', '80.00')
                ->where('rencanaAksi.periode.1.peringatan_turun', false)
                ->where('rencanaAksi.deviasi_pk.ada', true)
                ->where('rencanaAksi.deviasi_pk.alasan_diperlukan', true));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->fresh()->id}/target", [
            'expected_versi' => 2,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 50, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 30, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.periode.1.skor.nilai', '30.00')
                ->where('rencanaAksi.periode.1.peringatan_turun', true)
                ->where('rencanaAksi.periode.1.komponen_turun', [$fixture['pembilang']->id]));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->fresh()->id}/target", [
            'expected_versi' => 3,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 50, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 5, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 0, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.periode.1.skor.nilai', null)
                ->where('rencanaAksi.periode.1.skor.status_perhitungan', 'tidak_dapat_dihitung')
                ->where('rencanaAksi.deviasi_pk.dapat_dinilai', false)
                ->where('rencanaAksi.deviasi_pk.ada', false));
    }

    public function test_baca_menolak_tanpa_izin_dan_menandai_can(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $tanpaIzin = $this->penggunaDenganPeran('admin');
        $this->actingAs($tanpaIzin)->get("/rencana-aksi/{$header->id}")->assertForbidden();

        $bacaSaja = $this->penggunaDenganPeran('pimpinan');
        $this->actingAs($bacaSaja)->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.can.view', true)
                ->where('rencanaAksi.can.update', false));

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.can.view', true)
                ->where('rencanaAksi.can.update', true)
                ->where('rencanaAksi.expected_versi', 1)
                ->where('rencanaAksi.status_alur', 'draft'));
    }

    /** Lookup header mendahului Gate: UUID asing selalu 404, dengan maupun tanpa izin baca. */
    public function test_tampilan_header_tak_ditemukan_404_sebelum_otorisasi(): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $idAsing = (string) Str::uuid();

        $this->actingAs($this->penggunaDenganPeran('admin'))->get("/rencana-aksi/{$idAsing}")->assertNotFound();
        $this->actingAs($this->penggunaDenganPeran('pimpinan'))->get("/rencana-aksi/{$idAsing}")->assertNotFound();
    }

    /**
     * Daftar Rencana Aksi adalah titik masuk PIC: indikator miliknya tampil
     * di atas, "Buat" hanya ditawarkan bila gerbang server mengizinkan, dan
     * berubah menjadi "Buka" setelah draf ada.
     */
    public function test_daftar_menawarkan_buat_lalu_buka_dengan_milik_sendiri_di_atas(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $lain = $this->tambahIndikatorTerjadwal($fixture, 'A-LAIN');

        $this->actingAs($fixture['pic'])->get('/rencana-aksi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('RencanaAksi/Index')
                ->where('auth.can.rencanaAksi', true)
                ->has('daftar', 2)
                ->where('daftar.0.indikator_id', $fixture['indikator']->id)
                ->where('daftar.0.tahun', 2026)
                ->where('daftar.0.milik_saya', true)
                ->where('daftar.0.rencana_aksi', null)
                ->where('daftar.0.can.create', true)
                ->where('daftar.1.indikator_id', $lain->id)
                ->where('daftar.1.milik_saya', false)
                ->where('daftar.1.can.create', false));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->get('/rencana-aksi')
            ->assertInertia(fn ($page) => $page
                ->where('daftar.0.rencana_aksi.id', $header->id)
                ->where('daftar.0.rencana_aksi.status_alur', 'draft')
                ->where('daftar.0.can.create', false));
    }

    /**
     * PJ efektif sudah ikut di-join daftar; capability "buat" tidak boleh
     * membaca ulang penugasan per baris kandidat (N+1).
     */
    public function test_daftar_tidak_membaca_ulang_pic_per_baris(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $bacaPenugasan = function () use ($fixture): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $daftar = app(DaftarRencanaAksi::class)->handle($fixture['pic']);
            $jumlah = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '"penanggung_jawab"'))->count();
            DB::disableQueryLog();
            $this->assertSame([true], collect($daftar['daftar'])->where('milik_saya', true)->pluck('can.create')->all());
            $this->assertSame([], collect($daftar['daftar'])->where('milik_saya', false)->where('can.create', true)->all());

            return $jumlah;
        };
        foreach (range(1, 4) as $nomor) {
            $this->tambahIndikatorTerjadwal($fixture, "A-LAIN-{$nomor}");
        }
        $limaBaris = $bacaPenugasan();
        foreach (range(5, 19) as $nomor) {
            $this->tambahIndikatorTerjadwal($fixture, "A-LAIN-{$nomor}");
        }

        $this->assertSame($limaBaris, $bacaPenugasan());
    }

    /**
     * Di luar jendela hanya jalur Perencanaan yang ditawari "Buat", dan
     * indikator tanpa PJ efektif tidak ditawari sama sekali karena
     * EnsureDraftRencanaAksi pasti menolaknya.
     */
    public function test_daftar_di_luar_jendela_hanya_perencanaan_yang_ditawari_buat(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 4, 10)->setTime(9, 0));
        $tanpaPj = $this->tambahIndikatorTerjadwal($fixture, 'A-TANPA-PJ', denganPj: false);

        $this->actingAs($fixture['pic'])->get('/rencana-aksi')
            ->assertInertia(fn ($page) => $page->where('daftar.0.indikator_id', $fixture['indikator']->id)->where('daftar.0.can.create', false));
        $this->actingAs($fixture['perencanaan'])->get('/rencana-aksi')
            ->assertInertia(fn ($page) => $page
                ->where('daftar.0.indikator_id', $tanpaPj->id)
                ->where('daftar.0.pj_nama', null)
                ->where('daftar.0.can.create', false)
                ->where('daftar.1.indikator_id', $fixture['indikator']->id)
                ->where('daftar.1.can.create', true));
    }

    /**
     * Deny `rencana_aksi:read` pada satu unit hanya menyembunyikan baris unit
     * itu. Baris yang sudah punya header disaring memakai unit header, bukan
     * unit master indikator yang mungkin sudah pindah.
     */
    public function test_daftar_menyaring_unit_yang_ditolak_dan_menolak_tanpa_izin_baca(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $unitLain = Unit::create(['nama' => 'Unit Lain RA', 'status' => 'aktif', 'created_by' => $fixture['perencanaan']->id]);
        $lain = $this->tambahIndikatorTerjadwal($fixture, 'A-UNIT-LAIN', unitId: $unitLain->id);
        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $fixture['indikator']->update(['unit_id' => $unitLain->id]);
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $fixture['pic']->id,
            'permission_id' => Permission::where('kode', 'rencana_aksi:read')->value('id'),
            'unit_id' => $fixture['unit']->id,
            'alasan' => 'Fixture pembatasan',
            'ditetapkan_oleh' => $fixture['perencanaan']->id,
            'created_at' => now(),
        ]);

        $this->actingAs($fixture['pic'])->get('/rencana-aksi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('daftar', 1)->where('daftar.0.indikator_id', $lain->id));
        $this->actingAs($this->penggunaDenganPeran('admin'))->get('/rencana-aksi')->assertForbidden();
    }

    /**
     * Baris tanpa header disaring memakai unit snapshot terbaru, bukan unit
     * master yang bisa berpindah setelah aktivasi.
     */
    public function test_daftar_menyaring_deny_memakai_unit_snapshot_untuk_baris_tanpa_header(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $unitBaru = Unit::create(['nama' => 'Unit Master Baru', 'status' => 'aktif', 'created_by' => $fixture['perencanaan']->id]);
        $fixture['indikator']->update(['unit_id' => $unitBaru->id]);

        $ditolakUnitSnapshot = $this->penggunaDenganPeran('pimpinan');
        $this->tolakBaca($ditolakUnitSnapshot, $fixture['unit']->id, $fixture['perencanaan']);
        $this->actingAs($ditolakUnitSnapshot)->get('/rencana-aksi')->assertOk()->assertInertia(fn ($page) => $page->has('daftar', 0));

        $ditolakUnitMaster = $this->penggunaDenganPeran('pimpinan');
        $this->tolakBaca($ditolakUnitMaster, $unitBaru->id, $fixture['perencanaan']);
        $this->actingAs($ditolakUnitMaster)->get('/rencana-aksi')->assertOk()->assertInertia(fn ($page) => $page
            ->has('daftar', 1)
            ->where('daftar.0.indikator_id', $fixture['indikator']->id));
    }

    /**
     * Baris ber-header menampilkan unit header; baris tanpa header
     * menampilkan unit snapshot terbaru. Unit master yang berpindah tidak
     * mengubah tampilan.
     */
    public function test_daftar_menampilkan_unit_header_dan_unit_snapshot(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $unitSnapshot = Unit::create(['nama' => 'Unit Snapshot Lain', 'status' => 'aktif', 'created_by' => $fixture['perencanaan']->id]);
        $tanpaHeader = $this->tambahIndikatorTerjadwal($fixture, 'A-SNAPSHOT', unitId: $unitSnapshot->id);
        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $unitPindah = Unit::create(['nama' => 'Unit Master Pindah', 'status' => 'aktif', 'created_by' => $fixture['perencanaan']->id]);
        IndikatorKinerja::whereKey([$fixture['indikator']->id, $tanpaHeader->id])->update(['unit_id' => $unitPindah->id]);

        $this->actingAs($this->penggunaDenganPeran('pimpinan'))->get('/rencana-aksi')->assertOk()->assertInertia(fn ($page) => $page
            ->where('daftar', fn ($daftar) => collect($daftar)->pluck('unit_nama', 'indikator_id')->all() === [
                $tanpaHeader->id => 'Unit Snapshot Lain',
                $fixture['indikator']->id => 'Unit Uji RA Baca',
            ]));
    }

    /**
     * Gerbang tulis juga menolak unit nonaktif, indikator arsip, dan unit
     * snapshot yang tidak selaras dengan unit master, sehingga capability
     * tidak boleh menawarkan aksi yang pasti ditolak (dan diaudit sebagai
     * penolakan).
     */
    public function test_capability_tidak_menawarkan_aksi_saat_unit_nonaktif_atau_indikator_arsip(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $baru = $this->tambahIndikatorTerjadwal($fixture, 'A-BARU');

        Unit::whereKey($fixture['unit']->id)->update(['status' => 'nonaktif']);
        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}")
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.can.update', false));
        $this->actingAs($fixture['perencanaan'])->get('/rencana-aksi')
            ->assertInertia(fn ($page) => $page->where('daftar.0.indikator_id', $baru->id)->where('daftar.0.can.create', false));

        Unit::whereKey($fixture['unit']->id)->update(['status' => 'aktif']);
        $fixture['indikator']->update(['status' => 'arsip']);
        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}")
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.can.update', false));

        // Indikator pindah unit setelah aktivasi: snapshot masih berunit lama
        // sehingga EnsureDraftRencanaAksi menolak; tombol tidak ditawarkan.
        $this->actingAs($fixture['perencanaan'])->get('/rencana-aksi')
            ->assertInertia(fn ($page) => $page->where('daftar.0.indikator_id', $baru->id)->where('daftar.0.can.create', true));
        $unitLain = Unit::create(['nama' => 'Unit Tujuan RA', 'status' => 'aktif', 'created_by' => $fixture['perencanaan']->id]);
        $baru->update(['unit_id' => $unitLain->id]);
        $this->actingAs($fixture['perencanaan'])->get('/rencana-aksi')
            ->assertInertia(fn ($page) => $page->where('daftar.0.indikator_id', $baru->id)->where('daftar.0.can.create', false));
        $this->actingAs($fixture['perencanaan'])->post('/rencana-aksi/ensure-draft', ['indikator_id' => $baru->id, 'tahun' => 2026])
            ->assertSessionHasErrors('snapshot');

        // Snapshot koreksi v2 berunit baru: versi terbaru yang menentukan,
        // dan indikator tetap satu baris walau punya dua versi snapshot.
        $v1 = JadwalSnapshot::where('indikator_id', $baru->id)->sole();
        JadwalSnapshot::create([
            ...$v1->only(['jadwal_id', 'indikator_id', 'periode_mulai_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'nomor_versi' => 2,
            'menggantikan_id' => $v1->id,
            'alasan_koreksi' => 'Koreksi unit setelah pindah.',
            'rujukan_koreksi' => 'SK-KOREKSI-UNIT-RA',
            'unit_id' => $unitLain->id,
        ]);
        $this->actingAs($fixture['perencanaan'])->get('/rencana-aksi')
            ->assertInertia(fn ($page) => $page->has('daftar', 2)->where('daftar.0.indikator_id', $baru->id)->where('daftar.0.can.create', true));
    }

    /**
     * `can.update` memakai gerbang tulis yang sama dengan SimpanTargetPeriode:
     * PIC di luar jendela dan header yang sudah diajukan tidak ditawari form.
     */
    public function test_can_update_mengikuti_gerbang_tulis(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $respon = $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $respon->assertRedirect(route('rencana-aksi.show', $header));

        $this->travelTo(now()->setDate(2026, 4, 10)->setTime(9, 0));
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.can.update', false));
        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}")
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.can.update', true));

        $header->update(['status_alur' => RencanaAksi::STATUS_DIAJUKAN]);
        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}")
            ->assertInertia(fn ($page) => $page->where('rencanaAksi.can.update', false));
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(string $tipe = 'manual', int $mulaiUrutan = 1): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji RA Baca', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-RAB', 'nama' => 'Renstra Uji RA Baca', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-RAB', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Baca Uji',
            'satuan' => 'poin',
            'tipe_perhitungan' => $tipe,
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $periode1 = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $periode2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => true]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'rencana_aksi_mulai' => '2026-03-01',
            'rencana_aksi_selesai' => '2026-03-31',
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);
        foreach ([$periode1, $periode2] as $periode) {
            PeriodeJadwal::create([
                'jadwal_id' => $jadwal->id,
                'periode_id' => $periode->id,
                'pengisian_mulai' => '2026-03-01',
                'pengisian_selesai' => '2026-03-31',
                'reviu_mulai' => '2026-04-01',
                'reviu_selesai' => '2026-04-30',
            ]);
        }
        $mulaiId = $mulaiUrutan === 2 ? $periode2->id : $periode1->id;
        $snapshot = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $indikator->id,
            'periode_mulai_id' => $mulaiId,
            'unit_id' => $unit->id,
            'nama' => $indikator->nama,
            'definisi' => 'Definisi beku.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => $tipe,
            'target' => 100,
        ]);
        PenugasanIndikator::create([
            'indikator_id' => $indikator->id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $perencanaan->id,
            'created_at' => now(),
        ]);

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periode1', 'periode2', 'jadwal', 'snapshot');
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureRasio(): array
    {
        $dasar = $this->buatFixtureManual('rasio_persen', 1);

        $pembilang = IndikatorKomponen::create([
            'indikator_id' => $dasar['indikator']->id,
            'kode' => 'n',
            'label' => 'Pembilang',
            'peran' => 'pembilang',
            'bobot' => 1,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $dasar['perencanaan']->id,
        ]);
        $penyebut = IndikatorKomponen::create([
            'indikator_id' => $dasar['indikator']->id,
            'kode' => 't',
            'label' => 'Penyebut',
            'peran' => 'penyebut',
            'bobot' => 1,
            'urutan' => 2,
            'aktif' => true,
            'created_by' => $dasar['perencanaan']->id,
        ]);
        foreach ([$pembilang, $penyebut] as $index => $komponen) {
            JadwalSnapshotKomponen::create([
                'jadwal_snapshot_id' => $dasar['snapshot']->id,
                'komponen_id' => $komponen->id,
                'kode' => $komponen->kode,
                'label' => $komponen->label,
                'peran' => $komponen->peran,
                'bobot' => 1,
                'urutan' => $index + 1,
            ]);
        }

        return [...$dasar, 'pembilang' => $pembilang, 'penyebut' => $penyebut];
    }

    /**
     * Indikator kedua pada jadwal fixture yang sama, dengan PJ pengguna lain
     * (atau tanpa PJ bila `$denganPj` false).
     *
     * @param  array<string, mixed>  $fixture
     */
    private function tambahIndikatorTerjadwal(array $fixture, string $kode, bool $denganPj = true, ?string $unitId = null): IndikatorKinerja
    {
        $unitId ??= $fixture['unit']->id;
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $fixture['sasaran']->id,
            'unit_id' => $unitId,
            'kode' => $kode,
            'nama' => 'Indikator Lain',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $fixture['perencanaan']->id,
            'created_by_role' => 'perencanaan',
        ]);
        JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $indikator->id,
            'periode_mulai_id' => $fixture['periode1']->id,
            'unit_id' => $unitId,
            'nama' => $indikator->nama,
            'definisi' => 'Definisi beku.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 50,
        ]);
        if ($denganPj) {
            PenugasanIndikator::create([
                'indikator_id' => $indikator->id,
                'user_id' => $this->penggunaDenganPeran('pegawai')->id,
                'tanggal_mulai_berlaku' => '2026-01-01',
                'ditetapkan_oleh' => $fixture['perencanaan']->id,
                'created_at' => now(),
            ]);
        }

        return $indikator;
    }

    private function tolakBaca(User $user, string $unitId, User $oleh): void
    {
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'permission_id' => Permission::where('kode', 'rencana_aksi:read')->value('id'),
            'unit_id' => $unitId,
            'alasan' => 'Fixture pembatasan',
            'ditetapkan_oleh' => $oleh->id,
            'created_at' => now(),
        ]);
    }

    private function penggunaDenganPeran(string $kode): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', $kode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function grant(User $user, string $permission, string $unitId, User $oleh): void
    {
        DB::table('user_permission_granted')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'permission_id' => Permission::where('kode', $permission)->value('id'),
            'unit_id' => $unitId,
            'alasan' => 'Fixture pengujian',
            'diberikan_oleh' => $oleh->id,
            'created_at' => now(),
        ]);
    }
}
