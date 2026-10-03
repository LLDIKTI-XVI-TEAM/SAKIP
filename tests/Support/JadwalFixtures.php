<?php

namespace Tests\Support;

use App\Models\Periode;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RegulasiPermissionSeeder;
use Illuminate\Support\Str;

trait JadwalFixtures
{
    private function calendarActor(array $permissions = ['periode:create', 'periode:update', 'jadwal:create', 'jadwal:update']): User
    {
        if (! Role::where('kode', 'perencanaan')->exists()) {
            $this->seed(RegulasiPermissionSeeder::class);
        }
        $role = Role::where('kode', 'perencanaan')->sole();
        $role->permissions()->sync(Permission::whereIn('kode', $permissions)->pluck('id')
            ->mapWithKeys(fn (string $id): array => [$id => ['id' => (string) Str::uuid(), 'created_at' => now()]])->all());
        $actor = User::factory()->create(['status' => 'aktif']);
        $actor->roles()->attach($role->id, ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $actor->id, 'created_at' => now()]);

        return $actor;
    }

    private function calendarRenstra(User $actor, string $status = 'draft'): Renstra
    {
        return Renstra::create(['kode' => 'REN-'.Str::random(8), 'nama' => 'Renstra Kalender', 'tahun_mulai' => 2025,
            'tahun_selesai' => 2029, 'status' => $status, 'dasar_hukum' => 'Kepmen fixture', 'created_by' => $actor->id]);
    }

    private function calendarMaster(int $order = 4, bool $final = true): Periode
    {
        return Periode::create(['nama' => 'Triwulan '.$order, 'urutan' => $order, 'aktif' => true, 'is_nilai_akhir' => $final])->refresh();
    }

    private function calendarPayload(Renstra $renstra, Periode $periode, int $year = 2026): array
    {
        return ['renstra_id' => $renstra->id, 'tahun' => $year, 'rencana_aksi_mulai' => "$year-01-05", 'rencana_aksi_selesai' => "$year-01-30",
            'penutupan' => ($year + 1).'-01-19', 'periode' => [['periode_id' => $periode->id, 'periode_revisi' => $periode->revisi,
                'pengisian_mulai' => ($year + 1).'-01-04', 'pengisian_selesai' => ($year + 1).'-01-11',
                'reviu_mulai' => ($year + 1).'-01-12', 'reviu_selesai' => ($year + 1).'-01-18']]];
    }
}
