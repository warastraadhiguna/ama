<?php

namespace Modules\Identity\Presentation\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web Admin session auth (docs section 25.2: secure session cookie + CSRF)
 * — distinct from the mobile access+refresh token flow in AuthController.
 * Any active user can sign in here; what they can actually see once inside
 * is gated per-page/per-resource by permission (same as the API), not at
 * the login gate.
 */
class WebAuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.']);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => 'This account has been deactivated.']);
        }

        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
