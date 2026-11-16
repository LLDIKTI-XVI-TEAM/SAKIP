<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use UnexpectedValueException;

class KeycloakTokenValidator
{
    private const CLOCK_SKEW_SECONDS = 60;

    /** @return array<string, mixed> */
    public function validate(#[\SensitiveParameter] string $token, array $jwks, string $issuer, string $client, #[\SensitiveParameter] string $nonce): array
    {
        // Algoritma berasal dari kebijakan aplikasi, bukan header token yang belum dipercaya.
        $keys = array_values(array_filter($jwks['keys'] ?? [], fn (array $key) => ($key['kty'] ?? null) === 'RSA'
            && ($key['alg'] ?? 'RS256') === 'RS256' && ($key['use'] ?? 'sig') === 'sig'));
        // Jam issuer dapat sedikit berbeda; jangan biarkan toleransi library bocor ke consumer lain.
        $previousLeeway = JWT::$leeway;
        try {
            JWT::$leeway = self::CLOCK_SKEW_SECONDS;
            $claims = (array) JWT::decode($token, JWK::parseKeySet(['keys' => $keys], 'RS256'));
        } finally {
            JWT::$leeway = $previousLeeway;
        }
        $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? null];

        if (($claims['iss'] ?? null) !== $issuer
            || ! in_array($client, $audiences, true)
            || (isset($claims['azp']) && $claims['azp'] !== $client)
            || (count($audiences) > 1 && ($claims['azp'] ?? null) !== $client)
            || ! is_int($claims['exp'] ?? null) || $claims['exp'] <= time()
            || ! is_int($claims['iat'] ?? null) || $claims['iat'] > time() + self::CLOCK_SKEW_SECONDS
            || ! is_string($claims['sub'] ?? null) || trim($claims['sub']) === ''
            || ! is_string($claims['nonce'] ?? null) || $nonce === '' || ! hash_equals($nonce, $claims['nonce'])) {
            throw new UnexpectedValueException('Identitas SSO tidak valid.');
        }

        return $claims;
    }
}
