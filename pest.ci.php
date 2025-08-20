<?php

/*
|--------------------------------------------------------------------------
| Pest CI Configuration
|--------------------------------------------------------------------------
|
| This file contains optimizations for running Pest in CI environments
| with limited memory. It reduces memory usage during test discovery
| and execution.
|
*/

// Disable parallel execution in CI to reduce memory usage
if (getenv('CI') === 'true' || getenv('GITHUB_ACTIONS') === 'true') {
    // Force sequential execution
    putenv('PEST_PARALLEL=false');
    
    // Optimize memory usage
    ini_set('memory_limit', '768M');
    
    // Disable Xdebug if not needed for coverage
    if (!getenv('COVERAGE_ENABLED')) {
        ini_set('xdebug.mode', 'off');
    }
}

// Configure test discovery to be more memory efficient
uses()
    ->beforeAll(function () {
        // Clear any caches before test suite
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    })
    ->afterEach(function () {
        // Force garbage collection after each test
        if (getenv('CI') === 'true') {
            gc_collect_cycles();
        }
    })
    ->in('Feature', 'Unit', 'Domain', 'Security', 'Console');

// Limit the number of tests loaded at once
if (getenv('CI') === 'true') {
    // Use a custom test loader that processes files in chunks
    config()->set('testLoader.chunkSize', 50);
}