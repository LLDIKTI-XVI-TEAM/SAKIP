<?php

namespace Tests\Unit;

use App\Services\Auth\KeycloakTokenValidator;
use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class KeycloakTokenValidationTest extends TestCase
{
    private static string $privateKey = '';

    private static array $jwks;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, self::$privateKey);
        $rsa = openssl_pkey_get_details($key)['rsa'];
        self::$jwks = ['keys' => [['kty' => 'RSA', 'kid' => 'test-key', 'alg' => 'RS256', 'use' => 'sig', 'n' => JWT::urlsafeB64Encode($rsa['n']), 'e' => JWT::urlsafeB64Encode($rsa['e'])]]];
    }

    public function test_accepts_signed_identity_for_the_configured_client(): void
    {
        $claims = $this->claims();
        $result = (new KeycloakTokenValidator)->validate($this->sign($claims), self::$jwks, 'https://sso.test/realms/sakip', 'sakip', 'nonce');
        $this->assertSame('subject-fixture', $result['sub']);
    }

    public function test_accepts_small_clock_skew_without_leaking_library_configuration(): void
    {
        $previousLeeway = JWT::$leeway;
        JWT::$leeway = 7;
        try {
            $claims = array_replace($this->claims(), ['iat' => time() + 30, 'nbf' => time() + 30]);
            $result = (new KeycloakTokenValidator)->validate($this->sign($claims), self::$jwks, 'https://sso.test/realms/sakip', 'sakip', 'nonce');
            $this->assertSame('subject-fixture', $result['sub']);
            $this->assertSame(7, JWT::$leeway);
        } finally {
            JWT::$leeway = $previousLeeway;
        }
    }

    #[DataProvider('invalidClaims')]
    public function test_rejects_invalid_identity_claims(array $changes): void
    {
        $this->expectException(UnexpectedValueException::class);
        $previousLeeway = JWT::$leeway;
        try {
            (new KeycloakTokenValidator)->validate($this->sign(array_replace($this->claims(), $changes)), self::$jwks, 'https://sso.test/realms/sakip', 'sakip', 'nonce');
        } finally {
            $this->assertSame($previousLeeway, JWT::$leeway);
        }
    }

    public static function invalidClaims(): array
    {
        return [
            'issuer' => [['iss' => 'https://attacker.test']],
            'audience' => [['aud' => 'another-client']],
            'azp' => [['azp' => 'another-client']],
            'multiple audiences without azp' => [['aud' => ['sakip', 'other'], 'azp' => null]],
            'expired' => [['exp' => 1]],
            'recently expired' => [['exp' => time() - 1]],
            'missing expiry' => [['exp' => null]],
            'future issued' => [['iat' => PHP_INT_MAX]],
            'future not before' => [['nbf' => PHP_INT_MAX]],
            'nonce' => [['nonce' => 'different']],
            'empty subject' => [['sub' => '']],
        ];
    }

    public function test_rejects_invalid_signature(): void
    {
        $token = $this->sign($this->claims());
        $parts = explode('.', $token);
        $parts[2] = JWT::urlsafeB64Encode(str_repeat('x', 256));
        $this->expectException(UnexpectedValueException::class);
        (new KeycloakTokenValidator)->validate(implode('.', $parts), self::$jwks, 'https://sso.test/realms/sakip', 'sakip', 'nonce');
    }

    private function claims(): array
    {
        return ['iss' => 'https://sso.test/realms/sakip', 'aud' => 'sakip', 'sub' => 'subject-fixture', 'nonce' => 'nonce', 'iat' => time() - 1, 'exp' => time() + 300];
    }

    private function sign(array $claims): string
    {
        return JWT::encode($claims, self::$privateKey, 'RS256', 'test-key');
    }
}
