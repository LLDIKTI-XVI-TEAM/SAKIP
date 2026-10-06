<?php

namespace Tests\Feature\Jadwal;

use App\Actions\Jadwal\SaveJadwalDraft;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\JadwalFixtures;
use Tests\TestCase;

class JadwalPagesTest extends TestCase
{
    use JadwalFixtures, RefreshDatabase;

    public function test_domain_access_is_create_or_update_and_before_lookup_or_query_validation(): void
    {
        $none = User::factory()->create(['status' => 'aktif']);
        foreach (['/periode', '/jadwal', '/jadwal/create', '/jadwal/'.Str::uuid(), '/jadwal/opsi/renstra?q='.str_repeat('x', 101)] as $url) {
            $this->actingAs($none)->get($url)->assertForbidden();
        }
        $actor = $this->calendarActor(['periode:update', 'jadwal:update']);
        $this->actingAs($actor)->get('/periode')->assertInertia(fn (Assert $page) => $page->component('Periode/Index')->where('can.create', false)->where('can.update', true)->where('auth.can.periode', true)->where('auth.can.jadwal', true));
        $this->get('/jadwal')->assertOk();
        $this->get('/jadwal/create')->assertForbidden();
        $this->get('/jadwal/not-a-uuid')->assertNotFound();
        $this->get('/jadwal/opsi/invalid')->assertNotFound();
    }

    public function test_queries_are_paginated_and_current_final_is_outside_filter(): void
    {
        $actor = $this->calendarActor();
        $final = $this->calendarMaster();
        for ($i = 0; $i < 22; $i++) {
            Periode::create(['nama' => 'Kandidat '.$i, 'urutan' => $i, 'aktif' => true, 'is_nilai_akhir' => false]);
        }
        $this->actingAs($actor)->get('/periode?q=Kandidat')->assertInertia(fn (Assert $page) => $page->has('periode.data', 20)
            ->where('periode.total', 22)->where('current_final.id', $final->id)->where('current_final.revisi', 1)
            ->where('periode.data.0.metadata_locked', false)->missing('periode.data.0.created_by'));
        $this->getJson('/jadwal/opsi/periode?q=Kandidat')->assertJsonCount(20, 'data')->assertJsonPath('has_more', true);
        $this->getJson('/jadwal/opsi/periode?q=Kandidat&page=2')->assertJsonCount(2, 'data')->assertJsonPath('has_more', false);
        $final->update(['aktif' => false]);
        $this->getJson('/jadwal/opsi/periode?q=Triwulan')->assertJsonCount(0, 'data');
        $this->getJson('/jadwal/opsi/periode?page=0')->assertUnprocessable();
        $this->get('/jadwal?sort=sql')->assertSessionHasErrors('sort');
    }

    public function test_year_filter_validation_has_readable_indonesian_messages(): void
    {
        $actor = $this->calendarActor();
        $this->actingAs($actor)->get('/jadwal?tahun=10000')
            ->assertSessionHasErrors(['tahun' => 'Tahun harus berada antara 1 dan 9998.']);
        $this->get('/jadwal?tahun=2026.5')
            ->assertSessionHasErrors(['tahun' => 'Tahun harus berupa bilangan bulat.']);
    }

    public function test_editor_revision_matches_aggregate_and_parent_readonly_is_authoritative(): void
    {
        $actor = $this->calendarActor();
        $renstra = $this->calendarRenstra($actor);
        $periode = $this->calendarMaster();
        $data = $this->calendarPayload($renstra, $periode);
        $jadwal = app(SaveJadwalDraft::class)->handle($actor, $data);
        app(SaveJadwalDraft::class)->handle($actor, [...$data, 'penutupan' => '2027-01-20', 'revisi' => 1], $jadwal);
        $periode->update(['aktif' => false]);
        $this->actingAs($actor)->get('/jadwal/'.$jadwal->id)->assertInertia(fn (Assert $page) => $page->component('Jadwal/Editor')
            ->where('jadwal.revisi', 2)->where('jadwal.penutupan', '2027-01-20')->where('jadwal.periode.0.aktif', false)
            ->where('jadwal.periode.0.periode_revisi', 1)->where('jadwal.renstra.id', $renstra->id)->where('can.update', true)
            ->missing('jadwal.renstra_pk_id')->missing('jadwal.activated_at'));
        $renstra->update(['status' => 'nonaktif']);
        $this->get('/jadwal/'.$jadwal->id)->assertInertia(fn (Assert $page) => $page->where('can.update', false)->where('read_only_reason', fn ($reason): bool => str_contains($reason, 'Renstra')));
        $this->get('/jadwal?renstra_id='.$renstra->id.'&tahun=2026')->assertInertia(fn (Assert $page) => $page->has('jadwal.data', 1)->where('jadwal.data.0.can_update', false));
        $this->getJson('/jadwal/opsi/renstra')->assertJsonCount(0, 'data');
    }
}
