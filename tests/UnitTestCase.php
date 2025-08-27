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

        // Note: We do NOT call withoutEvents() here because some unit tests
        // need to test event dispatching behavior (e.g., sagas)
        // If you need to disable events in a specific test, call $this->withoutEvents()
        // in that test's setUp() method.
    }
}
