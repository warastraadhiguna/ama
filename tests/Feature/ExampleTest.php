<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A guest hitting the root URL is sent to the Web Admin login page —
     * there's no public "welcome" page in this app.
     */
    public function test_the_root_url_redirects_a_guest_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
