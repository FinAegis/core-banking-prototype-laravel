/**
 * FinAegis Core Banking API - Smoke Test Scenario
 *
 * Lightweight verification that critical health and monitoring endpoints
 * are responding correctly. Designed for 1 VU over 30 seconds.
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, HEADERS } from '../helpers/config.js';

/**
 * Smoke test: verify basic health endpoints return 200 within 200ms.
 */
export function smoke()
{
    // Health check
    const healthRes = http.get(`${BASE_URL} / monitoring / health`, { headers: HEADERS });
    check(healthRes, {
        'health: status is 200': (r) => r.status === 200,
        'health: response time < 200ms': (r) => r.timings.duration < 200,
    });

    sleep(0.5);

    // Readiness check
    const readyRes = http.get(`${BASE_URL} / monitoring / ready`, { headers: HEADERS });
    check(readyRes, {
        'ready: status is 200': (r) => r.status === 200,
        'ready: response time < 200ms': (r) => r.timings.duration < 200,
    });

    sleep(0.5);

    // Liveness check
    const aliveRes = http.get(`${BASE_URL} / monitoring / alive`, { headers: HEADERS });
    check(aliveRes, {
        'alive: status is 200': (r) => r.status === 200,
        'alive: response time < 200ms': (r) => r.timings.duration < 200,
    });

    sleep(0.5);

    // API root
    const rootRes = http.get(`${BASE_URL} / `, { headers: HEADERS });
    check(rootRes, {
        'api root: status is 200': (r) => r.status === 200,
        'api root: response time < 200ms': (r) => r.timings.duration < 200,
        'api root: contains version': (r) => r.body.includes('version'),
    });

    sleep(0.5);
}
