<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LegacySiteHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $handoff = app(LegacySiteHandoff::class);
        if ($handoff->isConfigured()) {
            return redirect()->away($handoff->redirectUrl($request->user()));
        }

        $legacy = rtrim((string) config('legacy.site_url', ''), '/');
        if ($legacy !== '') {
            return redirect()->away($legacy.'/index.php?page=home');
        }

        return redirect()->intended('/');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        $legacy = rtrim((string) config('legacy.site_url', ''), '/');
        if ($legacy !== '') {
            return redirect()->away($legacy.'/index.php?page=login');
        }

        return redirect('/');
    }
}
