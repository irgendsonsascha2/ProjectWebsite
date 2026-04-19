<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Auth\User as MongoUser;

#[Fillable(['username', 'email', 'password', 'role', 'created_at', 'email_verified_at', 'remember_token'])]
#[Hidden(['password', 'remember_token'])]
class User extends MongoUser implements MustVerifyEmailContract
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
        ];
    }
}
