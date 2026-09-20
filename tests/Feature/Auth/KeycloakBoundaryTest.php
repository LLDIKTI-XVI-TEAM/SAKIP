<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\KeycloakIdentityProvider;
use App\Services\Auth\KeycloakTokenValidator;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Tests\TestCase;
use UnexpectedValueException;

class KeycloakBoundaryTest extends TestCase
{
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
