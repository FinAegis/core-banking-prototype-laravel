/**
 * FinAegis Core Banking API - Load Test Scenario
 *
 * Standard load test mixing public endpoints and authentication flow.
 * Designed for ramping up to 50 VUs with a 3-minute sustained hold.
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, HEADERS, TEST_USER } from '../helpers/config.js';

/**
 * Load test: mix of public browsing and authentication attempts.
 */
export function load()
{
    // GET API root - simulates landing page discovery
    const rootRes = http.get(`${BASE_URL} / `, { headers: HEADERS });
    check(rootRes, {
        'load api root: status is 200': (r) => r.status === 200,
    });

    sleep(1);

    // GET health check - simulates monitoring probe
    const healthRes = http.get(`${BASE_URL} / monitoring / health`, { headers: HEADERS });
    check(healthRes, {
        'load health: status is 200': (r) => r.status === 200,
    });

    sleep(1);

    // POST auth/login - simulates user login attempt
    const loginPayload = JSON.stringify({
        email: TEST_USER.email,
        password: TEST_USER.password,
    });
    const loginRes = http.post(`${BASE_URL} / auth / login`, loginPayload, { headers: HEADERS });
    check(loginRes, {
        'load login: status is 200 or 401 or 422': (r) =>
            r.status === 200 || r.status === 401 || r.status === 422,
        'load login: response time < 500ms': (r) => r.timings.duration < 500,
    });

    // If login succeeded, hit an authenticated endpoint
    if (loginRes.status === 200) {
        try {
            const body = JSON.parse(loginRes.body);
            const token = body.token || (body.data && body.data.token);
            if (token) {
                const authHeaderSet = Object.assign({}, HEADERS, {
                    Authorization: `Bearer ${token}`,
                });

                sleep(1);

                const modulesRes = http.get(`${BASE_URL} / v2 / modules`, { headers: authHeaderSet });
                check(modulesRes, {
                    'load modules: status is 200': (r) => r.status === 200,
                });
            }
        } catch (_e) {
            // Login response was not JSON or token not found; continue
        }
    }

    sleep(1);

    // GET readiness check
    const readyRes = http.get(`${BASE_URL} / monitoring / ready`, { headers: HEADERS });
    check(readyRes, {
        'load ready: status is 200': (r) => r.status === 200,
    });

    sleep(1);
}
