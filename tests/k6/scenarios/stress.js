/**
 * FinAegis Core Banking API - Stress Test Scenario
 *
 * High-concurrency stress test targeting monitoring and public endpoints.
 * Designed for ramping up to 100 VUs over extended durations to find
 * breaking points and verify graceful degradation.
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, HEADERS } from '../helpers/config.js';

/**
 * Stress test: rapid concurrent requests across multiple endpoints.
 */
export function stress()
{
    // Batch concurrent requests to multiple monitoring endpoints
    const responses = http.batch([
        ['GET', `${BASE_URL} / monitoring / health`, null, { headers: HEADERS, tags: { endpoint: 'health' } }],
        ['GET', `${BASE_URL} / monitoring / ready`, null, { headers: HEADERS, tags: { endpoint: 'ready' } }],
        ['GET', `${BASE_URL} / monitoring / alive`, null, { headers: HEADERS, tags: { endpoint: 'alive' } }],
        ['GET', `${BASE_URL} / `, null, { headers: HEADERS, tags: { endpoint: 'root' } }],
    ]);

    // Check health response
    check(responses[0], {
        'stress health: status is 200': (r) => r.status === 200,
        'stress health: response time < 500ms': (r) => r.timings.duration < 500,
    });

    // Check readiness response
    check(responses[1], {
        'stress ready: status is 200': (r) => r.status === 200,
        'stress ready: response time < 500ms': (r) => r.timings.duration < 500,
    });

    // Check liveness response
    check(responses[2], {
        'stress alive: status is 200': (r) => r.status === 200,
        'stress alive: response time < 500ms': (r) => r.timings.duration < 500,
    });

    // Check API root response
    check(responses[3], {
        'stress root: status is 200': (r) => r.status === 200,
        'stress root: response time < 500ms': (r) => r.timings.duration < 500,
    });

    sleep(0.3);

    // Prometheus metrics endpoint (heavier response)
    const metricsRes = http.get(`${BASE_URL} / monitoring / metrics`, { headers: HEADERS });
    check(metricsRes, {
        'stress metrics: status is 200': (r) => r.status === 200,
        'stress metrics: response time < 1000ms': (r) => r.timings.duration < 1000,
    });

    sleep(0.3);

    // Rapid-fire health checks to simulate aggressive monitoring
    for (let i = 0; i < 3; i++) {
        const rapidRes = http.get(`${BASE_URL} / monitoring / health`, { headers: HEADERS });
        check(rapidRes, {
            'stress rapid health: status is 200': (r) => r.status === 200,
        });
    }

    sleep(0.2);
}
