<?php

namespace App\Services;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\RegistrationCode;
use App\Models\RegistrationCodeRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisterInvitedUser
{
    /**
     * Invite-only Registrierung (gleiche Regeln wie {@see RegisteredUserController::store}).
     *
     * @param  array<string, mixed>  $input
     *                                       Erwartete Keys: registration_code, username, email, password, password_confirmation
     *
     * @throws ValidationException
     */
    public function register(array $input): User
    {
        $validated = Validator::make($input, [
            'registration_code' => ['required', 'string'],
            'username' => ['required', 'string', 'regex:/^[a-z0-9._-]{3,20}$/'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'content_responsibility_consent' => ['accepted'],
        ])->validate();

        $code = strtoupper(trim($validated['registration_code']));

        $validCode = RegistrationCode::query()
            ->where('code', $code)
            ->where('is_used', false)
            ->first();

        if ($validCode === null) {
            throw ValidationException::withMessages([
                'registration_code' => [__('Der Code ist ungültig oder bereits verbraucht.')],
            ]);
        }

        $username = strtolower($validated['username']);

        if (User::query()->where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'username' => [__('Dieser Username wird bereits verwendet.')],
            ]);
        }

        $emailVerifiedAt = $this->resolveEmailVerifiedAt(
            (string) $validated['email'],
            $code
        );

        $user = User::create([
            'username' => $username,
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validCode->role,
            'created_at' => now(),
            'email_verified_at' => $emailVerifiedAt,
            'content_responsibility_consent_at' => now(),
        ]);

        $validCode->update(['is_used' => true]);

        event(new Registered($user));

        return $user;
    }

    /**
     * Setzt den E-Mail-Nachweis-Zeitstempel für die klassische Site (kein Login-Gate).
     *
     * - Reiner Admin-/Invite-Code (nicht an eine Anfrage gebunden): immer gesetzt.
     * - Code aus dem Anfrage-Flow: nur wenn E-Mail zu einer verifizierten Anfrage passt.
     */
    private function resolveEmailVerifiedAt(string $email, string $code): ?Carbon
    {
        $email = strtolower(trim($email));
        $code = strtoupper(trim($code));

        $request = RegistrationCodeRequest::query()
            ->where('code', $code)
            ->first();

        if ($request === null) {
            return now();
        }

        if ($request->verified_at === null) {
            return null;
        }

        $requestEmail = strtolower(trim((string) ($request->email ?? '')));
        if ($requestEmail === '' || $requestEmail !== $email) {
            return null;
        }

        return now();
    }
}
