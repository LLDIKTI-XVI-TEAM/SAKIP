<?php

namespace Tests\Feature\TargetRenstra;

use App\Actions\TargetRenstra\SimpanTargetRenstra;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\TargetRenstra;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\TargetRenstraFixtures;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class TargetRenstraTest extends TestCase
{
    use RefreshDatabase, TargetRenstraFixtures, TargetTahunanFixtures;

    private const FIELD_ASING = 'Permintaan memuat field yang tidak didukung.';

    private const MODE_REVISI = 'Target Renstra pada Renstra aktif hanya dapat diubah melalui Mode revisi.';

    /** @return array{0: User, 1: IndikatorKinerja} */
    private function penulis(): array
    {
        $aktor = $this->calendarActor(['indikator:read', 'target:update']);

        return [$aktor, $this->targetIndicator($aktor)];
    }

    private function auditDitolak(string $alasanPenolakan): Collection
    {
        return AuditLog::where('tindakan', 'target_renstra.simpan_ditolak')->get()
            ->filter(fn (AuditLog $audit): bool => $audit->nilai_baru['alasan_penolakan'] === $alasanPenolakan);
    }

    private function barisTarget(?string $nilai, int $tahun = 2026, ?string $indikatorId = null): string
    {
        $actor = $this->calendarActor();
        $indikatorId ??= $this->targetIndicator($actor)->id;
        $id = (string) Str::uuid();
        DB::table('target_renstra')->insert(['id' => $id, 'indikator_id' => $indikatorId, 'tahun' => $tahun, 'nilai' => $nilai, 'updated_by' => $actor->id, 'updated_at' => now()]);

        return $id;
    }

    #[DataProvider('nilaiTidakSah')]
    public function test_db_menolak_nilai_di_luar_domain_q33(string $nilai, string $sqlState): void
    {
        try {
            DB::transaction(fn () => $this->barisTarget($nilai));
            $this->fail('Database harus menolak nilai negatif dan non-finite.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
            $this->assertStringContainsString($sqlState === '23514' ? 'target_renstra_nilai_valid_check' : 'numeric field overflow', $exception->getMessage());
        }
        $this->assertSame(0, DB::table('target_renstra')->count());
    }

    public static function nilaiTidakSah(): array
    {
        return [['-1', '23514'], ['NaN', '23514'], ['Infinity', '22003'], ['-Infinity', '22003']];
    }

    public function test_db_menerima_null_nol_dan_kapasitas_penuh_serta_menolak_tahun_ganda(): void
    {
        foreach ([null, '0.000000000000', '999999999999999999.999999999999'] as $tahun => $nilai) {
            $id = $this->barisTarget($nilai, 2026 + $tahun);
            $this->assertSame($nilai, DB::table('target_renstra')->where('id', $id)->value('nilai'));
        }
        $indikatorId = DB::table('target_renstra')->where('tahun', 2026)->value('indikator_id');
        try {
            DB::transaction(fn () => $this->barisTarget('1', 2026, $indikatorId));
            $this->fail('Satu indikator hanya boleh punya satu target Renstra per tahun.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0]);
        }
    }

    public function test_rollback_migrasi_ditolak_bila_tabel_berisi(): void
    {
        $this->barisTarget('10');
        try {
            (require base_path('database/migrations/2026_10_17_000001_create_target_renstra_table.php'))->down();
            $this->fail('Rollback harus ditolak selama target Renstra tersimpan.');
        } catch (RuntimeException $exception) {
            $this->assertStringStartsWith('Rollback ditolak', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('target_renstra'));
    }

    #[DataProvider('izin')]
    public function test_akses_membutuhkan_baca_indikator_dan_ubah_target(array $izin, int $status): void
    {
        $aktor = $this->calendarActor($izin);
        $indikator = $this->targetIndicator($aktor);
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '1'])->assertStatus($status);
        if ($status === 403) {
            $this->assertSame(0, TargetRenstra::count());
            $audit = AuditLog::where('tindakan', 'target_renstra.simpan_ditolak')->sole();
            $this->assertSame(['indikator', $indikator->id, 'izin_ditolak'], [$audit->objek_tipe, $audit->objek_id, $audit->nilai_baru['alasan_penolakan']]);
        }
    }

    public static function izin(): array
    {
        return [[['indikator:read'], 403], [['target:update'], 403], [['indikator:read', 'target:update'], 302]];
    }

    public function test_action_langsung_menolak_aktor_tanpa_ubah_target(): void
    {
        $aktor = $this->calendarActor(['indikator:read']);
        $indikator = $this->targetIndicator($aktor);
        $this->assertThrows(fn () => app(SimpanTargetRenstra::class)->handle($aktor, $indikator->id,
            ['nilai' => [2026 => '1'], 'expected_state' => $this->targetRenstraState($indikator)]), AuthorizationException::class);
        $this->assertSame(0, TargetRenstra::count());
        $audit = $this->auditDitolak('izin_ditolak')->sole();
        $this->assertNotNull($audit->dasar_izin);
        $this->assertNotSame('diizinkan', $audit->dasar_izin['target:update']['keputusan']);
    }

    #[DataProvider('izinDitolak')]
    public function test_deny_eksplisit_menang_di_batas_mutasi(string $izin): void
    {
        [$aktor, $indikator] = $this->penulis();
        $state = $this->targetRenstraState($indikator);
        UserPermissionDeny::create(['user_id' => $aktor->id, 'permission_id' => Permission::where('kode', $izin)->sole()->id, 'alasan' => 'Fixture penolakan', 'ditetapkan_oleh' => $aktor->id]);
        $this->actingAs($aktor)->putJson("/perencanaan/indikator/{$indikator->id}/target-renstra", ['nilai' => [2026 => '1'], 'expected_state' => $state])->assertForbidden();
        // Deny juga menang saat Action dipanggil langsung, tanpa FormRequest.
        $this->assertThrows(fn () => app(SimpanTargetRenstra::class)->handle($aktor, $indikator->id, ['nilai' => [2026 => '1'], 'expected_state' => $state]), AuthorizationException::class);
        $this->assertSame(0, TargetRenstra::count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_renstra.simpan')->count());
        $ditolak = $this->auditDitolak('izin_ditolak');
        $this->assertCount(2, $ditolak);
        foreach ($ditolak as $audit) {
            $this->assertNotSame('diizinkan', $audit->dasar_izin[$izin]['keputusan']);
        }
    }

    public static function izinDitolak(): array
    {
        return [['indikator:read'], ['target:update']];
    }

    public function test_menyimpan_satu_nilai_per_tahun_dan_membedakan_nol_dari_kosong(): void
    {
        [$aktor, $indikator] = $this->penulis();
        $renstraId = $indikator->sasaranStrategis->renstra_id;
        $payload = [2028 => '75,50', 2026 => '0', 2027 => '150', 2029 => null];
        $this->simpanTargetRenstra($aktor, $indikator, $payload)
            ->assertRedirect(route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId]))
            ->assertSessionHas('inertia.flash_data.target_renstra', fn (array $outcome): bool => $outcome['tahun'] === [2026, 2027, 2028]
                && $outcome['indikator_id'] === $indikator->id && $outcome['renstra_id'] === $renstraId);
        $this->assertSame([2026 => '0', 2027 => '150', 2028 => '75.5'], $this->nilaiTersimpan($indikator));

        $this->simpanTargetRenstra($aktor, $indikator, $payload)->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.target_renstra', fn (array $outcome): bool => $outcome['tahun'] === []);
        $this->assertSame(3, TargetRenstra::count());
        $this->assertSame(3, AuditLog::where('tindakan', 'target_renstra.simpan')->count());
        $this->assertSame([$aktor->id], TargetRenstra::pluck('updated_by')->unique()->values()->all());
    }

    #[DataProvider('tahunTidakSah')]
    public function test_tahun_di_luar_rentang_atau_sebelum_berlaku_ditolak(int $tahun): void
    {
        [$aktor, $indikator] = $this->penulis();
        $this->simpanTargetRenstra($aktor, $indikator, [$tahun => '1'])
            ->assertSessionHasErrors(['nilai' => 'Tahun harus berada dalam rentang Renstra dan tidak sebelum indikator mulai berlaku.']);
        $this->assertSame(0, TargetRenstra::count());
        $this->assertCount(1, $this->auditDitolak('input_atau_state_tidak_valid'));
    }

    public static function tahunTidakSah(): array
    {
        return [[2024], [2025], [2030]];
    }

    public function test_format_dan_presisi_nilai_baru_ditolak(): void
    {
        [$aktor, $indikator] = $this->penulis();
        foreach (['-1', '1e3', 'NaN', 'Infinity', '1.234'] as $nilai) {
            $this->simpanTargetRenstra($aktor, $indikator, [2026 => $nilai])->assertSessionHasErrors(['nilai.2026']);
        }
        $this->actingAs($aktor)->putJson("/perencanaan/indikator/{$indikator->id}/target-renstra", ['nilai' => [2026 => 5], 'expected_state' => $this->targetRenstraState($indikator)])
            ->assertUnprocessable()->assertJsonValidationErrors(['nilai.2026']);
        $this->assertSame(0, TargetRenstra::count());

        // Nilai lama yang tidak berubah tidak dipaksa mengikuti presisi indikator.
        TargetRenstra::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'nilai' => '1.234', 'updated_by' => $aktor->id]);
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '1.234', 2027 => '2'])->assertSessionHasNoErrors();
        $this->assertSame([2026 => '1.234', 2027 => '2'], $this->nilaiTersimpan($indikator));
    }

    public function test_perubahan_tersimpan_tanpa_alasan_dan_teraudit_per_tahun(): void
    {
        [$aktor, $indikator] = $this->penulis();
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '10', 2027 => '20'])->assertSessionHasNoErrors();

        $sebelum = AuditLog::pluck('id');
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '10', 2027 => '20', 2028 => '30'])->assertSessionHasNoErrors();
        $pengisian = AuditLog::where('tindakan', 'target_renstra.simpan')->whereNotIn('id', $sebelum)->sole();
        $this->assertNull($pengisian->nilai_lama);
        // JSONB tidak mempertahankan urutan key.
        $this->assertEquals(['indikator_id' => $indikator->id, 'tahun' => 2028, 'nilai' => '30'], $pengisian->nilai_baru);

        $sebelum = AuditLog::pluck('id');
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '11', 2027 => null])->assertSessionHasNoErrors();
        $audit = AuditLog::where('tindakan', 'target_renstra.simpan')->whereNotIn('id', $sebelum)->get()->keyBy(fn (AuditLog $a): int => $a->nilai_baru['tahun']);
        $this->assertSame([2026, 2027], $audit->keys()->sort()->values()->all());
        $this->assertEquals(['indikator_id' => $indikator->id, 'tahun' => 2026, 'nilai' => '10'], $audit[2026]->nilai_lama);
        $this->assertSame('11', $audit[2026]->nilai_baru['nilai']);
        $this->assertSame('20', $audit[2027]->nilai_lama['nilai']);
        $this->assertNull($audit[2027]->nilai_baru['nilai']);
        foreach ($audit as $baris) {
            $this->assertSame(['target_renstra', $aktor->id], [$baris->objek_tipe, $baris->actor_id]);
            $this->assertArrayHasKey('indikator:read', $baris->dasar_izin);
            $this->assertArrayHasKey('target:update', $baris->dasar_izin);
            $this->assertSame('Pencatatan audit untuk tindakan target_renstra.simpan.', $baris->alasan);
        }
        $this->assertSame([2026 => '11', 2027 => null, 2028 => '30'], $this->nilaiTersimpan($indikator));

        $jumlah = AuditLog::where('tindakan', 'target_renstra.simpan')->count();
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '11,00'])->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.target_renstra', fn (array $outcome): bool => $outcome['tahun'] === []);
        $this->assertSame($jumlah, AuditLog::where('tindakan', 'target_renstra.simpan')->count());
    }

    public function test_state_basi_menghasilkan_409_sedangkan_edit_terbuka_saat_aktivasi_diarahkan_ke_mode_revisi(): void
    {
        [$aktor, $indikator] = $this->penulis();
        $lama = $this->targetRenstraState($indikator);
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '5'])->assertSessionHasNoErrors();
        $this->actingAs($aktor)->putJson("/perencanaan/indikator/{$indikator->id}/target-renstra", ['nilai' => [2026 => '6'], 'expected_state' => $lama])
            ->assertStatus(409)->assertJsonValidationErrors(['expected_state']);
        $this->assertCount(1, $this->auditDitolak('input_atau_state_tidak_valid'));
        $this->withHeader('X-Inertia', 'true');
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '6'], $lama)->assertStatus(303)->assertSessionHasErrors(['expected_state']);
        $this->flushHeaders();
        $this->assertSame([2026 => '5'], $this->nilaiTersimpan($indikator));

        // Status Renstra di luar token: edit yang terbuka sebelum aktivasi mendapat arahan Mode revisi, bukan 409.
        $draft = $this->targetRenstraState($indikator);
        $this->ubahStatusRenstra($indikator, Renstra::STATUS_AKTIF);
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '7'], $draft)->assertSessionHasErrors(['nilai' => self::MODE_REVISI]);
        $this->assertSame([2026 => '5'], $this->nilaiTersimpan($indikator));
    }

    public function test_renstra_aktif_menolak_simpan_langsung_dan_mengarahkan_ke_mode_revisi(): void
    {
        [$aktor, $indikator] = $this->penulis();
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '10'])->assertSessionHasNoErrors();
        $this->ubahStatusRenstra($indikator, Renstra::STATUS_AKTIF);

        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '12'])->assertSessionHasErrors(['nilai' => self::MODE_REVISI]);
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => null, 2027 => '5'])->assertSessionHasErrors(['nilai' => self::MODE_REVISI]);
        $this->actingAs($aktor)->putJson("/perencanaan/indikator/{$indikator->id}/target-renstra", ['nilai' => [2026 => '12'], 'expected_state' => $this->targetRenstraState($indikator)])
            ->assertStatus(422)->assertJsonValidationErrors(['nilai' => self::MODE_REVISI]);
        $this->assertSame([2026 => '10.000000000000'], DB::table('target_renstra')->where('indikator_id', $indikator->id)->pluck('nilai', 'tahun')->all());
        $this->assertCount(3, $this->auditDitolak('input_atau_state_tidak_valid'));

        // Kontrol positif: Renstra draft tetap disimpan langsung tanpa alasan (Q60).
        $this->ubahStatusRenstra($indikator, Renstra::STATUS_DRAFT);
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '12'])->assertSessionHasNoErrors();
        $this->assertSame([2026 => '12'], $this->nilaiTersimpan($indikator));
    }

    public function test_status_baca_saja_ditolak_termasuk_no_op(): void
    {
        [$aktor, $indikator] = $this->penulis();
        foreach ([Renstra::STATUS_NONAKTIF, Renstra::STATUS_DIARSIPKAN] as $status) {
            $this->ubahStatusRenstra($indikator, $status);
            $this->simpanTargetRenstra($aktor, $indikator, [2026 => null])->assertSessionHasErrors(['nilai' => 'Renstra nonaktif atau diarsipkan hanya dapat dibaca.']);
        }
        $this->ubahStatusRenstra($indikator, Renstra::STATUS_DRAFT);
        $indikator->update(['status' => IndikatorKinerja::STATUS_ARSIP]);
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => null])->assertSessionHasErrors(['nilai' => 'Indikator arsip hanya dapat dibaca.']);
        $this->assertSame(0, TargetRenstra::count());
        $this->assertCount(3, $this->auditDitolak('input_atau_state_tidak_valid'));
    }

    public function test_field_asing_termasuk_alasan_ditolak_dan_diaudit(): void
    {
        [$aktor, $indikator] = $this->penulis();
        // Key literal `nilai.*` adalah nama aturan wildcard, bukan field kontrak.
        foreach ([['alasan' => 'Revisi'], ['tahun' => 2026], ['nilai.*' => 'x']] as $extra) {
            $this->simpanTargetRenstra($aktor, $indikator, [2026 => '1'], extra: $extra)->assertSessionHasErrors(['nilai' => self::FIELD_ASING]);
        }
        $this->assertCount(3, $this->auditDitolak('input_tidak_valid'));

        // HTTP selalu tertangkap FormRequest lebih dulu; Action tetap menolak sebagai batas mutasi.
        foreach (['alasan', 'nilai.*'] as $field) {
            try {
                app(SimpanTargetRenstra::class)->handle($aktor, $indikator->id, ['nilai' => [2026 => '1'], 'expected_state' => $this->targetRenstraState($indikator), $field => 'x']);
                $this->fail("Action harus menolak field {$field}.");
            } catch (ValidationException $exception) {
                $this->assertSame([self::FIELD_ASING], $exception->errors()['nilai']);
            }
        }
        $this->assertSame(0, TargetRenstra::count());
        $this->assertCount(2, $this->auditDitolak('input_atau_state_tidak_valid'));
    }

    public function test_halaman_sasaran_indikator_memuat_target_renstra_dan_capability(): void
    {
        [$aktor, $indikator] = $this->penulis();
        TargetRenstra::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'nilai' => '0', 'updated_by' => $aktor->id]);
        TargetRenstra::create(['indikator_id' => $indikator->id, 'tahun' => 2027, 'nilai' => null, 'updated_by' => $aktor->id]);
        $halaman = fn (User $pembaca) => $this->actingAs($pembaca)->get('/perencanaan/sasaran-indikator?renstra_id='.$indikator->sasaranStrategis->renstra_id);

        $halaman($aktor)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('renstras.0.status', 'draft')
            ->where('sasarans.0.indikator_kinerjas.0.target_renstra', [['tahun' => 2026, 'nilai' => '0'], ['tahun' => 2027, 'nilai' => null]])
            ->where('sasarans.0.indikator_kinerjas.0.target_renstra_state', $this->targetRenstraState($indikator))
            ->where('can.target_update', true));

        $halaman($this->calendarActor(['indikator:read']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can.target_update', false));
    }

    public function test_kegagalan_audit_membatalkan_simpan(): void
    {
        [$aktor, $indikator] = $this->penulis();
        $data = ['nilai' => [2026 => '1'], 'expected_state' => $this->targetRenstraState($indikator)];
        $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andThrow(new RuntimeException('Audit unavailable'));
        try {
            app(SimpanTargetRenstra::class)->handle($aktor, $indikator->id, $data);
            $this->fail('Kegagalan audit harus diteruskan.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertSame(0, TargetRenstra::count());
    }
}
