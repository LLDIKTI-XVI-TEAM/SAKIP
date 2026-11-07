<?php

namespace Tests\Feature\Notifikasi;

use App\Actions\Pengaturan\UpdatePengaturan;
use App\Models\AuditLog;
use App\Models\Pengaturan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionDenial;
use App\Services\Notifikasi\KonfigurasiNotifikasi;
use App\Services\Pengaturan\PengaturanService;
use Database\Seeders\AccessCatalogSeeder;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PengaturanNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->admin = User::factory()->create(['status' => 'aktif']);
        $this->admin->roles()->attach(Role::where('kode', 'admin')->value('id'), [
            'id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->admin->id, 'created_at' => now(),
        ]);
    }

    public function test_default_bertipe_sebelum_seed_dan_seed_tidak_menimpa_perubahan(): void
    {
        $service = app(PengaturanService::class);
        $this->assertFalse($service->get('notifikasi.kanal_email_aktif'));
        $this->assertSame([7, 3, 1], app(KonfigurasiNotifikasi::class)->hMinusPic());
        $this->assertSame([3, 1], app(KonfigurasiNotifikasi::class)->hMinusRekap());
        $this->assertNull(app(KonfigurasiNotifikasi::class)->jamKirim());
        $this->seed(PengaturanSeeder::class);
        $this->actingAs($this->admin)->put('/pengaturan', $this->payload('notifikasi.kanal_email_aktif', true))->assertRedirect('/pengaturan');
        $this->seed(PengaturanSeeder::class);
        $this->assertSame('1', Pengaturan::where('kunci', 'notifikasi.kanal_email_aktif')->value('nilai'));
        $this->assertSame(count(PengaturanService::WHITELIST), Pengaturan::count());
    }

    public function test_false_roundtrip_dan_audit_parameter_tanpa_shared_prop_notifikasi(): void
    {
        $this->seed(PengaturanSeeder::class);
        foreach ([true, false] as $aktif) {
            $this->actingAs($this->admin)->put('/pengaturan', $this->payload('notifikasi.kanal_email_aktif', $aktif))->assertRedirect('/pengaturan');
            $this->assertSame($aktif, app(KonfigurasiNotifikasi::class)->aktif('email'));
        }
        $this->assertSame('0', Pengaturan::where('kunci', 'notifikasi.kanal_email_aktif')->value('nilai'));
        $audit = AuditLog::where('tindakan', 'pengaturan:update')->get();
        $this->assertCount(2, $audit);
        $this->assertTrue($audit->contains(fn ($a) => $a->nilai_lama === ['nilai' => '1'] && $a->nilai_baru === ['nilai' => '0']));
        $this->assertSame('pengaturan:update', $audit->first()->dasar_izin['permission']);
        $this->actingAs($this->admin)->get('/pengaturan')->assertInertia(fn (Assert $page) => $page
            ->where('values', fn ($values) => $values['notifikasi.kanal_email_aktif'] === false)
            ->has('grouped.notifikasi', 3)
            ->where('notifikasi.h_minus_pic', [7, 3, 1])
            ->where('notifikasi.h_minus_rekap', [3, 1])
            ->where('notifikasi.simulasi.email', true)
            ->where('pengaturan', fn ($props) => collect($props)->keys()->every(fn (string $key) => ! str_starts_with($key, 'notifikasi.')))
        );
    }

    #[DataProvider('parameterInvalid')]
    public function test_validasi_http_menolak_parameter_di_luar_kontrak(string $key, mixed $value): void
    {
        $auditCount = AuditLog::count();
        $this->actingAs($this->admin)->putJson('/pengaturan', [$key => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($key);
        $this->assertDatabaseCount('audit_log', $auditCount);
    }

    public static function parameterInvalid(): array
    {
        return [
            'hari tidak boleh dimutasi' => ['notifikasi.h_minus', '7,3,1'],
            'jam invalid' => ['notifikasi.jam_kirim', '24:00'],
            'boolean invalid' => ['notifikasi.kanal_email_aktif', 'ya'],
            'credential' => ['notifikasi.api_token', 'secret-sintetis'],
        ];
    }

    public function test_action_juga_menolak_kunci_hari_pengiriman(): void
    {
        $this->expectException(ValidationException::class);
        app(UpdatePengaturan::class)->handle($this->admin, ['notifikasi.h_minus' => '10,5,2'], '');
    }

    public function test_jam_boleh_dikosongkan_dan_dibaca_tanpa_cache(): void
    {
        $this->seed(PengaturanSeeder::class);
        foreach (['09:30', null] as $jam) {
            $this->actingAs($this->admin)->put('/pengaturan', $this->payload('notifikasi.jam_kirim', $jam))->assertRedirect('/pengaturan');
            $this->assertSame($jam, app(KonfigurasiNotifikasi::class)->jamKirim());
        }
    }

    public function test_status_dan_mutasi_tetap_ditolak_oleh_explicit_deny(): void
    {
        UserPermissionDenial::create([
            'user_id' => $this->admin->id, 'permission_id' => Permission::where('kode', 'pengaturan:update')->value('id'),
            'unit_id' => null, 'alasan' => 'Pembatasan sintetis', 'ditetapkan_oleh' => $this->admin->id,
        ]);
        $this->actingAs($this->admin)->get('/pengaturan/notifikasi/pengiriman')->assertForbidden();
        $this->actingAs($this->admin)->put('/pengaturan', ['notifikasi.kanal_email_aktif' => true])->assertForbidden();
        $this->assertDatabaseMissing('pengaturan', ['kunci' => 'notifikasi.kanal_email_aktif']);
    }

    public function test_konfigurasi_hari_mengabaikan_baris_lama_dan_mode_mengikuti_driver(): void
    {
        Pengaturan::create(['kunci' => 'notifikasi.h_minus', 'nilai' => '10,5,2', 'tipe' => 'string', 'grup' => 'notifikasi']);
        $this->assertSame([7, 3, 1], app(KonfigurasiNotifikasi::class)->hMinusPic());
        $this->assertSame([3, 1], app(KonfigurasiNotifikasi::class)->hMinusRekap());
        config(['notifikasi.kanal.email.driver' => 'driver-tidak-didukung']);
        $this->actingAs($this->admin)->get('/pengaturan')->assertInertia(fn (Assert $page) => $page
            ->where('notifikasi.simulasi.email', false)
            ->where('notifikasi.simulasi.whatsapp', true)
            ->where('values', fn ($values) => ! isset($values['notifikasi.h_minus']))
        );
    }

    private function payload(string $key, mixed $value): array
    {
        return [$key => $value, 'expected_updated_at' => [$key => Pengaturan::where('kunci', $key)->first()?->updated_at?->toISOString()]];
    }
}
