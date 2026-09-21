<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Konfigurasi provider yang belum tersedia menampilkan halaman pemulihan SSO.
     */
    public function test_unconfigured_login_redirects_to_the_sso_error_page(): void
    {
        config(['services.keycloak.base_url' => '']);
        $response = $this->get('/login');

        $response->assertRedirect('/auth/error');
        $this->get('/auth/error')->assertOk();
    }

    /**
     * Rute root / melakukan pengalihan (redirect)
     */
    public function test_root_route_redirects_appropriately(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
    }
}
