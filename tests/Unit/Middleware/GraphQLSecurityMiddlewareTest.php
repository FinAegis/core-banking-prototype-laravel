<?php

declare(strict_types=1);

use App\Domain\AccountProvisioning\Services\AccountFlagsService;
use App\Http\Middleware\GraphQLQueryCostMiddleware;
use App\Http\Middleware\GraphQLRateLimitMiddleware;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

uses(Tests\TestCase::class);

describe('GraphQLRateLimitMiddleware', function () {
    beforeEach(function () {
        $this->store = new Repository(new ArrayStore());
        $this->limiter = new RateLimiter($this->store);
        $this->middleware = new GraphQLRateLimitMiddleware($this->limiter, app(AccountFlagsService::class));
    });

    it('allows requests under the rate limit', function () {
        $request = Request::create('/graphql', 'POST');

        $response = $this->middleware->handle($request, function () {
            return new JsonResponse(['data' => ['users' => []]]);
        });

        expect($response->getStatusCode())->toBe(200);
        expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue();
        expect($response->headers->has('X-RateLimit-Remaining'))->toBeTrue();
    });

    it('returns 429 when rate limit is exceeded for guest', function () {
        $request = Request::create('/graphql', 'POST');
        $guestLimit = (int) config('lighthouse.rate_limiting.guest_limit', 30);

        // Exhaust the rate limit
        for ($i = 0; $i < $guestLimit; $i++) {
            $this->middleware->handle($request, fn () => new JsonResponse(['data' => []]));
        }

        // Next request should be rate limited
        $response = $this->middleware->handle($request, fn () => new JsonResponse(['data' => []]));

        expect($response->getStatusCode())->toBe(429);
        expect($response->headers->has('Retry-After'))->toBeTrue();
    });

    it('includes GraphQL-specific error format in 429 response', function () {
        $request = Request::create('/graphql', 'POST');
        $guestLimit = (int) config('lighthouse.rate_limiting.guest_limit', 30);

        for ($i = 0; $i < $guestLimit; $i++) {
            $this->middleware->handle($request, fn () => new JsonResponse(['data' => []]));
        }

        $response = $this->middleware->handle($request, fn () => new JsonResponse(['data' => []]));
        $body = json_decode($response->getContent(), true);

        expect($body)->toHaveKey('errors');
        expect($body['errors'][0]['message'])->toContain('rate limit exceeded');
        expect($body['errors'][0]['extensions']['category'])->toBe('rate_limit');
    });

    it('decrements remaining count with each request', function () {
        $request = Request::create('/graphql', 'POST');

        $response1 = $this->middleware->handle($request, fn () => new JsonResponse(['data' => []]));
        $remaining1 = (int) $response1->headers->get('X-RateLimit-Remaining');

        $response2 = $this->middleware->handle($request, fn () => new JsonResponse(['data' => []]));
        $remaining2 = (int) $response2->headers->get('X-RateLimit-Remaining');

        expect($remaining2)->toBeLessThan($remaining1);
    });
});

describe('GraphQLQueryCostMiddleware', function () {
    beforeEach(function () {
        $this->middleware = new GraphQLQueryCostMiddleware();
    });

    it('allows simple queries through', function () {
        $request = Request::create('/graphql', 'POST', [
            'query' => '{ users { id name } }',
        ]);

        $response = $this->middleware->handle($request, function () {
            return new JsonResponse(['data' => ['users' => []]]);
        });

        expect($response->getStatusCode())->toBe(200);
        expect($response->headers->has('X-GraphQL-Cost'))->toBeTrue();
        expect($response->headers->has('X-GraphQL-Max-Cost'))->toBeTrue();
    });

    it('rejects queries that exceed the maximum cost', function () {
        // Build a deeply nested query that will exceed cost limits
        $deepQuery = 'query { ';
        for ($i = 0; $i < 10; $i++) {
            $deepQuery .= "level{$i} { ";
        }
        // Add many fields to drive up cost
        for ($i = 0; $i < 500; $i++) {
            $deepQuery .= "field{$i}\n";
        }
        for ($i = 0; $i < 10; $i++) {
            $deepQuery .= '} ';
        }
        $deepQuery .= '}';

        $request = Request::create('/graphql', 'POST', [
            'query' => $deepQuery,
        ]);

        $response = $this->middleware->handle($request, function () {
            return new JsonResponse(['data' => []]);
        });

        expect($response->getStatusCode())->toBe(400);
        $body = json_decode($response->getContent(), true);
        expect($body['errors'][0]['extensions']['category'])->toBe('query_cost');
    });

    it('passes through requests with no query', function () {
        $request = Request::create('/graphql', 'POST');

        $response = $this->middleware->handle($request, function () {
            return new JsonResponse(['data' => []]);
        });

        expect($response->getStatusCode())->toBe(200);
    });

    it('assigns higher cost to mutations than queries', function () {
        $queryRequest = Request::create('/graphql', 'POST', [
            'query' => '{ users { id } }',
        ]);
        $mutationRequest = Request::create('/graphql', 'POST', [
            'query' => 'mutation { createUser(name: "Test") { id } }',
        ]);

        $queryResponse = $this->middleware->handle($queryRequest, fn () => new JsonResponse(['data' => []]));
        $mutationResponse = $this->middleware->handle($mutationRequest, fn () => new JsonResponse(['data' => []]));

        $queryCost = (int) $queryResponse->headers->get('X-GraphQL-Cost');
        $mutationCost = (int) $mutationResponse->headers->get('X-GraphQL-Cost');

        expect($mutationCost)->toBeGreaterThan($queryCost);
    });

    it('assigns higher cost to subscriptions than mutations', function () {
        $mutationRequest = Request::create('/graphql', 'POST', [
            'query' => 'mutation { createUser(name: "Test") { id } }',
        ]);
        $subscriptionRequest = Request::create('/graphql', 'POST', [
            'query' => 'subscription { onUserCreated { id } }',
        ]);

        $mutationResponse = $this->middleware->handle($mutationRequest, fn () => new JsonResponse(['data' => []]));
        $subscriptionResponse = $this->middleware->handle($subscriptionRequest, fn () => new JsonResponse(['data' => []]));

        $mutationCost = (int) $mutationResponse->headers->get('X-GraphQL-Cost');
        $subscriptionCost = (int) $subscriptionResponse->headers->get('X-GraphQL-Cost');

        expect($subscriptionCost)->toBeGreaterThan($mutationCost);
    });

    it('includes cost details in the error response', function () {
        $deepQuery = 'query { ';
        for ($i = 0; $i < 10; $i++) {
            $deepQuery .= "level{$i} { ";
        }
        for ($i = 0; $i < 500; $i++) {
            $deepQuery .= "field{$i}\n";
        }
        for ($i = 0; $i < 10; $i++) {
            $deepQuery .= '} ';
        }
        $deepQuery .= '}';

        $request = Request::create('/graphql', 'POST', [
            'query' => $deepQuery,
        ]);

        $response = $this->middleware->handle($request, fn () => new JsonResponse([]));
        $body = json_decode($response->getContent(), true);

        expect($body['errors'][0]['extensions'])->toHaveKey('cost');
        expect($body['errors'][0]['extensions'])->toHaveKey('max_cost');
        expect($body['errors'][0]['extensions']['cost'])->toBeGreaterThan($body['errors'][0]['extensions']['max_cost']);
    });
});
