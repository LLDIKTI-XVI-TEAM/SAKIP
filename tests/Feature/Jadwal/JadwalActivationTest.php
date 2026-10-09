<?php

namespace Tests\Feature\Jadwal;

use App\Actions\Audit\WriteAuditLog;
use App\Actions\Jadwal\ActivateJadwal;
use App\Actions\Jadwal\SaveJadwalDraft;
use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JadwalTahunan;
use App\Models\TargetKinerja;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

class JadwalActivationTest extends TestCase
{
    use JadwalActivationFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-07 10:00:00.123456', 'Asia/Makassar'));
    }

    private function activate(array $payload, ?string $id = null): TestResponse
    {
        return $this->from('/jadwal/'.$this->ready['jadwal']->id)->post('/jadwal/'.($id ?? $this->ready['jadwal']->id).'/aktivasi', $payload);
    }

    private function audits(string $tindakan): int
    {
        return AuditLog::where('tindakan', $tindakan)->count();
    }

    private function assertStillDraft(int $revisi = 1): void
    {
        $jadwal = JadwalTahunan::findOrFail($this->ready['jadwal']->id);
        $this->assertSame(['draft', null, null, $revisi], [$jadwal->status, $jadwal->activated_at, $jadwal->renstra_pk_id, $jadwal->revisi]);
    }

    public function test_activation_freezes_sources_and_returns_outcome_bound_to_operation(): void
    {
        $this->readyJadwal(withIndicator: false);
        $first = $this->calendarMaster(1, false);
        $window = fn (string $from, string $to, string $rFrom, string $rTo): array => ['pengisian_mulai' => $from, 'pengisian_selesai' => $to, 'reviu_mulai' => $rFrom, 'reviu_selesai' => $rTo];
        $payload = $this->calendarPayload($this->ready['renstra'], $this->ready['periode']);
        $payload['periode'][] = ['periode_id' => $first->id, 'periode_revisi' => $first->revisi, ...$window('2026-04-01', '2026-04-07', '2026-04-08', '2026-04-14')];
        app(SaveJadwalDraft::class)->handle($this->ready['owner'], [...$payload, 'revisi' => 1], $this->ready['jadwal']);
        $manual = $this->eligibleIndicator(target: '76.25', baseline: null);
        $ratio = $this->eligibleIndicator(target: '0', baseline: '74.2', tipe: 'rasio_persen', komponen: [['kode' => 'A', 'peran' => 'pembilang'], ['kode' => 'B', 'peran' => 'penyebut', 'bobot' => '2.5']]);
        $actor = $this->activator();
        $data = $this->activationPayload();

        $this->actingAs($actor)->activate($data)->assertRedirect('/jadwal/'.$this->ready['jadwal']->id)->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.jadwal_aktivasi', fn (array $outcome): bool => $outcome['operation_id'] === $data['operation_id']
                && $outcome['jadwal_id'] === $this->ready['jadwal']->id && $outcome['status'] === 'aktif' && $outcome['changed'] === true
                && $outcome['revisi'] === 3 && $outcome['snapshot_created_count'] === 2 && $outcome['activated_at'] === now()->startOfSecond()->toIso8601ZuluString());

        $jadwal = JadwalTahunan::findOrFail($this->ready['jadwal']->id);
        $this->assertSame(['aktif', $this->ready['pk']->id, 3], [$jadwal->status, $jadwal->renstra_pk_id, $jadwal->revisi]);
        $this->assertSame(now()->format('Y-m-d H:i:s'), Carbon::parse($jadwal->activated_at)->format('Y-m-d H:i:s'));
        $this->assertSame(['2026-01-05', '2027-01-19'], [$jadwal->rencana_aksi_mulai->format('Y-m-d'), $jadwal->penutupan->format('Y-m-d')]);

        $rows = DB::table('jadwal_snapshot')->where('jadwal_id', $jadwal->id)->get()->keyBy('indikator_id');
        $this->assertCount(2, $rows);
        $manualRow = (array) $rows[$manual->id];
        unset($manualRow['id']);
        $this->assertSame(['jadwal_id' => $jadwal->id, 'indikator_id' => $manual->id, 'nomor_versi' => 1, 'menggantikan_id' => null,
            'alasan_koreksi' => null, 'rujukan_koreksi' => null, 'periode_mulai_id' => $first->id, 'unit_id' => $manual->unit_id,
            'nama' => 'Indikator fixture', 'definisi' => 'Definisi operasional fixture', 'satuan' => 'persen', 'presisi' => 2,
            'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => '76.250000000000', 'baseline' => null,
            'komposisi_final' => true], $manualRow);
        $this->assertSame(['0.000000000000', '74.200000000000'], [$rows[$ratio->id]->target, $rows[$ratio->id]->baseline]);
        $this->assertSame(0, JadwalSnapshotKomponen::where('jadwal_snapshot_id', $rows[$manual->id]->id)->count());
        $children = DB::table('jadwal_snapshot_komponen')->where('jadwal_snapshot_id', $rows[$ratio->id]->id)->orderBy('urutan')->get(['komponen_id', 'kode', 'label', 'peran', 'bobot', 'urutan']);
        $this->assertSame([['A', 'Komponen A', 'pembilang', '1.000000000000', 1], ['B', 'Komponen B', 'penyebut', '2.500000000000', 2]],
            $children->map(fn ($row): array => [$row->kode, $row->label, $row->peran, $row->bobot, $row->urutan])->all());
        $this->assertSame($ratio->komponen()->orderBy('urutan')->pluck('id')->all(), $children->pluck('komponen_id')->all());

        $audit = AuditLog::where('tindakan', 'jadwal.aktivasi')->sole();
        $this->assertSame([$actor->id, 'PK dan target tahunan sudah disahkan.'], [$audit->actor_id, $audit->alasan]);
        $this->assertSame(['draft', 2], [$audit->nilai_lama['status'], $audit->nilai_lama['revisi']]);
        $this->assertSame(['aktif', 3, 2, false], [$audit->nilai_baru['status'], $audit->nilai_baru['revisi'], $audit->nilai_baru['snapshot_created_count'], $audit->nilai_baru['pengecualian_lampiran_pk']]);
        $this->assertEqualsCanonicalizing($rows->pluck('id')->all(), $audit->nilai_baru['snapshot_ids']);
        $this->assertSame(2, $this->audits('jadwal_snapshot.buat'));
        $this->assertSame(0, $this->audits('jadwal.aktivasi_ditolak'));

        // Perubahan master berikutnya tidak menjalar ke nilai beku.
        $before = $this->snapshotFingerprint();
        TargetKinerja::where('indikator_kinerja_id', $manual->id)->update(['target_tahunan' => '90']);
        DB::table('indikator_komponen')->where('indikator_id', $ratio->id)->update(['bobot' => '9', 'label' => 'Ubah']);
        IndikatorKinerja::whereKey($manual->id)->update(['nama' => 'Nama baru', 'definisi_operasional' => 'Baru']);
        $this->assertSame($before, $this->snapshotFingerprint());
    }

    public function test_post_rechecks_every_prerequisite_even_when_preview_allowed(): void
    {
        $this->readyJadwal();
        $actor = $this->activator();
        $jadwalId = $this->ready['jadwal']->id;
        $this->actingAs($actor)->getJson("/jadwal/$jadwalId/kesiapan-aktivasi")->assertJsonPath('allowed', true);
        $breakers = [
            'G1' => [fn () => DB::table('renstra_pk')->where('id', $this->ready['pk']->id)->update(['tahun' => 2027]), fn () => DB::table('renstra_pk')->where('id', $this->ready['pk']->id)->update(['tahun' => 2026])],
            'G2' => [fn () => TargetKinerja::query()->update(['target_tahunan' => null]), fn () => TargetKinerja::query()->update(['target_tahunan' => '1'])],
            'G3' => [fn () => $this->ready['renstra']->update(['tahun_mulai' => 2027, 'tahun_selesai' => 2029]), fn () => $this->ready['renstra']->update(['tahun_mulai' => 2025])],
            'G4' => [fn () => Berkas::query()->delete(), fn () => Berkas::withTrashed()->restore()],
            'renstra' => [fn () => $this->ready['renstra']->update(['status' => 'draft']), fn () => $this->ready['renstra']->update(['status' => 'aktif'])],
            'periode' => [fn () => $this->ready['periode']->update(['aktif' => false]), fn () => $this->ready['periode']->update(['aktif' => true])],
            'indikator' => [fn () => IndikatorKinerja::query()->update(['status' => 'arsip']), fn () => IndikatorKinerja::query()->update(['status' => 'aktif'])],
            'penutupan' => [fn () => $this->travelTo(Carbon::parse('2027-01-20 00:00', 'Asia/Makassar')), fn () => $this->travelTo(Carbon::parse('2026-10-07 10:00', 'Asia/Makassar'))],
        ];
        foreach ($breakers as $name => [$break, $restore]) {
            $break();
            $this->activate($this->activationPayload())->assertSessionHasErrors('aktivasi');
            $this->assertStillDraft();
            $this->assertSame(0, JadwalSnapshot::count(), $name);
            $restore();
        }
        $this->assertSame(count($breakers), $this->audits('jadwal.aktivasi_ditolak'));
        $this->activate($this->activationPayload())->assertSessionHasNoErrors();
        $this->assertSame('aktif', JadwalTahunan::findOrFail($jadwalId)->status);
    }

    public function test_input_contract_is_validated_with_single_rejection_audit(): void
    {
        $this->readyJadwal();
        $this->actingAs($this->activator());
        $cases = [
            'alasan' => ['alasan' => '   '],
            'alasan ' => ['alasan' => "Alasan\x07kontrol"],
            'alasan  ' => ['alasan' => str_repeat('a', 1001)],
            'operation_id' => ['operation_id' => 'bukan-uuid'],
            'expected_revisi' => ['expected_revisi' => 0],
            'jadwal' => ['status' => 'aktif'],
        ];
        foreach ($cases as $field => $override) {
            $this->activate($this->activationPayload($override))->assertSessionHasErrors(trim($field));
        }
        $this->activate(array_diff_key($this->activationPayload(), ['alasan' => true]))->assertSessionHasErrors('alasan');
        $this->assertStillDraft();
        $this->assertSame(count($cases) + 1, $this->audits('jadwal.aktivasi_ditolak'));
        $this->assertSame(0, $this->audits('jadwal.aktivasi'));
    }

    public function test_authorization_precedes_lookup_and_live_recheck_records_actual_decision(): void
    {
        $this->readyJadwal();
        $none = $this->activator([]);
        $this->actingAs($none)->activate($this->activationPayload())->assertForbidden();
        $this->activate($this->activationPayload(), (string) Str::uuid())->assertForbidden();
        $this->assertSame(2, $this->audits('jadwal.aktivasi_ditolak'));
        $this->actingAs($this->activator())->activate($this->activationPayload(), (string) Str::uuid())->assertNotFound();

        $revoked = $this->activator();
        $this->denyPermission($revoked, 'jadwal:aktivasi');
        try {
            app(ActivateJadwal::class)->handle($revoked, $this->ready['jadwal']->id, $this->activationPayload());
            $this->fail('Aktor dengan deny harus ditolak.');
        } catch (AuthorizationException) {
        }
        $audit = AuditLog::where('tindakan', 'jadwal.aktivasi_ditolak')->where('actor_id', $revoked->id)->sole();
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
        $this->assertStillDraft();
    }

    public function test_stale_revision_and_locked_states_are_rejected_without_reopen(): void
    {
        $this->readyJadwal();
        $this->actingAs($this->activator());
        $stale = $this->activationPayload();
        app(SaveJadwalDraft::class)->handle($this->ready['owner'], [...$this->calendarPayload($this->ready['renstra'], $this->ready['periode']), 'penutupan' => '2027-01-20', 'revisi' => 1], $this->ready['jadwal']);
        $this->activate($stale)->assertSessionHasErrors(['aktivasi' => 'Jadwal sudah berubah. Muat ulang detail jadwal sebelum mengaktifkan.']);
        $this->assertStillDraft(2);

        foreach ([['status' => 'ditutup', 'activated_at' => now(), 'closed_at' => now()], ['status' => 'draft', 'activated_at' => now()]] as $state) {
            JadwalTahunan::whereKey($this->ready['jadwal']->id)->update($state);
            $this->activate($this->activationPayload())->assertSessionHasErrors('aktivasi');
            $this->assertSame($state['status'], JadwalTahunan::findOrFail($this->ready['jadwal']->id)->status);
        }
        $this->assertSame(0, JadwalSnapshot::count());
    }

    public function test_replay_on_active_schedule_is_idempotent_noop(): void
    {
        $this->readyJadwal();
        $actor = $this->activator();
        $original = $this->activationPayload();
        $this->actingAs($actor)->activate($original)->assertSessionHasNoErrors();
        $jadwal = JadwalTahunan::findOrFail($this->ready['jadwal']->id);
        $before = [$this->snapshotFingerprint(), $jadwal->revisi, $jadwal->activated_at, AuditLog::count()];
        $this->travel(5)->minutes();
        $replay = [...$original, 'operation_id' => (string) Str::uuid()];
        $this->activate($replay)->assertSessionHasNoErrors()->assertSessionHas('inertia.flash_data.jadwal_aktivasi',
            fn (array $outcome): bool => $outcome['operation_id'] === $replay['operation_id'] && $outcome['changed'] === false && $outcome['snapshot_created_count'] === 0);
        $jadwal->refresh();
        $this->assertSame($before, [$this->snapshotFingerprint(), $jadwal->revisi, $jadwal->activated_at, AuditLog::count()]);
    }

    public function test_existing_draft_snapshots_are_kept_and_only_missing_pairs_are_created(): void
    {
        $this->readyJadwal(withIndicator: false);
        $kept = $this->eligibleIndicator(target: '5', tipe: 'penjumlahan', komponen: [['kode' => 'X', 'peran' => 'penjumlah']]);
        $missing = $this->eligibleIndicator(target: '7');
        $old = JadwalSnapshot::create(['jadwal_id' => $this->ready['jadwal']->id, 'indikator_id' => $kept->id, 'nomor_versi' => 2, 'periode_mulai_id' => $this->ready['periode']->id,
            'unit_id' => $kept->unit_id, 'nama' => 'Nama lama', 'definisi' => null, 'satuan' => 'lama', 'presisi' => 0, 'desimal_tampilan' => 0, 'arah' => 'turun_baik',
            'tipe_perhitungan' => 'penjumlahan', 'target' => '1', 'baseline' => null]);
        JadwalSnapshotKomponen::create(['jadwal_snapshot_id' => $old->id, 'komponen_id' => $kept->komponen()->value('id'), 'kode' => 'X', 'label' => 'Label lama', 'peran' => 'penjumlah', 'bobot' => '3', 'urutan' => 9]);
        $before = $this->snapshotFingerprint();

        $this->actingAs($this->activator())->getJson('/jadwal/'.$this->ready['jadwal']->id.'/kesiapan-aktivasi')
            ->assertJsonPath('counts.snapshot_existing', 1)->assertJsonPath('counts.snapshot_baru', 1)->assertJsonPath('allowed', true);
        $this->activate($this->activationPayload())->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.jadwal_aktivasi', fn (array $outcome): bool => $outcome['changed'] && $outcome['snapshot_created_count'] === 1);

        [$parents, $children] = $this->snapshotFingerprint();
        [$parentSebelum, $anakSebelum] = $this->tanpaFlagFinalisasi($before);
        [$parentSesudah, $anakSesudah] = $this->tanpaFlagFinalisasi([$parents, $children]);
        // Baris existing tidak diganti: seluruh kolom konten identik. Satu-satunya perubahan
        // yang dizinkan adalah flag finalisasi karena aktivasi membekukan komposisi terbit,
        // sehingga snapshot lama tidak lagi dapat disisipi komponen.
        foreach ([...$parentSebelum, ...$anakSebelum] as $row) {
            $this->assertContains($row, [...$parentSesudah, ...$anakSesudah]);
        }
        $this->assertTrue((bool) $old->refresh()->komposisi_final);
        $this->assertSame(0, JadwalSnapshot::where('komposisi_final', false)->count());
        $this->assertSame(1, JadwalSnapshot::where('indikator_id', $kept->id)->count());
        $this->assertSame(1, JadwalSnapshot::where('indikator_id', $missing->id)->where('nomor_versi', 1)->count());
        $this->assertSame('aktif', JadwalTahunan::findOrFail($this->ready['jadwal']->id)->status);
    }

    public function test_complete_existing_snapshots_still_activate_with_zero_created(): void
    {
        $this->readyJadwal();
        $indikator = IndikatorKinerja::sole();
        JadwalSnapshot::create(['jadwal_id' => $this->ready['jadwal']->id, 'indikator_id' => $indikator->id, 'nomor_versi' => 1, 'periode_mulai_id' => $this->ready['periode']->id,
            'unit_id' => $indikator->unit_id, 'nama' => 'Nama lama', 'satuan' => 'lama', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => '1']);
        $before = $this->snapshotFingerprint();
        $this->actingAs($this->activator())->activate($this->activationPayload())->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.jadwal_aktivasi', fn (array $outcome): bool => $outcome['changed'] === true && $outcome['snapshot_created_count'] === 0);
        $this->assertSame($this->tanpaFlagFinalisasi($before), $this->tanpaFlagFinalisasi($this->snapshotFingerprint()));
        // Snapshot existing tidak dibuat ulang, tetapi aktivasi tetap membekukannya.
        $this->assertTrue((bool) JadwalSnapshot::sole()->komposisi_final);
        $this->assertSame(1, $this->audits('jadwal.aktivasi'));
        $this->assertSame(0, $this->audits('jadwal_snapshot.buat'));
    }

    public function test_snapshot_hasil_aktivasi_final_sehingga_komposisi_beku(): void
    {
        $this->readyJadwal(withIndicator: false);
        $ratio = $this->eligibleIndicator(target: '0', baseline: '74.2', tipe: 'rasio_persen',
            komponen: [['kode' => 'A', 'peran' => 'pembilang'], ['kode' => 'B', 'peran' => 'penyebut', 'bobot' => '2.5']]);
        $manual = $this->eligibleIndicator(target: '76.25');

        $this->actingAs($this->activator())->activate($this->activationPayload())->assertSessionHasNoErrors();

        // Seluruh snapshot hasil aktivasi terbit dalam keadaan sudah final.
        $this->assertSame(0, JadwalSnapshot::where('komposisi_final', false)->count());
        $this->assertTrue(JadwalSnapshot::where('indikator_id', $ratio->id)->sole()->komposisi_final);
        $this->assertTrue(JadwalSnapshot::where('indikator_id', $manual->id)->sole()->komposisi_final);
        $this->assertSame(2, AuditLog::where('tindakan', 'jadwal_snapshot.buat')->where('nilai_baru->komposisi_final', true)->count());

        // Komposisi terbit benar-benar beku: komponen tambahan ditolak guard insert komponen,
        // sehingga rumus yang dilihat pembaca RA tidak dapat berubah tanpa versi baru.
        // Percobaan dibungkus savepoint agar transaksi RefreshDatabase tidak ter-abort
        // setelah penolakan trigger, sehingga jumlah komponen tetap dapat diverifikasi.
        $snapshot = JadwalSnapshot::where('indikator_id', $ratio->id)->sole();
        try {
            DB::transaction(function () use ($snapshot, $ratio): void {
                JadwalSnapshotKomponen::create(['jadwal_snapshot_id' => $snapshot->id, 'komponen_id' => $ratio->komponen()->value('id'),
                    'kode' => 'C', 'label' => 'Sisipan', 'peran' => 'pembilang', 'bobot' => '1', 'urutan' => 3]);
            });
            $this->fail('Komposisi terbit harus menolak komponen tambahan.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0] ?? null);
        }
        $this->assertSame(2, JadwalSnapshotKomponen::where('jadwal_snapshot_id', $snapshot->id)->count());
    }

    public function test_attachment_exception_marks_pk_once_and_get_never_marks(): void
    {
        $this->readyJadwal();
        Berkas::query()->delete();
        $this->uploadSetting('false');
        $actor = $this->activator();
        $marker = fn (): int => AuditLog::where('tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')->where('objek_id', $this->ready['pk']->id)->count();
        $this->actingAs($actor)->getJson('/jadwal/'.$this->ready['jadwal']->id.'/kesiapan-aktivasi')->assertJsonPath('gates.3.status', 'pengecualian');
        $this->assertSame(0, $marker());
        $this->activate($this->activationPayload())->assertSessionHasNoErrors();
        $this->assertSame(1, $marker());
        $this->assertTrue(AuditLog::where('tindakan', 'jadwal.aktivasi')->sole()->nilai_baru['pengecualian_lampiran_pk']);
    }

    public function test_existing_active_marker_is_not_duplicated_and_invalid_setting_does_not_bypass(): void
    {
        $this->readyJadwal();
        Berkas::query()->delete();
        $actor = $this->activator();
        $this->uploadSetting('0');
        $this->actingAs($actor)->activate($this->activationPayload())->assertSessionHasErrors('aktivasi');
        $this->uploadSetting(null);
        $this->activate($this->activationPayload())->assertSessionHasErrors('aktivasi');
        $this->uploadSetting('false');
        app(AuditLogger::class)->catat(actor: $this->ready['owner'], tindakan: 'berkas.tandai_tidak_dapat_dipenuhi', objekTipe: 'renstra_pk', objekId: $this->ready['pk']->id,
            nilaiBaru: ['status_gerbang' => 'tidak_dapat_dipenuhi', 'gerbang' => 'lampiran_pk'], alasan: 'Fixture penanda');
        $this->activate($this->activationPayload())->assertSessionHasNoErrors();
        $this->assertSame(1, AuditLog::where('tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')->count());
    }

    public function test_failure_in_child_or_success_audit_rolls_back_this_transaction_only(): void
    {
        $this->readyJadwal();
        Berkas::query()->delete();
        $this->uploadSetting('false');
        $this->eligibleIndicator(tipe: 'penjumlahan', komponen: [['kode' => 'X', 'peran' => 'penjumlah']]);
        $kept = $this->eligibleIndicator();
        JadwalSnapshot::create(['jadwal_id' => $this->ready['jadwal']->id, 'indikator_id' => $kept->id, 'nomor_versi' => 1, 'periode_mulai_id' => $this->ready['periode']->id,
            'unit_id' => $kept->unit_id, 'nama' => 'Lama', 'satuan' => 'lama', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => '1']);
        $before = [$this->snapshotFingerprint(), AuditLog::count()];
        $actor = $this->activator();

        // Failpoint di level SQL karena komponen ditulis dengan batch insert tanpa event model.
        $failComponents = true;
        DB::connection()->beforeExecuting(function (string $sql) use (&$failComponents): void {
            if ($failComponents && str_starts_with($sql, 'insert into "jadwal_snapshot_komponen"')) {
                throw new RuntimeException('failpoint komponen');
            }
        });
        $this->assertRollback(fn () => app(ActivateJadwal::class)->handle($actor, $this->ready['jadwal']->id, $this->activationPayload()), 'failpoint komponen', $before);
        $failComponents = false;

        $this->app->instance(AuditLogger::class, new class(app(WriteAuditLog::class)) extends AuditLogger
        {
            public function catat(User $actor, string $tindakan, string $objekTipe, string $objekId, ?array $nilaiLama = null, ?array $nilaiBaru = null, ?string $alasan = null, ?array $dasarIzin = null): AuditLog
            {
                throw_if($tindakan === 'jadwal.aktivasi', new RuntimeException('failpoint audit'));

                return parent::catat($actor, $tindakan, $objekTipe, $objekId, $nilaiLama, $nilaiBaru, $alasan, $dasarIzin);
            }
        });
        $this->assertRollback(fn () => app(ActivateJadwal::class)->handle($actor, $this->ready['jadwal']->id, $this->activationPayload()), 'failpoint audit', $before);
    }

    private function assertRollback(callable $activate, string $message, array $before): void
    {
        try {
            $activate();
            $this->fail('Failpoint harus menggagalkan aktivasi.');
        } catch (RuntimeException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
        $this->assertStillDraft();
        $this->assertSame($before, [$this->snapshotFingerprint(), AuditLog::count()]);
    }

    public function test_validation_exception_from_action_is_not_caught_as_success(): void
    {
        $this->readyJadwal(withIndicator: false);
        $this->expectException(ValidationException::class);
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
    }

    public function test_inconsistent_source_blocks_only_pairs_that_would_be_copied(): void
    {
        $this->readyJadwal();
        $long = $this->eligibleIndicator(nama: str_repeat('n', 256));
        $this->actingAs($this->activator());
        $this->getJson('/jadwal/'.$this->ready['jadwal']->id.'/kesiapan-aktivasi')->assertJsonPath('allowed', false);
        JadwalSnapshot::create(['jadwal_id' => $this->ready['jadwal']->id, 'indikator_id' => $long->id, 'nomor_versi' => 1, 'periode_mulai_id' => $this->ready['periode']->id,
            'unit_id' => $long->unit_id, 'nama' => 'Nama pendek lama', 'satuan' => 'persen', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => '1']);
        $this->activate($this->activationPayload())->assertSessionHasNoErrors();
        $this->assertSame('Nama pendek lama', JadwalSnapshot::where('indikator_id', $long->id)->sole()->nama);
    }

    public function test_lock_conflict_becomes_reload_rejection_with_audit(): void
    {
        $this->readyJadwal();
        $injected = false;
        DB::connection()->beforeExecuting(function (string $sql) use (&$injected): void {
            if (! $injected && str_contains($sql, 'from "renstras"') && str_contains($sql, 'for update')) {
                $injected = true;
                $pdo = new \PDOException('deadlock detected');
                $pdo->errorInfo = ['40P01', 7, 'deadlock detected'];
                throw new QueryException('pgsql', $sql, [], $pdo);
            }
        });
        try {
            app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
            $this->fail('Deadlock harus menjadi penolakan konflik.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Muat ulang detail jadwal', $exception->errors()['aktivasi'][0]);
        }
        $this->assertStillDraft();
        $this->assertSame(1, $this->audits('jadwal.aktivasi_ditolak'));
    }
}
