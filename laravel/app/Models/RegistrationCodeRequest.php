<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * E-Mail-Anfragen für Registrierungscodes (gleiche Collection wie die PHP-App).
 */
class RegistrationCodeRequest extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'registration_code_requests';

    public $timestamps = false;

    protected $fillable = [
        'email',
        'token',
        'code',
        'created_at',
        'expires_at',
        'verified_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
