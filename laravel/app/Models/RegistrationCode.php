<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Registrierungscodes für invite-basierte Registrierung (gleiche Collection wie die PHP-App).
 */
class RegistrationCode extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'registration_codes';

    public $timestamps = false;

    protected $fillable = [
        'code',
        'role',
        'is_used',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
