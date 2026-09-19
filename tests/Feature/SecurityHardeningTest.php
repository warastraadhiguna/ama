<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_and_api_responses_carry_the_security_headers(): void
    {
        foreach (['/login', '/api/v1/me'] as $path) {
            $response = $this->get($path, ['Accept' => 'text/html,application/json']);

            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        }
    }

    public function test_hsts_is_only_sent_over_https_in_production(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->app->detectEnvironment(fn () => 'production');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_web_login_is_rate_limited_per_email_and_ip(): void
    {
        $user = User::factory()->create(['password' => 'correct-password-1']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        // Sixth attempt is throttled even with the CORRECT password.
        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password-1'])
            ->assertStatus(429);
        $this->assertGuest();

        // A different email from the same IP still has its own budget.
        $other = User::factory()->create(['password' => 'other-password-1']);
        $this->post('/login', ['email' => $other->email, 'password' => 'other-password-1'])
            ->assertRedirect('/dashboard');
    }

    public function test_the_api_limiter_is_keyed_per_authenticated_user_not_per_ip(): void
    {
        $limiter = RateLimiter::limiter('api');
        $a = User::factory()->create();
        $b = User::factory()->create();

        $keyFor = function (User $user) use ($limiter): string {
            $request = \Illuminate\Http\Request::create('/api/v1/me');
            $request->setUserResolver(fn () => $user);
            $request->setRouteResolver(fn () => null);
            auth('sanctum')->setUser($user);

            /** @var Limit $limit */
            $limit = $limiter($request);

            return $limit->key;
        };

        $this->assertNotSame($keyFor($a), $keyFor($b));
        $this->assertSame(120, $limiter(\Illuminate\Http\Request::create('/x'))->maxAttempts);
    }
}
