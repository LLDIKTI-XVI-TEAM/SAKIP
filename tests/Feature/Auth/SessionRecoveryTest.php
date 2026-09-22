<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $version = app(HandleInertiaRequests::class)->version(Request::create('/'));
        $this->withHeader('X-Inertia-Version', (string) $version);
    }

    public function test_guest_inertia_mutation_is_rejected_without_navigation_or_domain_writes(): void
    {
        $target = User::factory()->create();
        $before = DB::table('audit_log')->count();
        $path = '/akses/peran/'.$target->id;

        $response = $this->post($path.'?private=secret-canary', ['alasan' => 'draft-canary'], ['X-Inertia' => 'true']);
        $response->assertStatus(401)->assertHeaderMissing('Location')->assertHeaderMissing('X-Inertia-Location')
            ->assertHeaderMissing('X-Inertia')->assertJsonPath('recovery.reason', 'authentication_required')
            ->assertJsonPath('recovery.rejected', ['method' => 'POST', 'path' => $path, 'before_action' => true]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('canary', $response->getContent());
        $this->assertDatabaseCount('user_roles', 0);
        $this->assertDatabaseCount('audit_log', $before);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->getJson('/dashboard')->assertUnauthorized();
    }

    public function test_real_forgery_checks_reject_before_action_and_honor_effective_method(): void
    {
        // Hanya shortcut runner dimatikan; handle/origin/token framework tetap utuh.
        $this->app->bind(PreventRequestForgery::class, ChecksRequestForgeryInTests::class);
        $path = '/regulasi/00000000-0000-4000-8000-000000000001';
        $before = DB::table('audit_log')->count();
        $response = $this->post($path, ['_method' => 'PUT'], ['X-Inertia' => 'true']);
        $response->assertStatus(419)->assertHeaderMissing('Location')->assertHeaderMissing('X-Inertia')
            ->assertJsonPath('recovery.reason', 'csrf_mismatch')
            ->assertJsonPath('recovery.rejected', ['method' => 'PUT', 'path' => $path, 'before_action' => true]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('audit_log', $before);
        $this->assertDatabaseCount('regulasi', 0);
        $this->post($path, ['_method' => 'PUT'], ['X-Inertia' => 'true', 'Sec-Fetch-Site' => 'same-origin'])
            ->assertStatus(401)->assertJsonPath('recovery.reason', 'authentication_required');
    }

    public function test_committed_post_followed_by_guest_get_keeps_committed_data_and_get_marker(): void
    {
        $target = User::factory()->create(['nama' => 'Before commit']);
        Route::middleware('web')->post('/test-commit-before-redirect', function () use ($target) {
            DB::transaction(fn () => $target->update(['nama' => 'After commit']));

            return redirect('/dashboard');
        });
        $this->withHeader('X-Inertia', 'true')->followingRedirects()->post('/test-commit-before-redirect')
            ->assertUnauthorized()->assertJsonPath('recovery.rejected.method', 'GET')
            ->assertJsonPath('recovery.rejected.path', '/dashboard');
        $this->assertSame('After commit', $target->fresh()->nama);
    }

    public function test_downstream_auth_and_generic_419_do_not_claim_before_action(): void
    {
        Route::middleware('web')->get('/test-downstream-auth', fn () => throw new AuthenticationException);
        Route::middleware('web')->get('/test-generic-419', fn () => abort(419));
        $this->get('/test-downstream-auth', ['X-Inertia' => 'true'])->assertUnauthorized()
            ->assertJsonPath('recovery.rejected', null);
        $generic = $this->get('/test-generic-419', ['X-Inertia' => 'true'])->assertStatus(419);
        $this->assertStringNotContainsString('before_action', $generic->getContent());
        $this->get('/dashboard', ['X-Inertia' => 'true'])->assertUnauthorized()
            ->assertJsonPath('recovery.rejected.method', 'GET');
    }
}

class ChecksRequestForgeryInTests extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
