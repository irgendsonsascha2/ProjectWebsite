<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LegacySiteHandoff;
use App\Services\RegisterInvitedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        return view('auth.register', [
            'prefilled_code' => old('registration_code', $request->query('reg_token', '')),
        ]);
    }

    /**
     * Handle an incoming registration request (invite-only, wie die PHP-App).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = app(RegisterInvitedUser::class)->register($request->only([
            'registration_code',
            'username',
            'email',
            'password',
            'password_confirmation',
        ]));

        Auth::login($user);

        $handoff = app(LegacySiteHandoff::class);
        if ($handoff->isConfigured()) {
            return redirect()->away($handoff->redirectUrl($user));
        }

        return redirect(route('dashboard', absolute: false));
    }
}
