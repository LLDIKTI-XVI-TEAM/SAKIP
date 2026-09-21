<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\ProvisionKeycloakUser;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapSuperadminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
    }

    public function test_operator_can_bootstrap_the_displayed_account_id_with_one_confirmation(): void
    {
        $user = app(ProvisionKeycloakUser::class)->handle([
            'subject' => 'opaque-keycloak-subject',
            'nama' => 'Calon Admin',
            'email' => 'calon@example.test',
        ]);

        $this->artisan('sakip:bootstrap-superadmin')
            ->expectsQuestion('ID akun SAKIP', $user->id)
            ->expectsOutputToContain('Calon Admin')
            ->expectsOutputToContain('calon@example.test')
            ->expectsQuestion('Identitas operator dan referensi otorisasi', 'Operator QA / mandat awal')
            ->expectsQuestion('Alasan bootstrap', 'Inisialisasi akses pertama')
            ->expectsConfirmation('Aktifkan akun ini sebagai Super Admin?', 'yes')
            ->assertExitCode(0);

        $this->assertTrue($user->fresh()->is_active);
        $this->assertSame('superadmin', $user->roles()->first()->kode);
        $this->assertDatabaseCount('auth_bootstraps', 1);
    }

    public function test_wrong_non_interactive_confirmation_cannot_bootstrap_an_account(): void
    {
        $user = app(ProvisionKeycloakUser::class)->handle([
            'subject' => 'second-opaque-subject',
            'nama' => 'Calon Admin Kedua',
            'email' => 'kedua@example.test',
        ]);

        $this->artisan('sakip:bootstrap-superadmin', [
            'user' => $user->id,
            '--operator-reference' => 'Operator QA / mandat awal',
            '--reason' => 'Inisialisasi akses pertama',
            '--confirm-user' => '1380daa1-7af3-4884-aa0c-178614d7de78',
            '--no-interaction' => true,
        ])->assertExitCode(1);

        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseCount('auth_bootstraps', 0);
        $this->assertDatabaseCount('role_permissions', 0);
    }
}
