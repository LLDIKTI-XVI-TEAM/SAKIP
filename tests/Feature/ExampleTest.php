<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Halaman login publik mengembalikan status HTTP 200 OK
     */
    public function test_the_login_page_returns_a_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
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
