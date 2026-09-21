<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SocialiteProviders\Keycloak\Provider;
use SocialiteProviders\Manager\Config;
use UnexpectedValueException;

class KeycloakIdentityProvider extends Provider
{
    public static function forRequest(Request $request): self
    {
        $config = config('services.keycloak');
        foreach (['base_url', 'realms', 'client_id', 'client_secret', 'redirect'] as $key) {
            if (! is_string($config[$key] ?? null) || trim($config[$key]) === '') {
                throw new UnexpectedValueException('Konfigurasi SSO belum lengkap.');
            }
        }
        if (! self::safeUrl($config['base_url']) || ! self::safeUrl($config['redirect'])
            || ! preg_match('/^[a-zA-Z0-9._-]+$/D', $config['realms'])) {
            throw new UnexpectedValueException('Konfigurasi SSO tidak valid.');
        }

        $provider = new self($request, $config['client_id'], $config['client_secret'], $config['redirect'], [
            'timeout' => 10, 'connect_timeout' => 5, 'verify' => true, 'allow_redirects' => false,
        ]);
        $provider->setConfig(new Config($config['client_id'], $config['client_secret'], $config['redirect'], $config));
        $provider->setScopes(['openid', 'profile', 'email'])->enablePKCE();

        return $provider;
    }

    public function redirect()
    {
        $nonce = Str::random(64);
        $this->request->session()->put(['oidc_nonce' => $nonce, 'oidc_started_at' => time()]);

        $this->with(['nonce' => $nonce]);

        return parent::redirect();
    }

    /** @return array{subject:string,nama:string,email:string} */
    public function identity(KeycloakTokenValidator $validator): array
    {
        $session = $this->request->session();
        $nonce = $session->pull('oidc_nonce');
        $started = $session->pull('oidc_started_at');
        try {
            // Pull state juga pada error provider: callback tidak dapat diputar ulang.
            if ($this->hasInvalidState() || ! is_string($nonce) || ! is_int($started)
                || time() - $started > 600 || $started > time()
                || $this->request->has('error') || ! is_string($this->request->input('code'))
                || $this->request->input('code') === '' || ! is_string($session->get('code_verifier'))) {
                throw new UnexpectedValueException('Callback SSO tidak valid.');
            }
            $response = $this->getAccessTokenResponse($this->getCode());
            if (! is_string($response['id_token'] ?? null) || ! is_string($response['access_token'] ?? null)) {
                throw new UnexpectedValueException('Respons SSO tidak valid.');
            }
            $issuer = $this->getBaseUrl();
            $metadata = $this->json($issuer.'/.well-known/openid-configuration');
            // Endpoint tetap: metadata/token tidak dapat mengarahkan fetch ke host lain.
            $jwksUrl = $issuer.'/protocol/openid-connect/certs';
            if (($metadata['issuer'] ?? null) !== $issuer || ($metadata['jwks_uri'] ?? null) !== $jwksUrl) {
                throw new UnexpectedValueException('Metadata SSO tidak valid.');
            }
            $claims = $validator->validate($response['id_token'], $this->json($jwksUrl), $issuer, $this->clientId, $nonce);
            $profile = $this->getUserByToken($response['access_token']);
            if (($profile['sub'] ?? null) !== $claims['sub']
                || ! is_string($profile['name'] ?? null) || trim($profile['name']) === '' || mb_strlen($profile['name']) > 255
                || ! is_string($profile['email'] ?? null) || ! filter_var($profile['email'], FILTER_VALIDATE_EMAIL) || strlen($profile['email']) > 255
                || strlen($claims['sub']) > 255) {
                throw new UnexpectedValueException('Profil SSO tidak valid.');
            }

            return ['subject' => $claims['sub'], 'nama' => trim($profile['name']), 'email' => $profile['email']];
        } finally {
            $session->forget(['state', 'code_verifier', 'oidc_nonce', 'oidc_started_at']);
        }
    }

    private function json(string $url): array
    {
        return json_decode((string) $this->getHttpClient()->get($url)->getBody(), true, 32, JSON_THROW_ON_ERROR);
    }

    private static function safeUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        return ($parts['scheme'] ?? '') === 'https'
            || (app()->environment(['local', 'testing']) && ($parts['scheme'] ?? '') === 'http' && in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true));
    }
}
