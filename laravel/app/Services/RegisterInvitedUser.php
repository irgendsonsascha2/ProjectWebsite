<?php

namespace App\Services;

use App\Models\RegistrationCode;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisterInvitedUser
{
    /**
     * Invite-only Registrierung (gleiche Regeln wie {@see \App\Http\Controllers\Auth\RegisteredUserController::store}).
     *
     * @param  array<string, mixed>  $input
     *         Erwartete Keys: registration_code, username, email, password, password_confirmation
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

        $user = User::create([
            'username' => $username,
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validCode->role,
            'created_at' => now(),
            'content_responsibility_consent_at' => now(),
        ]);

        $validCode->update(['is_used' => true]);

        event(new Registered($user));

        return $user;
    }
}
