<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;

abstract class UnitTestCase extends BaseTestCase
{
    use CreatesApplication;
    
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Close any Mockery mocks
        Mockery::close();
    }
    
    /**
     * Set up the test case.
     * Unit tests should not use database, so we don't use RefreshDatabase trait.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable model events to prevent database operations
        if (method_exists($this, 'withoutEvents')) {
            $this->withoutEvents();
        }
    }
}