<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\AuditLog;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

class RencanaAksiTargetTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    public function test_manual_menyimpan_satu_baris_null_per_periode(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();

        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->where('tahun', 2026)->sole();
        $this->assertSame($fixture['unit']->id, $header->unit_id);
        $this->assertSame($fixture['jadwal']->id, $header->jadwal_tahunan_id);
        $this->assertSame($fixture['pic']->id, $header->penanggung_jawab_id);
        $this->assertSame('draft', $header->status_alur);
        $this->assertSame(1, $header->versi);

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rencana_aksi', 1);

        $response = $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => null, 'keterangan' => null],
            ],
        ]);
        $response->assertSessionHasNoErrors();

        $header->refresh();
        $this->assertSame(2, $header->versi);
        $this->assertSame('10.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('periode_id', $fixture['periode1']->id)->whereNull('komponen_id')->sole()->getRawOriginal('nilai'));
        $this->assertNull(RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('periode_id', $fixture['periode2']->id)->whereNull('komponen_id')->sole()->nilai);
        $this->assertDatabaseMissing('rencana_aksi_target', ['rencana_aksi_id' => $header->id, 'keterangan' => 'skor_turunan']);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah')->where('objek_id', $header->id)->exists());
    }

    /**
     * Notasi eksponen (`1e3`) lolos aturan `numeric` sehingga wajib ditolak
     * sebagai 422 oleh regex desimal biasa, bukan 500 dari perbandingan nilai.
     */
    public function test_nilai_eksponen_ditolak_422_bukan_500(): void
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
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => '1e3', 'keterangan' => null],
            ],
        ])->assertSessionHasErrors('targets.0.nilai');
        $this->assertSame(1, $header->fresh()->versi);
    }

    /**
     * Seluruh matriks masuk audit dua kali tiap simpan, jadi ukuran per
     * permintaan (keterangan per sel) dan frekuensi tulis dibatasi agar
     * `audit_log` yang append-only tidak dapat dibanjiri. Pratinjau tidak
     * menulis audit dan dipanggil tiap ketikan, sehingga tidak ikut dibatasi.
     */
    public function test_tulis_membatasi_keterangan_dan_frekuensi_tanpa_membatasi_pratinjau(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $ensure = fn () => $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', ['indikator_id' => $fixture['indikator']->id, 'tahun' => 2026]);
        $payload = fn (?string $keterangan) => [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => $keterangan]],
        ];

        $ensure()->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", $payload(str_repeat('x', 1001)))
            ->assertSessionHasErrors('targets.0.keterangan');
        $this->assertSame(1, $header->fresh()->versi);

        for ($permintaan = 3; $permintaan <= 30; $permintaan++) {
            $ensure()->assertSessionHasNoErrors();
        }
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", $payload(null))->assertStatus(429);
        $this->assertSame(1, $header->fresh()->versi);
        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", $payload(null))->assertOk();
    }

    /**
     * Simpan parsial menimpa sebagian sel dan mempertahankan sisanya, jadi
     * batas total berlaku pada matriks tersimpan hasil penggabungan, bukan
     * hanya payload. Ditolak berarti rollback utuh.
     */
    public function test_batas_total_keterangan_berlaku_pada_matriks_tersimpan(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $kirim = fn (array $targets, int $versi) => $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $versi,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => $targets,
        ]);
        $sel = fn (string $periodeId, ?string $keterangan) => ['periode_id' => $periodeId, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => $keterangan];
        $kirim([$sel($fixture['periode1']->id, null), $sel($fixture['periode2']->id, null)], 1)->assertSessionHasNoErrors();
        // Mewakili akumulasi simpan parsial sebelumnya pada matriks besar
        // (fixture hanya dua periode, sedangkan batas per sel 1.000).
        RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('periode_id', $fixture['periode2']->id)->update(['keterangan' => str_repeat('y', 9500)]);

        $kirim([$sel($fixture['periode1']->id, str_repeat('x', 1000))], 2)
            ->assertSessionHasErrors(['targets' => 'Total keterangan seluruh target melebihi 10.000 karakter.']);
        $this->assertSame(2, $header->fresh()->versi);
        $this->assertNull(RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('periode_id', $fixture['periode1']->id)->sole()->keterangan);
        $this->assertSame(1, AuditLog::where('tindakan', 'rencana_aksi.ubah')->where('objek_id', $header->id)->count());
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah_ditolak')->where('objek_id', $header->id)->exists());

        $kirim([$sel($fixture['periode1']->id, str_repeat('x', 500))], 2)->assertSessionHasNoErrors();
        $this->assertSame(3, $header->fresh()->versi);
    }

    /** Pembacaan seluruh matriks per `rencana_aksi_id` memakai index, bukan Seq Scan. */
    public function test_baca_seluruh_matriks_memakai_index(): void
    {
        DB::statement('SET LOCAL enable_seqscan = off');
        $rencana = collect(DB::select('EXPLAIN SELECT * FROM rencana_aksi_target WHERE rencana_aksi_id = ?', [(string) Str::uuid()]))
            ->pluck('QUERY PLAN')->implode("\n");

        $this->assertStringNotContainsString('Seq Scan', $rencana);
    }

    /**
     * Baris target dimuat dan dikunci sekali per simpan; jumlah SELECT ke
     * `rencana_aksi_target` tidak boleh bertambah seiring jumlah sel.
     */
    public function test_simpan_tidak_membaca_ulang_tiap_sel(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $kirim = fn (array $targets, int $versi) => $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $versi,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => $targets,
        ]);
        $sel = fn (string $periodeId) => ['periode_id' => $periodeId, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null];
        $selectTarget = fn (): int => collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_starts_with($query['query'], 'select') && str_contains($query['query'], '"rencana_aksi_target"'))
            ->count();
        DB::enableQueryLog();

        $kirim([$sel($fixture['periode1']->id)], 1)->assertSessionHasNoErrors();
        $satuSel = $selectTarget();
        DB::flushQueryLog();
        $kirim([$sel($fixture['periode1']->id), $sel($fixture['periode2']->id)], 2)->assertSessionHasNoErrors();

        $this->assertSame($satuSel, $selectTarget());
    }

    /**
     * Seluruh sel terkirim ditulis dalam satu statement, berapa pun jumlah sel.
     */
    public function test_simpan_menulis_matriks_dalam_satu_statement(): void
    {
        $fixture = $this->buatFixtureRasio();
        $header = $this->buatDraf($fixture);
        $sel = fn (string $periode, string $komponen): array => ['periode_id' => $fixture[$periode]->id, 'komponen_id' => $fixture[$komponen]->id, 'nilai' => 2, 'keterangan' => null];
        $tulisTarget = fn (): int => collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => preg_match('/^(insert into|update) "rencana_aksi_target"/', $query['query']) === 1)
            ->count();
        DB::enableQueryLog();

        $this->simpan($fixture, $header, 1, [$sel('periode1', 'pembilang'), $sel('periode1', 'penyebut')]);
        $this->assertSame(1, $tulisTarget());
        DB::flushQueryLog();
        $this->simpan($fixture, $header, 2, [
            $sel('periode1', 'pembilang'), $sel('periode1', 'penyebut'),
            $sel('periode2', 'pembilang'), $sel('periode2', 'penyebut'),
        ]);
        $this->assertSame(1, $tulisTarget());
        $this->assertSame(4, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
    }

    /**
     * Sel yang sudah ada diperbarui di tempat (ID tetap) beserta penanda
     * penyunting dan waktunya; sel baru dibuat. Simpan kedua oleh aktor lain
     * yang berhak agar `updated_by`/`updated_at` terbukti ikut diperbarui.
     */
    public function test_simpan_ulang_mempertahankan_id_sel(): void
    {
        $fixture = $this->buatFixtureRasio();
        $header = $this->buatDraf($fixture);
        $sel = fn (string $periode, string $komponen, ?int $nilai): array => ['periode_id' => $fixture[$periode]->id, 'komponen_id' => $fixture[$komponen]->id, 'nilai' => $nilai, 'keterangan' => null];

        $this->simpan($fixture, $header, 1, [$sel('periode1', 'pembilang', 1), $sel('periode1', 'penyebut', 4)]);
        $lama = RencanaAksiTarget::where('rencana_aksi_id', $header->id)->pluck('id', 'komponen_id')->all();
        $waktuLama = RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('komponen_id', $fixture['pembilang']->id)->value('updated_at');
        $this->travel(1)->hours();
        $this->simpan($fixture, $header, 2, [
            $sel('periode1', 'pembilang', 3), $sel('periode1', 'penyebut', 4),
            $sel('periode2', 'pembilang', 2), $sel('periode2', 'penyebut', 5),
        ], $fixture['perencanaan']);

        $periode1 = RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('periode_id', $fixture['periode1']->id)->get()->keyBy('komponen_id');
        $this->assertSame($lama, $periode1->map->id->all());
        $this->assertSame('3.000000000000', $periode1[$fixture['pembilang']->id]->nilai);
        $this->assertSame((string) $fixture['perencanaan']->id, (string) $periode1[$fixture['pembilang']->id]->updated_by);
        $this->assertTrue($periode1[$fixture['pembilang']->id]->updated_at->gt($waktuLama));
        $periode2 = RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('periode_id', $fixture['periode2']->id)->pluck('id');
        $this->assertCount(2, $periode2);
        $this->assertTrue($periode2->every(fn (string $id): bool => Str::isUuid($id)));
    }

    /**
     * Pasangan periode+komponen yang dikirim dua kali ditolak sebelum upsert
     * (tanpa ini PostgreSQL menolak ON CONFLICT ganda dengan 21000 → 500).
     */
    #[DataProvider('pasanganGanda')]
    public function test_simpan_menolak_pasangan_periode_komponen_ganda_tanpa_tulis(bool $manual): void
    {
        $fixture = $manual ? $this->buatFixtureManual() : $this->buatFixtureRasio();
        $header = $this->buatDraf($fixture);
        $komponen = $manual ? null : $fixture['pembilang']->id;
        $baris = ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $komponen, 'nilai' => 1, 'keterangan' => null];
        $targets = $manual ? [$baris, [...$baris, 'nilai' => 2]] : [$baris, [...$baris, 'nilai' => 2], ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 4, 'keterangan' => null]];

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => $targets,
        ])->assertUnprocessable()->assertJsonValidationErrors('targets');

        $this->assertSame(1, $header->fresh()->versi);
        $this->assertSame(0, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
        $this->assertFalse(AuditLog::where('tindakan', 'rencana_aksi.ubah')->exists());
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function pasanganGanda(): array
    {
        return ['manual' => [true], 'berkomponen' => [false]];
    }

    /**
     * Bentuk audit simpan dikunci persis: header terpilih + seluruh matriks
     * terurut periode lalu komponen, dengan tipe nilai yang sama, baik untuk
     * keadaan sebelum maupun sesudah simpan.
     */
    public function test_audit_simpan_mencatat_matriks_sebelum_dan_sesudah_dengan_bentuk_tetap(): void
    {
        $fixture = $this->buatFixtureRasio();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $sel = fn (string $periode, string $komponen, ?int $nilai, ?string $keterangan = null): array => ['periode_id' => $fixture[$periode]->id, 'komponen_id' => $fixture[$komponen]->id, 'nilai' => $nilai, 'keterangan' => $keterangan];
        $kirim = fn (array $data) => $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            ...$data,
        ])->assertSessionHasNoErrors();

        $kirim(['expected_versi' => 1, 'targets' => [
            $sel('periode1', 'pembilang', 1), $sel('periode1', 'penyebut', 4),
            $sel('periode2', 'pembilang', 2), $sel('periode2', 'penyebut', 4),
        ]]);
        $kirim(['expected_versi' => 2, 'uraian' => 'Uraian kedua', 'targets' => [
            $sel('periode2', 'pembilang', 3, 'naik'), $sel('periode2', 'penyebut', null),
        ]]);

        // Urutan kunci objek mengikuti normalisasi `jsonb` (panjang kunci lalu
        // byte); urutan baris matriks berasal dari aplikasi dan wajib tetap.
        $matriks = fn (array $nilai): array => collect($nilai)
            ->map(fn (array $baris): array => ['nilai' => $baris[2], 'keterangan' => $baris[3] ?? null, 'periode_id' => $fixture[$baris[0]]->id, 'komponen_id' => $fixture[$baris[1]]->id])
            ->sortBy(fn (array $baris): string => $baris['periode_id'].'|'.$baris['komponen_id'])
            ->values()
            ->all();
        $audit = AuditLog::where('tindakan', 'rencana_aksi.ubah')->where('objek_id', $header->id)->orderByDesc('waktu')->orderByDesc('id')->first();

        $this->assertSame([
            'versi' => 2,
            'uraian' => null,
            'targets' => $matriks([
                ['periode1', 'pembilang', '1.000000000000'], ['periode1', 'penyebut', '4.000000000000'],
                ['periode2', 'pembilang', '2.000000000000'], ['periode2', 'penyebut', '4.000000000000'],
            ]),
            'status_alur' => 'draft',
            'snapshot_draf_id' => $fixture['snapshot']->id,
            'alasan_deviasi_pk' => null,
        ], $audit->nilai_lama);
        $this->assertSame([
            'versi' => 3,
            'uraian' => 'Uraian kedua',
            'targets' => $matriks([
                ['periode1', 'pembilang', '1.000000000000'], ['periode1', 'penyebut', '4.000000000000'],
                ['periode2', 'pembilang', '3.000000000000', 'naik'], ['periode2', 'penyebut', null],
            ]),
            'status_alur' => 'draft',
            'snapshot_draf_id' => $fixture['snapshot']->id,
            'alasan_deviasi_pk' => null,
        ], $audit->nilai_baru);
    }

    public function test_nonmanual_menyimpan_per_komponen_efektif_dan_nol_berbeda_dari_null(): void
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
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 0, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('0.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('komponen_id', $fixture['pembilang']->id)->sole()->getRawOriginal('nilai'));
        $this->assertSame('10.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('komponen_id', $fixture['penyebut']->id)->sole()->getRawOriginal('nilai'));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->fresh()->id}/target", [
            'expected_versi' => 2,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 5, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 0, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('0.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('komponen_id', $fixture['penyebut']->id)->sole()->getRawOriginal('nilai'));
        $this->assertDatabaseCount('rencana_aksi_target', 2);
    }

    public function test_periode_non_efektif_ditolak(): void
    {
        $fixture = $this->buatFixtureManual('manual', 2);
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
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors('targets');

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
    }

    public function test_kunci_periode_pic_ditolak_tetapi_perencanaan_sampai_penutupan(): void
    {
        $fixture = $this->buatFixtureManual();
        // Q41: tidak ada jendela penyusunan — pembuatan draf PIC selalu
        // diterima sebelum penutupan; kunci per periode dinilai saat simpan.
        $this->travelTo(now()->setDate(2026, 4, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        // Batas pengisian 31 Maret sudah lewat: PIC ditolak dengan pesan
        // spesifik per periode, Perencanaan dikecualikan sampai penutupan.
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors(['jendela' => 'Periode terkunci bagi PIC setelah batas pengisian lewat: Triwulan I (batas 2026-03-31).']);
        $this->assertSame(1, $header->fresh()->versi);

        $this->actingAs($fixture['perencanaan'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $header->fresh()->versi);
    }

    public function test_ensure_draft_menolak_grant_unit_yang_bukan_pic_efektif(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $bukanPic = $this->penggunaDenganPeran('pegawai');
        $this->grant($bukanPic, 'rencana_aksi:create', $fixture['unit']->id, $fixture['perencanaan']);

        $this->actingAs($bukanPic)->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasErrors('jendela');

        $this->assertDatabaseCount('rencana_aksi', 0);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.buat_ditolak')->exists());
    }

    public function test_ensure_draft_mengizinkan_pic_setelah_periode_terkunci_kunci_dinilai_saat_simpan(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 4, 10)->setTime(9, 0));

        // Pembuatan header tanpa dimensi periode tidak terikat kunci.
        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        // Kunci baru menolak saat simpan menyentuh periode terkunci.
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors('jendela');

        $this->assertSame(1, $header->fresh()->versi);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.buat')->exists());
    }

    public function test_ensure_draft_mengizinkan_pic_efektif_sebelum_penutupan(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();

        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $this->assertSame($fixture['pic']->id, $header->penanggung_jawab_id);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.buat')->where('objek_id', $header->id)->exists());
    }

    public function test_ensure_draft_perencanaan_global_mengikuti_jalur_resmi_setelah_periode_terkunci(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 4, 10)->setTime(9, 0));

        $this->actingAs($fixture['perencanaan'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();

        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $this->assertSame($fixture['pic']->id, $header->penanggung_jawab_id);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.buat')->where('objek_id', $header->id)->exists());
    }

    public function test_versi_stale_ditolak_409_dan_tanpa_izin_403(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $tanpaIzin = $this->penggunaDenganPeran('pegawai');
        $this->actingAs($tanpaIzin)->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertForbidden();
        $this->actingAs($tanpaIzin)->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 5, 'keterangan' => null],
            ],
        ])->assertForbidden();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 5, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 9, 'keterangan' => null],
            ],
        ])->assertRedirect()->assertSessionHasErrors('expected_versi');

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 9, 'keterangan' => null],
            ],
        ])->assertConflict()->assertJsonValidationErrors('expected_versi');
        $this->assertSame(2, $header->fresh()->versi);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah_ditolak')->where('objek_id', $header->id)->exists());
    }

    /**
     * Penolakan pembuatan untuk indikator arsip tetap teraudit, baik saat
     * ditolak guard arsip di Action maupun saat ditolak otorisasi di request.
     * UUID huruf besar sah sebagai input, tetapi audit mencatat bentuk
     * kanonis agar pencarian audit per indikator tetap menemukannya.
     */
    #[DataProvider('jalurTolakIndikatorArsip')]
    public function test_create_indikator_arsip_ditolak_tetap_diaudit(bool $berizin, bool $hurufBesar): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $fixture['indikator']->update(['status' => 'arsip']);
        $aktor = $berizin ? $fixture['pic'] : $this->penggunaDenganPeran('pegawai');

        $respons = $this->actingAs($aktor)->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $hurufBesar ? strtoupper($fixture['indikator']->id) : $fixture['indikator']->id,
            'tahun' => 2026,
        ]);

        $berizin ? $respons->assertSessionHasErrors('indikator_id') : $respons->assertForbidden();
        $this->assertDatabaseCount('rencana_aksi', 0);
        $audit = AuditLog::where('tindakan', 'rencana_aksi.buat_ditolak')->sole();
        $this->assertSame((string) $aktor->id, (string) $audit->actor_id);
        // Urutan kunci mengikuti normalisasi `jsonb` (panjang kunci lalu byte).
        $this->assertSame(['tahun' => 2026, 'indikator_id' => $fixture['indikator']->id], $audit->nilai_baru);
        if ($berizin) {
            $this->assertStringContainsString('diarsipkan', (string) $audit->alasan);
        }
    }

    /**
     * @return array<string, array{0: bool, 1: bool}>
     */
    public static function jalurTolakIndikatorArsip(): array
    {
        return [
            'ditolak guard arsip di Action' => [true, false],
            'ditolak guard arsip di Action, UUID huruf besar' => [true, true],
            'ditolak otorisasi di request' => [false, false],
            'ditolak otorisasi di request, UUID huruf besar' => [false, true],
        ];
    }
}
