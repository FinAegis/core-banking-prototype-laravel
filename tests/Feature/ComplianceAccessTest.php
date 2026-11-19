<?php

declare(strict_types=1);

use App\Domain\Compliance\Contracts\SanctionsScreeningInterface;
use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\Compliance\Models\KycDocument;
use App\Domain\Compliance\Models\KycVerification;
use App\Domain\Compliance\Services\GdprService;
use App\Http\Middleware\MetricsMiddleware;
use App\Http\Middleware\TracingMiddleware;
use App\Jobs\ProcessOndatoWebhook;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
| Access rules for the compliance REST API (/api/compliance, /api/v1/compliance,
| /api/v2/compliance, /api/v1/user/data-export, /api/kyc), the regulatory,
| transaction monitoring, audit, SAR and CTR routes, and the GraphQL
| compliance schema:
| - self-service endpoints act on the signed-in user only (KYC, GDPR consents,
|   export and deletion); a KYC document or a GDPR export of another user is
|   readable by compliance staff only;
| - compliance management, certification, GDPR register, DPIA, breach and
|   retention, regulatory reports, transaction monitoring, audit, SAR and CTR
|   endpoints are limited to compliance staff (StaffRoles::COMPLIANCE);
| - the rules hold for every host registration (main domain under /api and the
|   prefix-less api.* host).
*/

beforeEach(function (): void {
    Cache::flush();
    config(['cache.default' => 'array']);
});

function cxaUser(?string $role = null): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        $user->assignRole($role);
    }

    return $user;
}

/**
 * Self-service routes that act on the signed-in user (or are informational /
 * public by design), relative to the host prefix. Every other route in the
 * inventory families must carry the compliance staff role middleware.
 *
 * @return list<string>
 */
function cxaSelfServiceRoutes(): array
{
    return [
        // KYC: the caller's own record; document download also staff (controller check)
        'GET compliance/kyc/status',
        'GET compliance/kyc/requirements',
        'POST compliance/kyc/submit',
        'POST compliance/kyc/documents',
        'GET compliance/kyc/documents/{documentId}/download',
        'POST compliance/kyc/ondato/start',
        'GET compliance/kyc/ondato/status/{verificationId}',
        // Legacy KYC upload (deprecated alias of compliance/kyc/documents)
        'POST kyc/documents',
        // GDPR: the caller's own consents, export and deletion; static policy text
        'GET compliance/gdpr/consent',
        'POST compliance/gdpr/consent',
        'POST compliance/gdpr/export',
        'GET compliance/gdpr/export/{exportId}',
        'GET compliance/gdpr/export/{exportId}/download',
        'POST compliance/gdpr/delete',
        'GET compliance/gdpr/retention-policy',
        'GET compliance/gdpr/v2/consent',
        'POST compliance/gdpr/v2/consent',
        // GDPR export aliases used by the mobile app
        'POST v1/user/data-export',
        'GET v1/user/data-export/{exportId}',
        'GET v1/user/data-export/{exportId}/download',
        // Address screening before a send (authenticated, throttled, public data)
        'GET v1/compliance/check-address',
        // v2: the caller's own KYC, AML status and risk profile
        'GET v2/compliance/kyc/status',
        'POST v2/compliance/kyc/start',
        'POST v2/compliance/kyc/{verificationId}/document',
        'POST v2/compliance/kyc/{verificationId}/selfie',
        'GET v2/compliance/aml/status',
        'POST v2/compliance/aml/request-screening',
        'GET v2/compliance/risk-profile',
        'POST v2/compliance/check-transaction',
    ];
}

/**
 * Checks every route of the inventory families registered on the router:
 * each one requires authentication, and each method that is not
 * self-service carries the compliance staff role. Returns the checked
 * "METHOD uri" keys relative to $prefix.
 *
 * @return list<string>
 */
function cxaAssertInventory(Router $router, string $prefix): array
{
    $staffMiddleware = RoleMiddleware::using(StaffRoles::COMPLIANCE);
    $selfService = cxaSelfServiceRoutes();
    $families = '#^(?:(?:v1/|v2/)?compliance|v1/user/data-export|kyc|regulatory|transaction-monitoring|audit|sar|ctr)(?:/|$)#';
    $seen = [];

    /** @var RoutingRoute $route */
    foreach ($router->getRoutes()->getRoutes() as $route) {
        $uri = $route->uri();
        if ($prefix !== '' && ! str_starts_with($uri, $prefix)) {
            continue;
        }
        $relative = substr($uri, strlen($prefix));
        if (! preg_match($families, $relative)) {
            continue;
        }

        $middleware = $router->gatherRouteMiddleware($route);
        $authenticated = collect($middleware)->contains(
            fn ($m): bool => is_string($m) && (str_starts_with($m, 'Illuminate\Auth\Middleware\Authenticate:sanctum') || str_starts_with($m, 'App\Http\Middleware\AuthenticateApiOrSanctum'))
        );
        expect($authenticated)->toBeTrue("{$uri} must require authentication");

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $key = "{$method} {$relative}";
            $seen[] = $key;

            if (in_array($key, $selfService, true)) {
                continue;
            }

            expect(in_array($staffMiddleware, $middleware, true))->toBeTrue("{$method} {$uri} must be limited to compliance staff");
        }
    }

    // The self-service list stays in step with the registered routes.
    expect(array_values(array_diff($selfService, $seen)))->toBe([]);

    return $seen;
}

describe('compliance route inventory', function (): void {
    it('requires authentication on every compliance route and the staff role on every route that is not self-service', function (): void {
        $seen = cxaAssertInventory(app(Router::class), 'api/');

        // The staff-only families are all present in the main-domain registration.
        foreach (['GET compliance/dashboard', 'GET compliance/certification/evidence', 'GET compliance/gdpr/v2/register', 'GET transaction-monitoring', 'GET regulatory/reports', 'GET audit/logs', 'GET sar', 'GET ctr'] as $key) {
            expect($seen)->toContain($key);
        }
    });

    it('applies the same rules to the prefix-less api.* host registration', function (): void {
        // Boot a fresh application whose console request host is api.zelta.app,
        // which selects the api.* branch in bootstrap/app.php.
        $app = require base_path('bootstrap/app.php');
        $app->afterBootstrapping(
            LoadConfiguration::class,
            static function ($app): void {
                $app['config']->set('app.url', 'http://api.zelta.app');
            }
        );
        $app->make(Kernel::class)->bootstrap();
        $this->app = $app;
        $this->artisan('migrate', ['--force' => true]);

        /** @var Router $router */
        $router = $app->make(Router::class);
        $uris = collect($router->getRoutes()->getRoutes())->map(fn (RoutingRoute $route): string => $route->uri());
        expect($uris)->toContain('compliance/dashboard')
            ->and($uris)->toContain('compliance/kyc/ondato/status/{verificationId}')
            ->and($uris)->not->toContain('api/compliance/dashboard');

        $seen = cxaAssertInventory($router, '');
        expect($seen)->toContain('GET compliance/dashboard')
            ->and($seen)->toContain('GET transaction-monitoring');
    });
});

describe('staff-only compliance endpoints', function (): void {
    it('answer 401 without a token, 403 to customers and 200 to compliance staff', function (string $method, string $path, array $body, int $staffStatus): void {
        $this->json($method, $path, $body)->assertUnauthorized();

        Sanctum::actingAs(cxaUser(), ['read', 'write', 'delete']);
        $this->json($method, $path, $body)->assertForbidden();

        Sanctum::actingAs(cxaUser('compliance_officer'), ['read', 'write', 'delete']);
        $this->json($method, $path, $body)->assertStatus($staffStatus);
    })->with([
        'dashboard'                => ['GET', '/api/compliance/dashboard', [], 200],
        'violations'               => ['GET', '/api/compliance/violations', [], 200],
        'violation details'        => ['GET', '/api/compliance/violations/1', [], 200],
        'resolve violation'        => ['POST', '/api/compliance/violations/1/resolve', [], 200],
        'rules'                    => ['GET', '/api/compliance/rules', [], 200],
        'rules by jurisdiction'    => ['GET', '/api/compliance/rules/EU', [], 200],
        'checks'                   => ['GET', '/api/compliance/checks', [], 200],
        'run check'                => ['POST', '/api/compliance/checks/run', [], 200],
        'certifications'           => ['GET', '/api/compliance/certifications', [], 200],
        'renew certification'      => ['POST', '/api/compliance/certifications/renew', [], 200],
        'policies'                 => ['GET', '/api/compliance/policies', [], 200],
        'update policy'            => ['PUT', '/api/compliance/policies/1', [], 200],
        'evidence'                 => ['GET', '/api/compliance/certification/evidence', [], 200],
        'incidents'                => ['GET', '/api/compliance/certification/incidents', [], 200],
        'data classification'      => ['GET', '/api/compliance/certification/pci/classification', [], 200],
        'key rotation status'      => ['GET', '/api/compliance/certification/pci/key-rotation', [], 200],
        'data residency transfers' => ['GET', '/api/compliance/certification/data-residency/transfers', [], 200],
        'processing register'      => ['GET', '/api/compliance/gdpr/v2/register', [], 200],
        'dpia summary'             => ['GET', '/api/compliance/gdpr/v2/dpia', [], 200],
        'breach summary'           => ['GET', '/api/compliance/gdpr/v2/breaches', [], 200],
        'breach deadlines'         => ['GET', '/api/compliance/gdpr/v2/breaches/deadlines', [], 200],
        'retention summary'        => ['GET', '/api/compliance/gdpr/v2/retention', [], 200],
        'report breach'            => ['POST', '/api/compliance/gdpr/v2/breaches', ['title' => 'T', 'description' => 'D', 'severity' => 'low'], 201],
    ]);

    it('accepts every compliance staff role', function (string $role): void {
        Sanctum::actingAs(cxaUser($role), ['read', 'write', 'delete']);
        $this->getJson('/api/compliance/dashboard')->assertOk();
        $this->getJson('/api/compliance/gdpr/v2/dpia')->assertOk();
    })->with(StaffRoles::COMPLIANCE);
});

describe('KYC self-service', function (): void {
    it('returns the signed-in user\'s own KYC status', function (): void {
        $this->getJson('/api/compliance/kyc/status')->assertUnauthorized();

        $user = User::factory()->create(['kyc_status' => 'pending', 'kyc_level' => 'basic']);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->getJson('/api/compliance/kyc/status')
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonStructure(['status', 'level', 'submitted_at', 'approved_at', 'expires_at', 'needs_kyc', 'documents']);
    });

    it('lets only the owner or compliance staff download a KYC document', function (): void {
        Storage::fake('private');
        $owner = User::factory()->create();
        Storage::disk('private')->put('kyc/' . $owner->uuid . '/passport.pdf', 'PDF-BYTES');
        $document = KycDocument::factory()->create([
            'user_uuid' => $owner->uuid,
            'file_path' => 'kyc/' . $owner->uuid . '/passport.pdf',
            'metadata'  => ['original_name' => 'passport.pdf'],
        ]);
        $path = "/api/compliance/kyc/documents/{$document->id}/download";

        $this->getJson($path)->assertUnauthorized();

        // The streamed download passes through the api middleware stack
        // (metrics, tracing) intact.
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $owned = $this->get($path)->assertOk()->assertDownload('passport.pdf');
        expect($owned->streamedContent())->toBe('PDF-BYTES');

        // Another user gets the same answer as for an unknown document.
        Sanctum::actingAs(cxaUser(), ['read', 'write', 'delete']);
        $this->getJson($path)->assertNotFound();
        $this->getJson('/api/compliance/kyc/documents/00000000-0000-4000-8000-000000000000/download')->assertNotFound();

        Sanctum::actingAs(cxaUser('compliance_officer'), ['read', 'write', 'delete']);
        expect($this->get($path)->assertOk()->streamedContent())->toBe('PDF-BYTES');
    });

    it('keeps the self-service endpoints working for compliance staff on their own record', function (): void {
        Sanctum::actingAs(cxaUser('compliance_officer'), ['read', 'write', 'delete']);

        $this->getJson('/api/compliance/kyc/status')->assertOk()->assertJsonStructure(['status', 'level', 'documents']);
        $this->getJson('/api/compliance/gdpr/consent')->assertOk()->assertJsonStructure(['consents', 'dates']);
        $this->getJson('/api/v2/compliance/kyc/status')->assertOk();
        $this->getJson('/api/v2/compliance/aml/status')->assertOk();
    });

    it('returns an Ondato verification only to its owner', function (): void {
        $owner = User::factory()->create();
        $verification = KycVerification::create([
            'user_id'            => $owner->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_PENDING,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-access-check',
            'target_level'       => 'verified',
            'verification_data'  => [],
        ]);
        $path = "/api/compliance/kyc/ondato/status/{$verification->id}";

        $this->getJson($path)->assertUnauthorized();

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->getJson($path)
            ->assertOk()
            ->assertExactJson(['data' => [
                'verification_id'  => $verification->id,
                'status'           => KycVerification::STATUS_PENDING,
                'completed_at'     => null,
                'failure_reason'   => null,
                'trust_cert_level' => null,
            ]]);

        Sanctum::actingAs(cxaUser(), ['read', 'write', 'delete']);
        $this->getJson($path)->assertNotFound();
    });

    it('scopes v2 KYC uploads to the owner of the verification', function (): void {
        $owner = User::factory()->create();
        $verification = KycVerification::create([
            'user_id'           => $owner->id,
            'type'              => KycVerification::TYPE_IDENTITY,
            'status'            => KycVerification::STATUS_PENDING,
            'provider'          => 'manual',
            'verification_data' => [],
        ]);
        $path = "/api/v2/compliance/kyc/{$verification->id}/selfie";
        Storage::fake((string) config('filesystems.default'));
        $selfie = Illuminate\Http\UploadedFile::fake()->image('selfie.jpg');

        $this->postJson($path, ['selfie' => $selfie])->assertUnauthorized();

        Sanctum::actingAs(cxaUser(), ['read', 'write', 'delete']);
        $this->postJson($path, ['selfie' => $selfie])->assertNotFound();

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        expect($this->postJson($path, ['selfie' => $selfie])->status())->not->toBeIn([401, 403, 404]);
    });
});

describe('GDPR self-service', function (): void {
    it('updates consents for the signed-in user only', function (): void {
        $this->postJson('/api/compliance/gdpr/consent', ['marketing' => true])->assertUnauthorized();

        $caller = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($caller, ['read', 'write', 'delete']);

        $this->postJson('/api/compliance/gdpr/consent', ['marketing' => true, 'user_id' => $other->id])->assertOk();

        expect($caller->fresh()?->marketing_consent_at)->not->toBeNull()
            ->and($other->fresh()?->marketing_consent_at)->toBeNull();

        $this->getJson('/api/compliance/gdpr/consent')->assertOk()->assertJsonPath('consents.marketing', true);
    });

    it('processes a deletion request for the signed-in user only', function (): void {
        $caller = User::factory()->create();
        $other = User::factory()->create();
        $gdpr = Mockery::mock(GdprService::class);
        $gdpr->shouldReceive('canDeleteUserData')
            ->once()
            ->with(Mockery::on(fn (User $user): bool => $user->id === $caller->id))
            ->andReturn(['can_delete' => true, 'reasons' => []]);
        $gdpr->shouldReceive('deleteUserData')
            ->once()
            ->with(Mockery::on(fn (User $user): bool => $user->id === $caller->id), Mockery::type('array'));
        app()->instance(GdprService::class, $gdpr);

        // Bind the double before the first request: the route keeps the controller instance.
        $this->postJson('/api/compliance/gdpr/delete', ['confirm' => true])->assertUnauthorized();

        Sanctum::actingAs($caller, ['read', 'write', 'delete']);
        $this->postJson('/api/compliance/gdpr/delete', ['confirm' => true, 'user_id' => $other->id, 'user_uuid' => $other->uuid])
            ->assertOk();
    });

    it('shows a data export to the requesting user and compliance staff only', function (string $statusPath): void {
        $gdpr = Mockery::mock(GdprService::class);
        $gdpr->shouldReceive('exportUserData')->once()->andReturn(['profile' => ['name' => 'Owner'], 'transactions' => []]);
        app()->instance(GdprService::class, $gdpr);
        Storage::fake('local');
        $this->getJson(sprintf($statusPath, 'any-export'))->assertUnauthorized();

        $owner = User::factory()->create();
        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        // The queue runs synchronously in tests, so the export completes here.
        $exportId = (string) $this->postJson('/api/v1/user/data-export')->assertStatus(202)->json('export_id');
        $path = sprintf($statusPath, $exportId);

        $owned = $this->getJson($path)->assertOk();
        expect(array_keys((array) $owned->json()))->toEqualCanonicalizing(['export_id', 'status', 'sections', 'completed_at', 'download_url'])
            ->and($owned->json('status'))->toBe('completed');

        // Another user gets the same answer as for an unknown export.
        Sanctum::actingAs(cxaUser(), ['read', 'write', 'delete']);
        $this->getJson($path)->assertNotFound()->assertExactJson(['error' => 'Export request not found or expired']);

        Sanctum::actingAs(cxaUser('compliance_officer'), ['read', 'write', 'delete']);
        $this->getJson($path)->assertOk()->assertJsonPath('status', 'completed');
    })->with([
        'mobile path'     => ['/api/v1/user/data-export/%s'],
        'compliance path' => ['/api/compliance/gdpr/export/%s'],
    ]);

    it('lets only the requesting user or compliance staff download an export', function (): void {
        Storage::fake('local');
        $owner = User::factory()->create();
        $exportId = 'access-export-1';
        Storage::disk('local')->put("gdpr-exports/{$exportId}.json.enc", encrypt((string) json_encode(['profile' => ['name' => 'Owner']])));
        Cache::put("gdpr_export:{$exportId}", [
            'user_id'   => $owner->id,
            'status'    => 'completed',
            'sections'  => ['profile'],
            'file_path' => "gdpr-exports/{$exportId}.json.enc",
        ], now()->addHour());
        $url = URL::signedRoute('api.user.data-export.download', ['exportId' => $exportId], now()->addHour());

        $this->getJson($url)->assertUnauthorized();

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/json');

        Sanctum::actingAs(cxaUser(), ['read', 'write', 'delete']);
        $this->getJson($url)->assertNotFound();

        Sanctum::actingAs(cxaUser('compliance_officer'), ['read', 'write', 'delete']);
        $this->get($url)->assertOk();
    });

    it('treats an export entry without an owner as staff-only', function (): void {
        Cache::put('gdpr_export:legacy-export', ['status' => 'processing'], now()->addHour());

        Sanctum::actingAs(cxaUser(), ['read', 'write', 'delete']);
        $this->getJson('/api/v1/user/data-export/legacy-export')->assertNotFound();

        Sanctum::actingAs(cxaUser('compliance_officer'), ['read', 'write', 'delete']);
        $this->getJson('/api/v1/user/data-export/legacy-export')->assertOk()->assertJsonPath('status', 'processing');
    });

    it('records v2 consent for the signed-in user', function (): void {
        $this->postJson('/api/compliance/gdpr/v2/consent', ['purpose' => 'marketing', 'granted' => true])->assertUnauthorized();

        Sanctum::actingAs(cxaUser(), ['read', 'write', 'delete']);
        $this->getJson('/api/compliance/gdpr/v2/consent')->assertOk();
    });
});

describe('public by design', function (): void {
    it('screens an address for any signed-in user', function (): void {
        $screening = Mockery::mock(SanctionsScreeningInterface::class);
        $screening->shouldReceive('screenAddress')->once()->andReturn(['total_matches' => 0, 'lists_checked' => ['OFAC SDN'], 'matches' => []]);
        $screening->shouldReceive('getName')->andReturn('test');
        app()->instance(SanctionsScreeningInterface::class, $screening);

        $this->getJson('/api/v1/compliance/check-address?address=0x1234567890abcdef')->assertUnauthorized();

        Sanctum::actingAs(cxaUser(), ['read', 'write', 'delete']);
        $this->getJson('/api/v1/compliance/check-address?address=0x1234567890abcdef')
            ->assertOk()
            ->assertJsonPath('data.sanctioned', false)
            ->assertJsonPath('data.provider', 'test');
    });
});

describe('GraphQL compliance schema', function (): void {
    it('returns a KYC verification only to its owner', function (): void {
        $owner = User::factory()->create();
        $verification = KycVerification::create([
            'user_id'           => $owner->id,
            'type'              => KycVerification::TYPE_IDENTITY,
            'status'            => KycVerification::STATUS_PENDING,
            'provider'          => 'manual',
            'verification_data' => [],
        ]);
        $query = ['query' => 'query ($id: ID!) { kycVerification(id: $id) { id status } }', 'variables' => ['id' => (string) $verification->id]];

        $anonymous = $this->postJson('/graphql', $query)->json();
        expect($anonymous['errors'][0]['message'] ?? null)->toBe('Unauthenticated.');

        $owned = $this->actingAs($owner, 'sanctum')->postJson('/graphql', $query)->json();
        expect($owned['data']['kycVerification']['id'] ?? null)->toBe((string) $verification->id);

        $other = $this->actingAs(cxaUser(), 'sanctum')->postJson('/graphql', $query)->json();
        expect($other)->not->toHaveKey('errors')
            ->and($other['data'])->toHaveKey('kycVerification')
            ->and($other['data']['kycVerification'])->toBeNull();
    });

    it('lists only the caller\'s own KYC verifications', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$owner, $other] as $user) {
            KycVerification::create([
                'user_id'           => $user->id,
                'type'              => KycVerification::TYPE_IDENTITY,
                'status'            => KycVerification::STATUS_PENDING,
                'provider'          => 'manual',
                'verification_data' => [],
            ]);
        }
        $query = ['query' => '{ kycVerifications(first: 10) { data { id user_id } } }'];

        $json = $this->actingAs($owner, 'sanctum')->postJson('/graphql', $query)->json();
        expect($json)->not->toHaveKey('errors')
            ->and(array_column($json['data']['kycVerifications']['data'], 'user_id'))->toBe([$owner->id]);
    });

    it('refuses the compliance mutations without a signed-in user', function (): void {
        $target = User::factory()->create();
        $mutations = [
            [
                'query'     => 'mutation ($input: SubmitKycDocumentInput!) { submitKycDocument(input: $input) { id } }',
                'variables' => ['input' => ['user_id' => $target->id, 'type' => 'identity', 'document_type' => 'passport', 'document_country' => 'US']],
            ],
            [
                'query'     => 'mutation ($input: TriggerAmlCheckInput!) { triggerAmlCheck(input: $input) { id } }',
                'variables' => ['input' => ['entity_type' => User::class, 'entity_id' => (string) $target->id]],
            ],
        ];

        foreach ($mutations as $body) {
            expect($this->postJson('/graphql', $body)->json('errors.0.message'))->toBe('Unauthenticated.');
        }

        expect(KycVerification::query()->count())->toBe(0)
            ->and(ComplianceAlert::query()->count())->toBe(0);
    });

    it('scopes every compliance query field with an owner or staff builder', function (): void {
        $schema = (string) file_get_contents(base_path('graphql/compliance.graphql'));
        preg_match('/extend type Query[^{\n]*\{(.*?)\n\}/s', $schema, $match);
        $fields = preg_split('/\n(?=    [a-zA-Z]+\()/', trim($match[1] ?? '')) ?: [];

        expect($fields)->toHaveCount(6);
        foreach ($fields as $field) {
            expect($field)->toMatch('/@builder\(method: "App\\\\\\\\GraphQL\\\\\\\\Builders\\\\\\\\(OwnedByUser@userId|StaffOnly@compliance)"\)/')
                ->and($field)->toContain('@guard(with: ["sanctum"])');
        }
    });
});

describe('regulatory reports, transaction monitoring and audit', function (): void {
    it('answer 401 without a token, 403 to customers and 200 to compliance staff', function (string $method, string $path, array $body): void {
        $this->json($method, $path, $body)->assertUnauthorized();

        Sanctum::actingAs(cxaUser(), ['read', 'write', 'delete']);
        $this->json($method, $path, $body)->assertForbidden();

        Sanctum::actingAs(cxaUser('compliance_officer'), ['read', 'write', 'delete']);
        $this->json($method, $path, $body)->assertOk();
    })->with([
        'monitored transactions' => ['GET', '/api/transaction-monitoring', []],
        'monitoring rules'       => ['GET', '/api/transaction-monitoring/rules', []],
        'monitoring thresholds'  => ['GET', '/api/transaction-monitoring/thresholds', []],
        'monitoring patterns'    => ['GET', '/api/transaction-monitoring/patterns', []],
        'audit logs'             => ['GET', '/api/audit/logs', []],
        'audit user activity'    => ['GET', '/api/audit/users/1/activity', []],
        'regulatory report list' => ['GET', '/api/regulatory/reports', []],
    ]);

    it('keeps the monitored transactions and monitoring settings away from customers', function (): void {
        App\Domain\Account\Models\Transaction::factory()->create();

        Sanctum::actingAs(cxaUser(), ['read', 'write', 'delete']);
        $this->getJson('/api/transaction-monitoring')->assertForbidden()->assertJsonMissingPath('data');
        $this->postJson('/api/transaction-monitoring/rules', ['name' => 'r', 'type' => 'amount'])->assertForbidden();
        $this->putJson('/api/transaction-monitoring/thresholds', ['amount' => 1])->assertForbidden();

        Sanctum::actingAs(cxaUser('compliance_officer'), ['read', 'write', 'delete']);
        expect($this->getJson('/api/transaction-monitoring')->assertOk()->json('meta.total'))->toBeGreaterThanOrEqual(1);
    });

    it('keeps stored regulatory reports away from customers', function (): void {
        Storage::fake();
        Storage::put('regulatory/kyc/report_2026_01_01.json', (string) json_encode([
            'report_type'           => 'KYC Compliance Report',
            'pending_verifications' => [['email' => 'pending@example.test']],
        ]));

        Sanctum::actingAs(cxaUser(), ['read', 'write', 'delete']);
        foreach (['/api/regulatory/reports', '/api/regulatory/reports/report_2026_01_01.json', '/api/regulatory/reports/report_2026_01_01.json/download'] as $path) {
            $this->getJson($path)->assertForbidden()->assertDontSee('report_2026_01_01')->assertDontSee('pending@example.test');
        }

        Sanctum::actingAs(cxaUser('compliance_officer'), ['read', 'write', 'delete']);
        expect($this->getJson('/api/regulatory/reports')->assertOk()->json())->toBeArray();
    });
});

describe('streamed and file downloads through the api middleware', function (): void {
    it('records metrics and a trace for responses without a buffered body', function (): void {
        $file = tempnam(sys_get_temp_dir(), 'cxa');
        file_put_contents((string) $file, 'FILE-BYTES');
        $responses = [
            'streamed' => new StreamedResponse(function (): void {
                echo 'STREAM-BYTES';
            }, 200),
            'streamed 404' => new StreamedResponse(null, 404),
            'binary file'  => new BinaryFileResponse((string) $file),
        ];

        foreach ($responses as $label => $response) {
            $request = Request::create('/api/compliance/kyc/documents/x/download', 'GET');

            expect(app(MetricsMiddleware::class)->handle($request, fn () => $response))->toBe($response, "metrics: {$label}")
                ->and(app(TracingMiddleware::class)->handle($request, fn () => $response))->toBe($response, "tracing: {$label}");
        }

        expect(Cache::get('metrics:errors:client'))->toBe(1);
        @unlink((string) $file);
    });
});

describe('Ondato webhooks', function (): void {
    it('rejects unsigned webhooks in production even in sandbox mode', function (): void {
        Queue::fake();
        config(['services.ondato.sandbox' => true, 'services.ondato.webhook_secret' => '']);
        $payload = ['id' => 'idv-forged', 'status' => 'PROCESSED'];

        $this->app->detectEnvironment(fn (): string => 'production');
        $this->postJson('/api/webhooks/ondato/identity-verification', $payload)
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'Invalid signature']);
        Queue::assertNotPushed(ProcessOndatoWebhook::class);

        // Outside production the sandbox passthrough still applies.
        $this->app->detectEnvironment(fn (): string => 'testing');
        $this->postJson('/api/webhooks/ondato/identity-verification', $payload)->assertOk();
        Queue::assertPushed(ProcessOndatoWebhook::class);
    });

    it('accepts a correctly signed webhook in production', function (): void {
        Queue::fake();
        config(['services.ondato.sandbox' => false, 'services.ondato.webhook_secret' => 'ondato-test-secret']);
        $body = (string) json_encode(['id' => 'idv-signed', 'status' => 'PROCESSED']);

        $this->app->detectEnvironment(fn (): string => 'production');
        $this->call('POST', '/api/webhooks/ondato/identity-verification', [], [], [], [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ACCEPT'             => 'application/json',
            'HTTP_X_ONDATO_SIGNATURE' => hash_hmac('sha256', $body, 'ondato-test-secret'),
        ], $body)->assertOk();
        Queue::assertPushed(ProcessOndatoWebhook::class);

        $this->call('POST', '/api/webhooks/ondato/identity-verification', [], [], [], [
            'CONTENT_TYPE'            => 'application/json',
            'HTTP_ACCEPT'             => 'application/json',
            'HTTP_X_ONDATO_SIGNATURE' => 'forged',
        ], $body)->assertUnauthorized();
    });
});
