<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Auth\User as MongoUser;

#[Fillable([
    'username',
    'email',
    'password',
    'role',
    'created_at',
    'email_verified_at',
    'remember_token',
    'two_factor_enabled',
    'two_factor_totp_secret',
    'two_factor_backup_codes',
    'two_factor_confirmed_at',
])]
#[Hidden(['password', 'remember_token'])]
/**
 * Legacy-Auth nutzt keine Laravel-E-Mail-Verifikation.
 * `email_verified_at` wird bei Registrierung gesetzt (Invite-Code / Anfrage-Flow).
 * Laravel `/dashboard` und `/profile` nutzen weiterhin das `verified`-Middleware (Feld am User).
 */
class User extends MongoUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Immer Mongo (nicht die Laravel-Standard-DB aus der .env für SQLite/MySQL). */
    protected $connection = 'mongodb';

    protected $collection = 'users';

    /** Die alte App speichert nur `created_at`, keine Laravel-Standard-Zeitstempel. */
    public $timestamps = false;

    /**
     * Lesbarer Name für Navigation/Breeze (kein eigenes Feld in Mongo).
     */
    public function getNameAttribute(): string
    {
        $username = $this->attributes['username'] ?? null;
        if (is_string($username) && $username !== '') {
            return $username;
        }

        $email = $this->attributes['email'] ?? '';

        return is_string($email) && str_contains($email, '@')
            ? strstr($email, '@', true) ?: $email
            : 'User';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'created_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
