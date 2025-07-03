<?php

namespace Tests;

use App\Models\User;
use Livewire\LivewireServiceProvider;

abstract class FilamentTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate an admin user
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
        ]);
        
        $this->actingAs($admin);
    }

    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [
            LivewireServiceProvider::class,
        ]);
    }
}