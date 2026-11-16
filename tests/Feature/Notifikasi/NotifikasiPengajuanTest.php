<?php

namespace Tests\Feature\Notifikasi;

use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RencanaAksiFixtures;
use Tests\TestCase;

/**
 * Pengajuan RA menulis satu item antrean untuk setiap Perencanaan aktif yang lolos
 * baca objek dan izin verifikasi pada unit itu (deny menang); pengaju dan Superadmin tidak menerima.
 */
class NotifikasiPengajuanTest extends TestCase
{
    use RefreshDatabase, RencanaAksiFixtures;

    private function items(): Collection
    {
        return DB::table('notifications')->orderBy('created_at')->get()->map(fn ($row) => (object) [...(array) $row, 'data' => json_decode($row->data, true)]);
    }

    private function deny(User $user, string $kode, ?string $unitId = null): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission_id' => Permission::where('kode', $kode)->value('id'),
            'unit_id' => $unitId, 'alasan' => 'Pengujian deny', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);
    }

    /** @return array{0: array<string, mixed>, 1: RencanaAksi, 2: User} fixture, draf lengkap, Perencanaan kedua */
    private function drafRencanaAksi(): array
    {
        $fixture = $this->buatFixtureManual();
        $header = $this->drafManual($fixture);
        $p2 = $this->penggunaDenganPeran('perencanaan');
        $denyGlobal = $this->penggunaDenganPeran('perencanaan');
        $this->deny($denyGlobal, 'rencana_aksi:verifikasi');
        $denyUnit = $this->penggunaDenganPeran('perencanaan');
        $this->deny($denyUnit, 'rencana_aksi:verifikasi', $fixture['unit']->id);
        $denyBaca = $this->penggunaDenganPeran('perencanaan');
        $this->deny($denyBaca, 'rencana_aksi:read', $fixture['unit']->id);
        $this->penggunaDenganPeran('perencanaan')->update(['status' => 'nonaktif']);

        return [$fixture, $header, $p2];
    }

    public function test_rencana_aksi_diajukan_jalur_pic_menulis_item_untuk_setiap_perencanaan_peninjau_unit(): void
    {
        [$fixture, $header, $p2] = $this->drafRencanaAksi();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();

        $items = $this->items();
        $this->assertEqualsCanonicalizing([$fixture['perencanaan']->id, $p2->id], $items->pluck('notifiable_id')->all());
        $item = $items->first();
        $this->assertSame('rencana_aksi.diajukan', $item->type);
        $this->assertSame(['judul' => "Rencana Aksi {$fixture['indikator']->kode} 2026 menunggu verifikasi", 'ringkasan' => "Diajukan oleh {$fixture['pic']->nama}",
            'route_name' => 'rencana-aksi.review', 'route_params' => ['rencanaAksi' => $header->id]], $item->data);
    }

    public function test_rencana_aksi_diajukan_jalur_perencanaan_tidak_menulis_item_untuk_pengaju(): void
    {
        [$fixture, $header, $p2] = $this->drafRencanaAksi();

        $this->actingAs($fixture['perencanaan'])->post("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 2])->assertSessionHasNoErrors();

        $this->assertSame([$p2->id], $this->items()->pluck('notifiable_id')->all());
        $this->assertSame("Diajukan oleh {$fixture['perencanaan']->nama}", $this->items()->sole()->data['ringkasan']);
    }

    public function test_pengajuan_yang_ditolak_gerbang_tidak_menulis_item(): void
    {
        [$fixture, $header] = $this->drafRencanaAksi();

        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/ajukan", ['expected_versi' => 99])->assertConflict();

        $this->assertCount(0, $this->items());
    }
}
