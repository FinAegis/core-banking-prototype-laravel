<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration File
|--------------------------------------------------------------------------
|
| This file configures Pest testing framework settings including
| timeout limits and execution behavior for development environments.
|
*/

// Configure timeout for local development
// Default is 2 minutes (120 seconds), increased to 5 minutes (300 seconds)
if (!getenv('CI') && !getenv('GITHUB_ACTIONS')) {
    // Set PHP execution time limit for local development
    ini_set('max_execution_time', '300');
    
    // Configure memory limit for local tests
    ini_set('memory_limit', '2G');
    
    // Enable parallel testing by default in local environment
    if (!getenv('PEST_PARALLEL')) {
        putenv('PEST_PARALLEL=true');
    }
    
    // Configure test timeout per test
    // This prevents individual tests from running too long
    define('PEST_TEST_TIMEOUT', 30); // 30 seconds per test
}

// For CI environment, use the pest.ci.php configuration
if (getenv('CI') === 'true' || getenv('GITHUB_ACTIONS') === 'true') {
    require __DIR__ . '/pest.ci.php';
}

// Include the main Pest configuration
require __DIR__ . '/tests/Pest.php';
