<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Wichtig: In MongoDB kann es (historisch) doppelte E-Mails geben.
        // Dann wäre Passwort-Reset mehrdeutig und könnte "den falschen" Account verändern.
        $email = strtolower(trim((string) $request->input('email', '')));
        $matches = User::query()->where('email', $email)->count();
        if ($matches > 1) {
            return back()
                ->withInput(['email' => $email])
                ->withErrors([
                    'email' => 'Zu dieser E-Mail-Adresse existieren mehrere Accounts. Passwort-Reset ist aus Sicherheitsgründen deaktiviert. Bitte Admin kontaktieren (Accounts zusammenführen / Duplikate löschen).',
                ]);
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
