<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Conservative response headers for Web Admin and the API. Deliberately no
 * Content-Security-Policy yet: the admin pages use inline Inertia bootstrap
 * data and Leaflet loads OpenStreetMap tiles/MinIO photos from other
 * origins, so a CSP needs to be designed against the real production hosts
 * rather than guessed here.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Only over HTTPS, and only where we are really serving production —
        // sending HSTS from a local http:// dev host is meaningless and
        // pinning a hostname to HTTPS by accident is hard to undo.
        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
