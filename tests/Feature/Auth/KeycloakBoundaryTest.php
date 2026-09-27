<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\KeycloakIdentityProvider;
use App\Services\Auth\KeycloakTokenValidator;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use UnexpectedValueException;

class KeycloakBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public static function existingSessionCases(): array
    {
        return ['guest' => [false], 'identity A' => [true]];
    }

    #[DataProvider('existingSessionCases')]
    public function test_landing_lookup_failure_preserves_session_identity(bool $hasExistingSession): void
    {
        $existing = $hasExistingSession ? User::factory()->create(['status' => 'aktif']) : null;
        $target = User::factory()->create(['keycloak_id' => 'fixture-sub', 'nama' => 'Sebelum provisioning', 'status' => 'aktif']);
        $callback = $this->signedCallbackUrl();
        if ($existing) {
            $this->withSession([Auth::guard()->getName() => $existing->id]);
        }

        $lookupFailed = false;
        // Gagalkan hanya lookup landing untuk B; provider, validator, dan provisioning tetap nyata.
        DB::connection()->beforeExecuting(function (string $query, array $bindings) use ($target, &$lookupFailed) {
            if (! $lookupFailed && str_contains($query, 'select exists') && str_contains($query, '"roles"')
                && str_contains($query, '"kode" in') && str_contains($query, '"roles"."aktif"')
                && in_array($target->id, $bindings, true)) {
                $lookupFailed = true;
                throw new \RuntimeException('Landing role lookup failed');
            }
        });

        $this->get($callback)->assertRedirect('/auth/error');
        $this->assertTrue($lookupFailed);
        $this->assertSame('Pengguna Uji', $target->fresh()->nama);

        // Buang cache guard agar request berikutnya membaca identity dari session tersimpan.
        Auth::forgetGuards();
        $this->get('/auth/error')->assertOk()->assertInertia(fn (Assert $page) => $existing
            ? $page->where('auth.user.id', $existing->id)
            : $page->where('auth.user', null));
        if ($existing) {
            $this->assertAuthenticatedAs($existing);
        } else {
            $this->assertGuest();
        }
    }

    public function test_signed_callback_with_active_official_role_logs_in_and_lands_on_dashboard(): void
    {
        $user = User::factory()->create(['keycloak_id' => 'fixture-sub', 'status' => 'aktif']);
        $role = Role::create(['kode' => 'pegawai', 'nama' => 'Pegawai', 'urutan' => 5, 'is_sistem' => true, 'aktif' => true]);
        $user->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);

        $this->get($this->signedCallbackUrl())->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    private function signedCallbackUrl(): string
    {
        [$provider, $request] = $this->provider();
        $provider->redirect();
        $this->app->bind(KeycloakIdentityProvider::class, function ($app) {
            $request = $app['request'];
            $provider = KeycloakIdentityProvider::forRequest($request);
            $provider->setHttpClient($this->http($request->session()->get('oidc_nonce')));

            return $provider;
        });

        return '/auth/keycloak/callback?'.http_build_query(['state' => $request->session()->get('state'), 'code' => 'code-canary']);
    }

    public function test_redirect_is_stateful_pkce_and_ignores_input_redirect(): void
    {
        [$provider, $request] = $this->provider();
        parse_str(parse_url($provider->redirect()->getTargetUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('https://sakip.test/auth/keycloak/callback', $query['redirect_uri']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame($request->session()->get('state'), $query['state']);
        $this->assertSame($request->session()->get('oidc_nonce'), $query['nonce']);
        $this->assertSame('openid profile email', $query['scope']);
    }

    public function test_signed_callback_returns_only_profile_and_is_single_use(): void
    {
        [$provider, $request] = $this->provider();
        $provider->redirect();
        $request->merge(['state' => $request->session()->get('state'), 'code' => 'code-canary']);
        $provider->setHttpClient($this->http($request->session()->get('oidc_nonce')));
        $this->assertSame(['subject' => 'fixture-sub', 'nama' => 'Pengguna Uji', 'email' => 'user@example.test'], $provider->identity(new KeycloakTokenValidator));
        $this->assertFalse($request->session()->has('code_verifier'));
        $this->expectException(UnexpectedValueException::class);
        $provider->identity(new KeycloakTokenValidator);
    }

    public function test_logout_accepts_only_fixed_landing_on_callback_origin(): void
    {
        [$provider] = $this->provider();
        foreach (['https://other.test/auth/logged-out', 'http://sakip.test/auth/logged-out', 'https://user@sakip.test/auth/logged-out', 'https://sakip.test/auth/logged-out?next=1', 'https://sakip.test/auth/logged-out#x', 'https://sakip.test/other', '/auth/logged-out', 'https://sakip.test:444/auth/logged-out'] as $landing) {
            config(['services.keycloak.post_logout_redirect' => $landing]);
            try {
                $provider->ssoLogoutUrl();
                $this->fail('Landing tidak aman harus ditolak.');
            } catch (UnexpectedValueException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach (['https://sakip.test', 'http://localhost:8000', 'http://127.0.0.1:8000'] as $origin) {
            config(['services.keycloak.redirect' => $origin.'/auth/keycloak/callback', 'services.keycloak.post_logout_redirect' => $origin.'/auth/logged-out']);
            $provider = KeycloakIdentityProvider::forRequest(Request::create('/logout/sso'));
            parse_str(parse_url($provider->ssoLogoutUrl(), PHP_URL_QUERY), $query);
            $this->assertSame(['post_logout_redirect_uri' => $origin.'/auth/logged-out', 'client_id' => 'sakip'], $query);
        }
    }

    public function test_userinfo_subject_mismatch_is_rejected(): void
    {
        [$provider, $request] = $this->provider();
        $provider->redirect();
        $request->merge(['state' => $request->session()->get('state'), 'code' => 'code-canary']);
        $provider->setHttpClient($this->http($request->session()->get('oidc_nonce'), 'different-sub'));
        $this->expectException(UnexpectedValueException::class);
        $provider->identity(new KeycloakTokenValidator);
    }

    private function provider(): array
    {
        config(['services.keycloak' => ['base_url' => 'https://sso.test', 'realms' => 'sakip', 'client_id' => 'sakip', 'client_secret' => 'secret-canary', 'redirect' => 'https://sakip.test/auth/keycloak/callback']]);
        $request = Request::create('/auth/keycloak/callback?redirect_uri=https://attacker.test');
        $request->setLaravelSession(app('session')->driver());

        return [KeycloakIdentityProvider::forRequest($request), $request];
    }

    public function test_invalid_state_is_consumed_without_contacting_provider(): void
    {
        [$provider, $request] = $this->provider();
        $provider->redirect();
        $request->merge(['state' => 'wrong-state', 'code' => 'code-canary']);
        $mock = new MockHandler([]);
        $provider->setHttpClient(new Client(['handler' => HandlerStack::create($mock)]));
        try {
            $provider->identity(new KeycloakTokenValidator);
            $this->fail('State tidak cocok harus ditolak.');
        } catch (UnexpectedValueException) {
            $this->assertNull($mock->getLastRequest());
            $this->assertFalse($request->session()->has('state'));
            $this->assertFalse($request->session()->has('code_verifier'));
        }
    }

    public function test_configured_url_cannot_contain_userinfo(): void
    {
        [, $request] = $this->provider();
        config(['services.keycloak.base_url' => 'https://username@sso.test']);
        $this->expectException(UnexpectedValueException::class);
        KeycloakIdentityProvider::forRequest($request);
    }

    private function http(string $nonce, string $subject = 'fixture-sub'): Client
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $private = '';
        openssl_pkey_export($key, $private);
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $issuer = 'https://sso.test/realms/sakip';
        $jwt = JWT::encode(['sub' => 'fixture-sub', 'iss' => $issuer, 'aud' => 'sakip', 'nonce' => $nonce, 'iat' => time(), 'exp' => time() + 300], $private, 'RS256', 'test');
        $bodies = [
            ['access_token' => 'access-canary', 'id_token' => $jwt],
            ['issuer' => $issuer, 'jwks_uri' => $issuer.'/protocol/openid-connect/certs'],
            ['keys' => [['kid' => 'test', 'kty' => 'RSA', 'alg' => 'RS256', 'n' => JWT::urlsafeB64Encode($rsa['n']), 'e' => JWT::urlsafeB64Encode($rsa['e'])]]],
            ['sub' => $subject, 'name' => 'Pengguna Uji', 'email' => 'user@example.test'],
        ];

        return new Client(['handler' => HandlerStack::create(new MockHandler(array_map(fn ($body) => new Response(200, [], json_encode($body)), $bodies)))]);
    }
}
