<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\BootstrapSuperadmin;
use App\Actions\Auth\ProvisionKeycloakUser;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\Auth\KeycloakIdentityProvider;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.keycloak' => ['base_url' => 'https://sso.test', 'realms' => 'sakip', 'client_id' => 'sakip', 'client_secret' => 'secret-canary', 'redirect' => 'https://sakip.test/auth/keycloak/callback', 'post_logout_redirect' => 'https://sakip.test/auth/logged-out']]);
        $this->seed(AccessCatalogSeeder::class);
    }

    public function test_guest_login_redirects_and_password_and_demo_endpoints_are_gone(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $response = $this->get('/login');
        $response->assertRedirectContains('https://sso.test/realms/sakip/protocol/openid-connect/auth');
        $this->get('/auth/logged-out')->assertHeader('Referrer-Policy', 'same-origin');
        $this->assertStringNotContainsString('secret-canary', $response->headers->get('Location'));
        $this->get('/login', ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/login'))])->assertStatus(409)
            ->assertHeader('X-Inertia-Location');
        $this->post('/login', ['email' => 'x@example.test', 'password' => 'canary'])->assertStatus(405);
        $this->post('/dev/switch-role/1')->assertNotFound();
    }

    public function test_local_loopback_alias_redirects_before_creating_oidc_state(): void
    {
        // Job backend CI tidak membangun aset; pastikan test juga mencakup versi aset kosong.
        $this->app->usePublicPath(storage_path('framework/testing/no-built-assets'));
        config(['app.asset_url' => null]);
        app()->instance('env', 'local');
        config(['services.keycloak.redirect' => 'http://localhost:8000/auth/keycloak/callback']);
        $this->get('http://127.0.0.1:8000/login?redirect_uri=https://attacker.test')
            ->assertRedirect('http://localhost:8000/login')
            ->assertSessionMissing('state')->assertSessionMissing('oidc_nonce')->assertSessionMissing('code_verifier');
        $this->get('http://127.0.0.1:8000/login', ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/login'))])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', 'http://localhost:8000/login');
        $this->get('http://localhost:8000/login')
            ->assertRedirectContains('https://sso.test/realms/sakip/protocol/openid-connect/auth')
            ->assertSessionHas('state')->assertSessionHas('oidc_nonce')->assertSessionHas('code_verifier');
    }

    public function test_loopback_normalization_does_not_change_production_login(): void
    {
        config(['services.keycloak.redirect' => 'https://localhost/auth/keycloak/callback']);
        app()->instance('env', 'production');
        $this->get('http://127.0.0.1/login')->assertRedirectContains('https://sso.test/realms/sakip/protocol/openid-connect/auth');
    }

    public function test_pending_callback_has_identity_session_but_no_business_access_or_sensitive_props(): void
    {
        $this->mock(KeycloakIdentityProvider::class)->shouldReceive('identity')->once()->andReturn(['subject' => 'pending-canary', 'nama' => 'Pengguna Uji', 'email' => 'test@example.test']);
        $this->get('/auth/keycloak/callback')->assertRedirect('/auth/pending');
        $this->assertAuthenticated();
        $this->get('/dashboard')->assertRedirect('/auth/pending');
        $this->post('/pengukuran/00000000-0000-4000-8000-000000000000')->assertRedirect('/auth/pending');
        $response = $this->get('/auth/pending');
        $response->assertInertia(fn (Assert $page) => $page->component('Auth/Pending')->where('auth.user.nama', 'Pengguna Uji')->where('auth.can.dashboard', false)->missing('demo_users')->missing('auth.user.keycloak_id')->missing('auth.user.password'));
        $this->assertStringNotContainsString('pending-canary', $response->getContent());
    }

    public function test_failed_callback_does_not_leak_provider_exception_or_create_session(): void
    {
        Log::spy();
        $this->mock(KeycloakIdentityProvider::class)->shouldReceive('identity')->andThrow(new \RuntimeException('token-secret-canary'));
        $this->get('/auth/keycloak/callback?code=code-secret-canary')->assertRedirect('/auth/error')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        Log::shouldHaveReceived('warning')->once()->with('Autentikasi SSO gagal.', ['category' => \RuntimeException::class]);
    }

    public function test_activation_validates_reason_and_live_deactivation_blocks_existing_session(): void
    {
        $provision = app(ProvisionKeycloakUser::class);
        $admin = $provision->handle(['subject' => 'admin', 'nama' => 'Admin Uji', 'email' => 'admin@example.test']);
        app(BootstrapSuperadmin::class)->handle($admin->id, 'Operator QA', 'Inisialisasi', 'qa-runtime');
        $target = $provision->handle(['subject' => 'target', 'nama' => 'Target Uji', 'email' => 'target@example.test']);
        $this->actingAs($admin->fresh())->post('/akses/aktivasi/'.$target->id, ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->post('/akses/aktivasi/'.$target->id, ['alasan' => 'Disetujui'])->assertRedirect('/akses/aktivasi')->assertSessionHas('activationResult.status', 'activated');
        $this->assertTrue($target->fresh()->is_active);
        $this->actingAs($target->fresh());
        $target->refresh()->update(['is_active' => false]);
        $this->get('/pengukuran')->assertRedirect('/auth/pending');
    }

    public function test_failed_callback_preserves_an_existing_identity_and_clears_oidc_state(): void
    {
        $user = app(ProvisionKeycloakUser::class)->handle(['subject' => 'existing', 'nama' => 'Sesi Uji', 'email' => 'existing@example.test']);
        $this->mock(KeycloakIdentityProvider::class)->shouldReceive('identity')->andThrow(new \RuntimeException('invalid-state'));
        $this->actingAs($user)->withSession(['state' => 'old', 'code_verifier' => 'old', 'oidc_nonce' => 'old', 'oidc_started_at' => time()])
            ->get('/auth/keycloak/callback')->assertRedirect('/auth/error')->assertSessionMissing('state')->assertSessionMissing('code_verifier')->assertSessionMissing('oidc_nonce');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_provider_configuration_failure_still_ends_the_local_session(): void
    {
        $user = app(ProvisionKeycloakUser::class)->handle(['subject' => 'local-logout', 'nama' => 'Logout Uji', 'email' => 'local@example.test']);
        config(['services.keycloak.base_url' => '']);
        $this->actingAs($user)->withSession(['_token' => 'old-csrf-token'])->post('/logout')->assertRedirect('/auth/logged-out');
        $this->assertGuest();
        $this->assertNotSame('old-csrf-token', session()->token());
    }

    public function test_return_only_reviewer_has_the_same_menu_capability_as_the_endpoint(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach (['pengukuran:read', 'pengukuran:kembalikan'] as $code) {
            UserPermissionGrant::create([
                'user_id' => $user->id, 'permission_id' => Permission::where('kode', $code)->sole()->id,
                'unit_id' => null, 'alasan' => 'Delegasi reviu sintetis', 'diberikan_oleh' => $user->id,
            ]);
        }
        $this->actingAs($user)->get('/verifikasi')->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.can.verifikasi', true));
    }

    public function test_logout_is_local_first_and_landing_does_not_restart_sso(): void
    {
        $user = app(ProvisionKeycloakUser::class)->handle(['subject' => 'logout', 'nama' => 'Logout Uji', 'email' => 'logout@example.test']);
        $response = $this->actingAs($user)->post('/logout', [], ['X-Inertia' => 'true']);
        $response->assertStatus(409)->assertHeader('X-Inertia-Location');
        $this->assertGuest();
        $this->get('/auth/logged-out')->assertInertia(fn (Assert $page) => $page->component('Auth/LoggedOut')->where('auth.user', null));
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
