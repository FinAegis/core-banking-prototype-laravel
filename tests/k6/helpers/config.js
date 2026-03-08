/**
 * FinAegis Core Banking API - k6 Load Test Configuration
 *
 * Shared configuration for all k6 test scenarios.
 * Override BASE_URL via environment variable for different environments:
 *   k6 run -e BASE_URL=https://staging.example.com/api load-test.js
 */

export const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000/api';

export const HEADERS = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
};

export const TEST_USER = {
    email: 'test@example.com',
    password: 'password',
};

/**
 * Build an authenticated header set with a Bearer token.
 *
 * @param {string} token - The Bearer token
 * @returns {object} Headers object with Authorization
 */
export function authHeaders(token)
{
    return Object.assign({}, HEADERS, {
        Authorization: `Bearer ${token}`,
    });
}
