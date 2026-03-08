/**
 * FinAegis Core Banking API - k6 Load Test Suite
 *
 * Main entry point for k6 load testing. Defines three scenarios:
 *   - smoke:  1 VU, 30s  -- basic health endpoint verification
 *   - load:   up to 50 VUs, ~5m  -- standard mixed-endpoint load
 *   - stress: up to 100 VUs, ~9m -- high-concurrency stress test
 *
 * Usage:
 *   k6 run tests/k6/load-test.js
 *   k6 run tests/k6/load-test.js --out json=load-test-results.json
 *   k6 run -e BASE_URL=https://staging.example.com/api tests/k6/load-test.js
 */

import { smoke } from './scenarios/smoke.js';
import { load } from './scenarios/load.js';
import { stress } from './scenarios/stress.js';

export const options = {
    scenarios: {
        smoke: {
            executor: 'constant-vus',
            vus: 1,
            duration: '30s',
            exec: 'smokeTest',
            tags: { scenario: 'smoke' },
        },
        load: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '1m', target: 50 },   // ramp up
                { duration: '3m', target: 50 },   // hold steady
                { duration: '1m', target: 0 },    // ramp down
            ],
            exec: 'loadTest',
            startTime: '35s', // start after smoke completes
            tags: { scenario: 'load' },
        },
        stress: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '2m', target: 100 },  // ramp up
                { duration: '5m', target: 100 },  // hold steady
                { duration: '2m', target: 0 },    // ramp down
            ],
            exec: 'stressTest',
            startTime: '5m45s', // start after load completes
            tags: { scenario: 'stress' },
        },
    },
    thresholds: {
        // Per-scenario thresholds (CI uses single-threaded php artisan serve)
        'http_req_duration{scenario:smoke}': ['p(95)<500'],
        'http_req_failed{scenario:smoke}': ['rate<0.01'],
        'http_req_duration{scenario:load}': ['p(95)<2000'],
        'http_req_failed{scenario:load}': ['rate<0.10'],
        // Stress test finds breaking points — lenient thresholds for CI
        'http_req_duration{scenario:stress}': ['p(95)<5000'],
        'http_req_failed{scenario:stress}': ['rate<0.80'],
    },
};

// Scenario executor functions
export function smokeTest()
{
    smoke();
}

export function loadTest()
{
    load();
}

export function stressTest()
{
    stress();
}

// Default function runs all scenarios sequentially (used when no scenarios are specified)
export default function () {
    smoke();
    load();
    stress();
}
