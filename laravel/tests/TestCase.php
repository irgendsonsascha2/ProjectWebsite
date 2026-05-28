<?php

namespace Tests;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public const VALID_PASSWORD = UserFactory::DEFAULT_PASSWORD;

    /**
     * MongoDB ohne Replica Set: Laravel-RefreshDatabase-Transaktionen sind nicht möglich.
     * Daten werden weiter mit migrate:fresh einmal aufgebaut; Tests sollten keine festen IDs annehmen.
     *
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        parent::setUp();

        // @vite in Layouts — Manifest erst nach `npm run build`
        $this->withoutVite();
    }
}
