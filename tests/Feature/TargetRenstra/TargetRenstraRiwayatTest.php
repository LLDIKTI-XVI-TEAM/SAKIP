<?php

namespace Tests\Feature\TargetRenstra;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\TargetRenstra;
use App\Models\User;
use App\Models\UserPermissionDeny;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\TargetRenstraFixtures;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class TargetRenstraRiwayatTest extends TestCase
{
    use RefreshDatabase, TargetRenstraFixtures, TargetTahunanFixtures;

    private function riwayat(User $pembaca, string $indikatorId): TestResponse
    {
        return $this->actingAs($pembaca)->getJson("/perencanaan/indikator/{$indikatorId}/target-renstra/riwayat");
    }

    /** @return array{0: User, 1: IndikatorKinerja} */
    private function indikatorDenganRiwayat(): array
    {
        $penulis = $this->calendarActor(['indikator:read', 'target:update']);
        $indikator = $this->targetIndicator($penulis);
        $this->simpanTargetRenstra($penulis, $indikator, [2026 => '10'])->assertSessionHasNoErrors();
        $this->travel(1)->minutes();
        $this->ubahStatusRenstra($indikator, Renstra::STATUS_AKTIF);
        $this->simpanTargetRenstra($penulis, $indikator, [2026 => '12'])->assertSessionHasNoErrors();
        $this->simpanTargetRenstra($penulis, $indikator, [2026 => '13'], extra: ['alasan' => 'Revisi'])->assertSessionHasErrors(['nilai']);

        return [$penulis, $indikator];
    }

    public function test_riwayat_memuat_semua_simpan_terbaru_lebih_dulu_tanpa_data_sensitif(): void
    {
        [$penulis, $indikator] = $this->indikatorDenganRiwayat();
        // Role bersama disinkron ulang setelah semua tulisan: pembaca hanya memegang indikator:read.
        $pembaca = $this->calendarActor(['indikator:read']);
        $response = $this->riwayat($pembaca, $indikator->id)->assertOk()->assertJsonCount(2, 'items')->assertJsonPath('terpotong', false);
        $items = $response->json('items');
        $this->assertSame(['waktu', 'aktor', 'tahun', 'nilai_lama', 'nilai_baru'], array_keys($items[0]));
        $this->assertSame(['aktor' => $penulis->nama, 'tahun' => 2026, 'nilai_lama' => '10', 'nilai_baru' => '12'], array_diff_key($items[0], ['waktu' => true]));
        $this->assertSame([null, '10'], [$items[1]['nilai_lama'], $items[1]['nilai_baru']]);
        foreach ([$penulis->email, 'dasar_izin', 'Pencatatan audit untuk tindakan', 'alasan'] as $bocor) {
            $this->assertStringNotContainsString($bocor, $response->getContent());
        }
    }

    public function test_riwayat_tetap_terbuka_saat_audit_read_ditolak(): void
    {
        [, $indikator] = $this->indikatorDenganRiwayat();
        $pembaca = $this->calendarActor(['indikator:read', 'audit:read']);
        UserPermissionDeny::create(['user_id' => $pembaca->id, 'permission_id' => Permission::where('kode', 'audit:read')->sole()->id, 'alasan' => 'Fixture penolakan', 'ditetapkan_oleh' => $pembaca->id]);
        $this->riwayat($pembaca, $indikator->id)->assertOk()->assertJsonCount(2, 'items');
    }

    public function test_riwayat_menolak_tanpa_baca_indikator_dan_indikator_tidak_dikenal(): void
    {
        $penulis = $this->calendarActor(['indikator:read']);
        $indikator = $this->targetIndicator($penulis);
        $this->riwayat($penulis, (string) Str::uuid())->assertNotFound();
        $this->riwayat($this->calendarActor([]), $indikator->id)->assertForbidden();
    }

    public function test_riwayat_dibatasi_seratus_entri_terbaru(): void
    {
        $aktor = $this->calendarActor(['indikator:read']);
        $indikator = $this->targetIndicator($aktor);
        $baris = TargetRenstra::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'nilai' => '1', 'updated_by' => $aktor->id]);
        foreach (range(0, 100) as $i) {
            // UUID v4 acak: urutan id tidak sama dengan urutan waktu.
            AuditLog::create(['id' => (string) Str::uuid(), 'tindakan' => 'target_renstra.simpan', 'objek_tipe' => 'target_renstra', 'objek_id' => $baris->id,
                'actor_id' => $aktor->id, 'actor_type' => 'user', 'sumber' => 'manual', 'waktu' => now()->subMinutes($i),
                'nilai_baru' => ['indikator_id' => $indikator->id, 'tahun' => 2026, 'nilai' => (string) $i]]);
        }
        $response = $this->riwayat($aktor, $indikator->id)->assertOk()->assertJsonCount(100, 'items')->assertJsonPath('terpotong', true)->assertJsonPath('items.0.nilai_baru', '0');
        $waktu = array_column($response->json('items'), 'waktu');
        foreach (array_slice($waktu, 1) as $index => $berikutnya) {
            $this->assertGreaterThan(strtotime($berikutnya), strtotime($waktu[$index]));
        }
    }
}
