<?php

namespace Tests\Feature\IndikatorKomponen;

use App\Actions\Perencanaan\ChangeIndicatorFormula;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\AuditLogger;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseDAtomicDefinitionTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private IndikatorKinerja $indikator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->actor = User::factory()->create(['status' => 'aktif']);
        $this->actor->roles()->attach(Role::where('kode', 'perencanaan')->firstOrFail()->id, [
            'id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id, 'created_at' => now(),
        ]);
        $renstra = Renstra::create(['kode' => 'D', 'nama' => 'Renstra D', 'tahun_mulai' => 2025,
            'tahun_selesai' => 2029, 'created_by' => $this->actor->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'D', 'deskripsi' => 'Sasaran D', 'urutan' => 1]);
        $unit = Unit::create(['nama' => 'Unit D', 'status' => 'aktif', 'created_by' => $this->actor->id]);
        $this->indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id,
            'kode' => 'D', 'nama' => 'Indikator D', 'satuan' => 'nilai', 'tipe_perhitungan' => 'penjumlahan',
            'arah' => 'naik_baik', 'presisi' => 12, 'desimal_tampilan' => 2, 'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025, 'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan']);
        $this->row('a');
        $this->actingAs($this->actor);
    }

    private function row(string $kode): IndikatorKomponen
    {
        return IndikatorKomponen::create(['indikator_id' => $this->indikator->id, 'kode' => $kode,
            'label' => $kode, 'peran' => 'penjumlah', 'bobot' => '0.500000000001', 'urutan' => 1,
            'aktif' => true, 'created_by' => $this->actor->id]);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['tipe_perhitungan' => 'penjumlahan', 'komponen' => [], 'hapus_komponen_ids' => [],
            'expected_updated_at' => $this->indikator->fresh()->updated_at->toISOString(),
            'alasan' => 'Perbaikan definisi secara atomik.', 'request_id' => (string) Str::uuid()], $extra);
    }

    public function test_omission_mempertahankan_row_dan_noop_tidak_menulis(): void
    {
        $old = $this->indikator->fresh()->getAttributes();
        $child = $this->indikator->komponen()->firstOrFail();
        $audit = AuditLog::count();
        $payload = $this->payload();
        $this->patch("/perencanaan/indikator/{$this->indikator->id}/formula", $payload)
            ->assertSessionHasNoErrors()->assertInertiaFlash('indikatorMutation.status', 'unchanged')
            ->assertInertiaFlash('indikatorMutation.request_id', $payload['request_id']);
        $this->assertSame($old, $this->indikator->fresh()->getAttributes());
        $this->assertTrue($child->fresh()->aktif);
        $this->assertSame($audit, AuditLog::count());
    }

    public function test_delete_eksplisit_tidak_mengubah_id_atau_row_lain(): void
    {
        $second = $this->row('b');
        $first = $this->indikator->komponen()->where('kode', 'a')->firstOrFail();
        $oldToken = $this->indikator->fresh()->updated_at->toISOString();
        $this->patch("/perencanaan/indikator/{$this->indikator->id}/formula", $this->payload(['hapus_komponen_ids' => [$second->id]]))
            ->assertSessionHasNoErrors()->assertInertiaFlash('indikatorMutation.status', 'saved');
        $this->assertModelMissing($second);
        $this->assertSame('0.500000000001', $first->fresh()->bobot);
        $this->assertNotSame($oldToken, $this->indikator->fresh()->updated_at->toISOString());
        $this->assertDatabaseHas('audit_log', ['tindakan' => 'komponen.hapus', 'objek_id' => $second->id]);
    }

    public function test_metadata_tipe_sama_tidak_melewati_definisi_invalid(): void
    {
        $this->indikator->komponen()->update(['aktif' => false]);
        $data = $this->indikator->only(['sasaran_strategis_id', 'unit_id', 'kode', 'nama', 'satuan', 'arah', 'tipe_perhitungan', 'presisi']);
        $data['nama'] = 'Nama baru';
        $data['presisi'] = 2;
        $this->put("/perencanaan/indikator/{$this->indikator->id}", array_merge($data, ['expected_updated_at' => $this->indikator->fresh()->updated_at->toISOString()]))
            ->assertSessionHasErrors('tipe_perhitungan');
        $this->assertSame('Indikator D', $this->indikator->fresh()->nama);
    }

    public function test_metadata_mempertahankan_kode_numerik_berbeda_pada_komponen_omitted(): void
    {
        $this->indikator->komponen()->firstOrFail()->update(['kode' => '1']);
        $this->row('01');
        $beforeParent = $this->indikator->fresh()->getAttributes();
        $beforeChildren = $this->indikator->komponen()->orderBy('id')->get()->map->getAttributes()->all();
        $beforeAudits = AuditLog::count();
        $data = $this->indikator->only(['sasaran_strategis_id', 'unit_id', 'kode', 'satuan', 'arah', 'tipe_perhitungan']);
        $this->put("/perencanaan/indikator/{$this->indikator->id}", [...$data, 'nama' => 'Metadata kode numerik',
            'expected_updated_at' => $this->indikator->fresh()->updated_at->toISOString(),
        ])->assertSessionHasNoErrors()->assertInertiaFlash('indikatorMutation.status', 'saved');

        $afterParent = $this->indikator->fresh()->getAttributes();
        $this->assertNotSame($beforeParent['updated_at'], $afterParent['updated_at']);
        $this->assertSame(array_replace($beforeParent, ['nama' => 'Metadata kode numerik', 'updated_at' => $afterParent['updated_at']]), $afterParent);
        $this->assertSame($beforeChildren, $this->indikator->komponen()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($beforeAudits + 1, AuditLog::count());
        $audit = AuditLog::where('tindakan', 'indikator.ubah')->where('objek_id', $this->indikator->id)->sole();
        $this->assertSame('Indikator D', $audit->nilai_lama['nama']);
        $this->assertSame('Metadata kode numerik', $audit->nilai_baru['nama']);
        $this->assertSame('indikator:update', $audit->dasar_izin['permission']);
    }

    public function test_parent_put_child_only_dan_noop_memakai_izin_delta(): void
    {
        UserPermissionDeny::create(['user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', 'indikator:update')->value('id'),
            'ditetapkan_oleh' => $this->actor->id, 'alasan' => 'Uji pencabutan izin parent.']);
        $data = $this->indikator->only(['sasaran_strategis_id', 'unit_id', 'kode', 'nama', 'satuan', 'arah', 'tipe_perhitungan']);
        $data['expected_updated_at'] = $this->indikator->fresh()->updated_at->toISOString();
        $row = $this->indikator->komponen()->firstOrFail()->only(['id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'aktif']);
        $oldParent = $this->indikator->fresh()->getAttributes();
        $oldAudit = AuditLog::count();
        foreach ([[], ['komponen' => [$row]]] as $intent) {
            $this->put("/perencanaan/indikator/{$this->indikator->id}", [...$data, ...$intent])
                ->assertRedirect()->assertSessionHasNoErrors()->assertInertiaFlash('indikatorMutation.status', 'unchanged');
            $this->assertSame($oldParent, $this->indikator->fresh()->getAttributes());
            $this->assertSame($oldAudit, AuditLog::count());
        }
        $row['label'] = 'Perubahan child saja';
        $this->put("/perencanaan/indikator/{$this->indikator->id}", [...$data, 'komponen' => [$row], 'alasan' => 'Perbaikan label komponen.'])
            ->assertSessionHasNoErrors()->assertInertiaFlash('indikatorMutation.status', 'saved');
        $this->assertDatabaseHas('indikator_komponen', ['id' => $row['id'], 'label' => $row['label']]);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'indikator.ubah', 'objek_id' => $this->indikator->id]);
        $data['expected_updated_at'] = $this->indikator->fresh()->updated_at->toISOString();
        $this->putJson("/perencanaan/indikator/{$this->indikator->id}", [...$data, 'nama' => 'Parent tidak boleh berubah'])->assertForbidden();
        $this->assertSame('Indikator D', $this->indikator->fresh()->nama);
        $this->assertSame(1, AuditLog::where('tindakan', 'indikator.ubah_ditolak')->count());
        $this->assertSame('indikator:update', AuditLog::where('tindakan', 'indikator.ubah_ditolak')->sole()->dasar_izin['permission']);
        foreach (['komponen:read', 'indikator:read'] as $index => $permission) {
            UserPermissionDeny::create(['user_id' => $this->actor->id,
                'permission_id' => Permission::where('kode', $permission)->value('id'),
                'ditetapkan_oleh' => $this->actor->id, 'alasan' => 'Uji batas baca editor.']);
            $this->putJson("/perencanaan/indikator/{$this->indikator->id}", [...$data, 'komponen' => []])->assertForbidden();
            $this->assertSame($index + 2, AuditLog::where('tindakan', 'indikator.ubah_ditolak')->count());
            $this->assertSame(1, AuditLog::where('tindakan', 'indikator.ubah_ditolak')->where('dasar_izin->permission', $permission)->count());
        }
    }

    public function test_parent_metadata_tanpa_akses_child_tetap_dapat_diubah(): void
    {
        UserPermissionDeny::create(['user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', 'komponen:read')->value('id'),
            'ditetapkan_oleh' => $this->actor->id, 'alasan' => 'Tidak boleh membaca konfigurasi.']);
        $data = $this->indikator->only(['sasaran_strategis_id', 'unit_id', 'kode', 'satuan', 'arah', 'tipe_perhitungan']);
        $this->put("/perencanaan/indikator/{$this->indikator->id}", [...$data, 'nama' => 'Metadata parent saja',
            'expected_updated_at' => $this->indikator->fresh()->updated_at->toISOString(),
        ])->assertSessionHasNoErrors()->assertInertiaFlash('indikatorMutation.status', 'saved');
        $this->assertSame('Metadata parent saja', $this->indikator->fresh()->nama);
        $this->assertSame(1, AuditLog::where('tindakan', 'indikator.ubah')->count());
    }

    public function test_alasan_kontrol_disanitasi_sebelum_minimum_dan_audit(): void
    {
        $row = $this->indikator->komponen()->firstOrFail()->only(['id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'aktif']);
        $row['label'] = 'Label baru';
        $oldRevision = $this->indikator->fresh()->updated_at->toISOString();
        $oldAudit = AuditLog::count();
        foreach ([str_repeat("\x01", 5), "abc\x01\x02"] as $reason) {
            $this->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", $this->payload(['komponen' => [$row], 'alasan' => $reason]))
                ->assertUnprocessable()->assertJsonValidationErrors('alasan');
            $this->assertSame('a', $this->indikator->komponen()->firstOrFail()->label);
            $this->assertSame($oldRevision, $this->indikator->fresh()->updated_at->toISOString());
            $this->assertSame($oldAudit, AuditLog::count());
        }
        $this->patch("/perencanaan/indikator/{$this->indikator->id}/formula", $this->payload([
            'komponen' => [$row], 'alasan' => "\x01  Rationale sah\nbaris kedua.  \x7F",
        ]))->assertSessionHasNoErrors();
        $this->assertSame("Rationale sah\nbaris kedua.", AuditLog::where('tindakan', 'komponen.ubah')->sole()->alasan);
    }

    public function test_parent_put_perubahan_tipe_memerlukan_alasan_sensitif(): void
    {
        $this->indikator->komponen()->update(['aktif' => false]);
        $this->indikator->update(['tipe_perhitungan' => 'manual', 'presisi' => 2]);
        $oldParent = $this->indikator->fresh()->getAttributes();
        $oldAudit = AuditLog::count();
        $data = $this->indikator->only(['sasaran_strategis_id', 'unit_id', 'kode', 'nama', 'satuan', 'arah', 'presisi']);
        $data += ['tipe_perhitungan' => 'penjumlahan', 'expected_updated_at' => $this->indikator->updated_at->toISOString(),
            'komponen' => [['kode' => 'baru', 'label' => 'Baru', 'peran' => 'penjumlah', 'bobot' => '1', 'urutan' => 2, 'aktif' => true]]];
        foreach ([null, 'abc'] as $alasan) {
            $this->putJson("/perencanaan/indikator/{$this->indikator->id}", [...$data, 'alasan' => $alasan])
                ->assertUnprocessable()->assertJsonValidationErrors('alasan');
            $this->assertSame($oldParent, $this->indikator->fresh()->getAttributes());
            $this->assertDatabaseMissing('indikator_komponen', ['indikator_id' => $this->indikator->id, 'kode' => 'baru']);
            $this->assertSame($oldAudit, AuditLog::count());
        }
        $this->put("/perencanaan/indikator/{$this->indikator->id}", [...$data, 'alasan' => 'Perubahan metode penghitungan.'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_log', ['objek_id' => $this->indikator->id, 'tindakan' => 'indikator.ubah', 'alasan' => 'Perubahan metode penghitungan.']);
    }

    public function test_duplicate_delete_id_beda_case_ditolak_http_dan_action(): void
    {
        $second = $this->row('b');
        $oldRevision = $this->indikator->fresh()->updated_at->toISOString();
        $data = $this->payload(['hapus_komponen_ids' => [$second->id, strtoupper($second->id)]]);
        $this->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", $data)
            ->assertUnprocessable()->assertJsonValidationErrors('hapus_komponen_ids');
        try {
            app(ChangeIndicatorFormula::class)->handle($this->actor, $this->indikator, $data);
            $this->fail('Action harus menolak identitas hapus duplikat sesudah normalisasi.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('hapus_komponen_ids', $exception->errors());
        }
        $this->assertModelExists($second);
        $this->assertSame($oldRevision, $this->indikator->fresh()->updated_at->toISOString());
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'komponen.hapus', 'objek_id' => $second->id]);
        $this->patch("/perencanaan/indikator/{$this->indikator->id}/formula", $this->payload(['hapus_komponen_ids' => [strtoupper($second->id)]]))
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($second);
    }

    public function test_create_nonmanual_dengan_komponen_valid_atomik(): void
    {
        $data = $this->indikator->only(['sasaran_strategis_id', 'unit_id', 'nama', 'satuan', 'arah', 'tipe_perhitungan', 'presisi']);
        $data['presisi'] = 2;
        $row = $this->indikator->komponen()->firstOrFail()->only(['kode', 'label', 'satuan', 'peran', 'bobot', 'urutan', 'aktif']);
        $beforeAudits = AuditLog::count();
        $this->post('/perencanaan/indikator', array_merge($data, ['kode' => 'BARU', 'komponen' => [
            array_replace($row, ['kode' => '1']),
            array_replace($row, ['kode' => '01', 'urutan' => 2]),
        ], 'request_id' => (string) Str::uuid()]))
            ->assertSessionHasNoErrors()->assertInertiaFlash('indikatorMutation.status', 'saved');
        $created = IndikatorKinerja::where('kode', 'BARU')->firstOrFail();
        $this->assertSame('perencanaan', $created->created_by_role);
        $this->assertSame('0.500000000001', $created->komponen()->firstOrFail()->bobot);
        $this->assertSame(['1', '01'], $created->komponen()->orderBy('urutan')->pluck('kode')->all());
        $this->assertSame($beforeAudits + 3, AuditLog::count());
        $this->assertDatabaseHas('audit_log', ['tindakan' => 'indikator.buat', 'objek_id' => $created->id]);
        $this->assertSame(2, AuditLog::where('tindakan', 'komponen.buat')->whereIn('objek_id', $created->komponen()->pluck('id'))->count());
    }

    public function test_create_nonmanual_kode_identik_ditolak_tanpa_mutasi(): void
    {
        $data = $this->indikator->only(['sasaran_strategis_id', 'unit_id', 'nama', 'satuan', 'arah', 'tipe_perhitungan']);
        $row = $this->indikator->komponen()->firstOrFail()->only(['kode', 'label', 'peran', 'bobot', 'urutan', 'aktif']);
        $row['kode'] = '1';
        $beforeParents = IndikatorKinerja::orderBy('id')->get()->map->getAttributes()->all();
        $beforeChildren = IndikatorKomponen::orderBy('id')->get()->map->getAttributes()->all();
        $beforeAudits = AuditLog::count();
        $this->postJson('/perencanaan/indikator', [...$data, 'kode' => 'DUPLIKAT', 'komponen' => [$row, $row]])
            ->assertUnprocessable()->assertJsonValidationErrors(['komponen.0.kode', 'komponen.1.kode']);
        $this->assertSame($beforeParents, IndikatorKinerja::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($beforeChildren, IndikatorKomponen::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($beforeAudits, AuditLog::count());
    }

    public function test_editor_halamaan_terikat_revisi_dan_presisi_eksak(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            $this->row('r'.$i);
        }
        $first = $this->getJson("/perencanaan/indikator/{$this->indikator->id}/editor")
            ->assertOk()->assertJsonCount(50, 'komponen')->assertJsonPath('pagination.complete', false)
            ->assertJsonPath('pagination.next_page', 2);
        $revision = $first->json('revision');
        $this->getJson("/perencanaan/indikator/{$this->indikator->id}/editor?page=2&expected_updated_at=".urlencode($revision))
            ->assertOk()->assertJsonCount(1, 'komponen')->assertJsonPath('pagination.complete', true);
        $this->indikator->update(['nama' => 'Revisi baru']);
        $this->getJson("/perencanaan/indikator/{$this->indikator->id}/editor?page=2&expected_updated_at=".urlencode($revision))->assertStatus(409);
    }

    public function test_editor_komponen_memuat_desimal_tampilan_terpisah_dari_presisi(): void
    {
        $this->getJson("/indikator/{$this->indikator->id}/komponen")
            ->assertOk()->assertJsonPath('indikator.presisi', 12)
            ->assertJsonPath('indikator.desimal_tampilan', 2);
    }

    public function test_preview_memakai_engine_eksak_tanpa_mutasi_dan_menolak_token_lama(): void
    {
        $component = $this->indikator->komponen()->firstOrFail();
        $token = $this->indikator->fresh()->updated_at->toISOString();
        $audits = AuditLog::count();
        $url = "/perencanaan/indikator/{$this->indikator->id}/komponen/preview";
        $this->postJson($url, ['expected_updated_at' => $token, 'values' => [$component->id => '2']])
            ->assertOk()->assertJsonPath('nilai', '1.000000000002')->assertJsonPath('revision', $token);
        $this->assertSame($audits, AuditLog::count());
        $this->assertSame($token, $this->indikator->fresh()->updated_at->toISOString());
        $this->indikator->update(['presisi' => 2]);
        $this->postJson($url, ['expected_updated_at' => $token, 'values' => [$component->id => '2']])->assertStatus(409);
    }

    public function test_create_only_tidak_memerlukan_parent_update_atau_parent_read(): void
    {
        foreach (['indikator:update', 'indikator:read'] as $permission) {
            UserPermissionDeny::create(['user_id' => $this->actor->id,
                'permission_id' => Permission::where('kode', $permission)->value('id'),
                'ditetapkan_oleh' => $this->actor->id, 'alasan' => 'Uji izin granular.']);
        }
        $row = $this->indikator->komponen()->firstOrFail()->only(['kode', 'label', 'peran', 'bobot', 'urutan', 'aktif']);
        $row['kode'] = 'baru';
        $response = $this->patch("/perencanaan/indikator/{$this->indikator->id}/formula", $this->payload(['komponen' => [$row]]));
        $response->assertSessionHasNoErrors()->assertRedirect("/indikator/{$this->indikator->id}/komponen");
        $this->get($response->headers->get('Location'))->assertOk();
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'indikator.ubah', 'objek_id' => $this->indikator->id]);
        $this->assertDatabaseHas('indikator_komponen', ['indikator_id' => $this->indikator->id, 'kode' => 'baru']);
    }

    public function test_bobot_eksponen_berlebihan_ditolak_sebagai_sintaks_tanpa_500(): void
    {
        $row = $this->indikator->komponen()->firstOrFail()->only(['id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'aktif']);
        $row['bobot'] = '1e100000000000000000000000';
        foreach (['penjumlah', 'penyebut'] as $peran) {
            $row['peran'] = $peran;
            $this->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", $this->payload(['komponen' => [$row]]))
                ->assertUnprocessable()->assertJsonValidationErrors('komponen.0.bobot');
        }
        $this->assertSame('0.500000000001', $this->indikator->komponen()->firstOrFail()->bobot);
    }

    public function test_bobot_di_atas_batas_dengan_selisih_terkecil_ditolak_eksak(): void
    {
        $row = $this->indikator->komponen()->firstOrFail()->only(['id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'aktif']);
        $row['bobot'] = '999999999.000000000001';
        $this->patch("/perencanaan/indikator/{$this->indikator->id}/formula", $this->payload(['komponen' => [$row]]))
            ->assertSessionHasErrors('komponen.0.bobot');
        $this->assertSame('0.500000000001', $this->indikator->komponen()->firstOrFail()->bobot);
    }

    public function test_update_delete_overlap_ditolak(): void
    {
        $row = $this->indikator->komponen()->firstOrFail()->only(['id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'aktif']);
        $this->patch("/perencanaan/indikator/{$this->indikator->id}/formula", $this->payload(['komponen' => [$row], 'hapus_komponen_ids' => [$row['id']]]))
            ->assertSessionHasErrors('komponen.0.id');
        $this->assertDatabaseHas('indikator_komponen', ['id' => $row['id']]);
    }

    public function test_tiga_route_crud_komponen_lama_tidak_aktif_dan_tidak_memutasi(): void
    {
        $this->row('b');
        $component = $this->indikator->komponen()->where('kode', 'a')->firstOrFail();
        $fields = $component->only(['kode', 'label', 'peran', 'bobot', 'urutan', 'aktif']);
        $beforeParent = $this->indikator->fresh()->getAttributes();
        $beforeChildren = $this->indikator->komponen()->orderBy('id')->get()->map->getAttributes()->all();
        $beforeAudits = AuditLog::count();
        $metadata = ['expected_updated_at' => $this->indikator->fresh()->updated_at->toISOString(),
            'alasan' => 'Uji route komponen yang sudah dipensiunkan.'];
        $url = "/indikator/{$this->indikator->id}/komponen";

        // GET daftar masih aktif (POST 405); URL child sudah tidak terdaftar (PUT/DELETE 404).
        foreach ([
            ['POST', $url, [...$fields, ...$metadata, 'kode' => 'baru'], 405],
            ['PUT', "{$url}/{$component->id}", [...$fields, ...$metadata, 'label' => 'Label baru'], 404],
            ['DELETE', "{$url}/{$component->id}", $metadata, 404],
        ] as [$method, $requestUrl, $payload, $status]) {
            $this->call($method, $requestUrl, $payload)->assertStatus($status);
            $this->assertSame($beforeParent, $this->indikator->fresh()->getAttributes());
            $this->assertSame($beforeChildren, $this->indikator->komponen()->orderBy('id')->get()->map->getAttributes()->all());
            $this->assertSame($beforeAudits, AuditLog::count());
        }
    }

    public function test_kegagalan_audit_membatalkan_parent_dan_seluruh_delta_child(): void
    {
        $delete = $this->row('hapus');
        $row = $this->indikator->komponen()->where('kode', 'a')->firstOrFail();
        $beforeParent = $this->indikator->fresh()->getAttributes();
        $beforeChildren = $this->indikator->komponen()->orderBy('id')->get()->map->getAttributes()->all();
        $beforeAudits = AuditLog::count();
        $logger = \Mockery::mock(AuditLogger::class);
        $logger->shouldReceive('catat')->once()->andThrow(new \RuntimeException('Audit fixture gagal.'));
        $this->app->instance(AuditLogger::class, $logger);
        try {
            app(ChangeIndicatorFormula::class)->handle($this->actor, $this->indikator, $this->payload([
                'presisi' => 2, 'hapus_komponen_ids' => [$delete->id], 'komponen' => [
                    array_merge($row->only(['id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'aktif']), ['label' => 'Revisi']),
                    ['kode' => 'baru', 'label' => 'Baru', 'peran' => 'penjumlah', 'bobot' => '1', 'urutan' => 3, 'aktif' => true],
                ],
            ]));
            $this->fail('Kegagalan audit harus keluar dari transaksi.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit fixture gagal.', $exception->getMessage());
        }
        $this->assertSame($beforeParent, $this->indikator->fresh()->getAttributes());
        $this->assertSame($beforeChildren, $this->indikator->komponen()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($beforeAudits, AuditLog::count());
    }

    public function test_receipt_tersedia_di_flash_inertia_bukan_props_saja(): void
    {
        $payload = $this->payload();
        $response = $this->patch("/perencanaan/indikator/{$this->indikator->id}/formula", $payload)->assertSessionHasNoErrors();
        $this->get($response->headers->get('Location'), ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonPath('flash.indikatorMutation.request_id', $payload['request_id'])
            ->assertJsonPath('flash.indikatorMutation.status', 'unchanged');
    }

    public function test_uuid_huruf_besar_dinormalisasi_dan_aba_tetap_menolak_token_asal(): void
    {
        $row = $this->indikator->komponen()->firstOrFail()->only(['id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'aktif']);
        $initial = $this->payload();
        $row['id'] = strtoupper($row['id']);
        $row['label'] = 'B';
        $this->patch("/perencanaan/indikator/{$this->indikator->id}/formula", array_replace($initial, ['komponen' => [$row]]))->assertSessionHasNoErrors();
        $row['label'] = 'a';
        $this->patch("/perencanaan/indikator/{$this->indikator->id}/formula", $this->payload(['komponen' => [$row]]))->assertSessionHasNoErrors();
        $this->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", $initial)->assertConflict();
    }
}
