<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Cardholder;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    config([
        'cardissuance.issuers.lepo.base_url'    => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'     => 'test-key',
        'cardissuance.providers.kyc_return_url' => 'zelta://cards/kyc-done',
    ]);
    Http::preventStrayRequests();
    // The pages are throttled per address (20 a minute). CI keeps that count
    // in Redis across the tests of this file, so the limit is switched off
    // here; it is not what these tests check.
    $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);
});

function hostedCardholder(string $status = 'pending', string $provider = 'lepo'): Cardholder
{
    $cardholder = new Cardholder();
    $cardholder->forceFill([
        'user_id'           => User::factory()->create()->id, 'provider' => $provider, 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'kyc_status'        => $status, 'issuer_cardholder_id' => '12345',
        'verification_data' => ['questionnaire' => ['occupation' => '15-1252', 'annualSalary' => '30001-50000']],
    ]);
    $cardholder->save();

    return $cardholder;
}

function hostedLink(Cardholder $cardholder, int $minutes = 30): string
{
    return URL::temporarySignedRoute('cards.kyc.hosted', now()->addMinutes($minutes), ['cardholder' => $cardholder->id], absolute: false);
}

it('opens the SumSub identity check with a fresh Lepo SDK token', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['success' => true, 'token' => 'web-token-123', 'status' => 'init'])]);
    $cardholder = hostedCardholder();

    $response = $this->get(hostedLink($cardholder))
        ->assertOk()
        ->assertSee('https://static.sumsub.com/idensic/static/sns-websdk-builder.js', false)
        ->assertSee('"web-token-123"', false)
        ->assertSee('zelta:\/\/cards\/kyc-done', false)
        ->assertSee('Identity check');

    expect((string) $response->headers->get('Content-Security-Policy'))
        ->toContain('script-src')->toContain('https://static.sumsub.com')
        ->toContain('frame-src https://*.sumsub.com')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and((string) $response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
        ->and((string) $response->headers->get('Permissions-Policy'))->toContain('camera=*');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/users/12345/kyc/sumsub-access-token')
        && $request['occupation'] === '15-1252' && $request['annualSalary'] === '30001-50000');
});

it('records that the identity check was sent from the page', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['success' => true, 'token' => 'web-token'])]);
    $cardholder = hostedCardholder();

    $this->get(hostedLink($cardholder))->assertOk()->assertSee('X-CSRF-TOKEN', false);
    $submitted = URL::temporarySignedRoute('cards.kyc.hosted.submitted', now()->addMinutes(60), ['cardholder' => $cardholder->id], absolute: false);

    $this->postJson($submitted)->assertNoContent();
    $this->postJson(route('cards.kyc.hosted.submitted', ['cardholder' => $cardholder->id]))->assertForbidden();

    expect($cardholder->fresh()?->verification_data['kyc_submitted_at'] ?? null)->toBeString()
        ->and($cardholder->fresh()?->kyc_status)->toBe('pending');
});

it('gives the page a signed link to renew the token', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['success' => true, 'token' => 'renewed-token'])]);
    $cardholder = hostedCardholder();

    $refresh = URL::temporarySignedRoute('cards.kyc.hosted.token', now()->addMinutes(60), ['cardholder' => $cardholder->id], absolute: false);

    $this->getJson($refresh)->assertOk()->assertExactJson(['token' => 'renewed-token']);
    $this->getJson(route('cards.kyc.hosted.token', ['cardholder' => $cardholder->id]))->assertForbidden();
});

it('accepts the link the API hands out whatever host or scheme reaches the page', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['success' => true, 'token' => 'web-token'])]);
    $cardholder = hostedCardholder();
    $url = (string) app(App\Domain\CardIssuance\Providers\Lepo\LepoCardOnboarding::class)->startKyc($cardholder)->url;

    $path = (string) parse_url($url, PHP_URL_PATH) . '?' . (string) parse_url($url, PHP_URL_QUERY);
    expect($url)->toStartWith('http')
        ->and($this->get('https://zelta.app' . $path)->status())->toBe(200)
        ->and($this->get('http://proxy.internal' . $path)->status())->toBe(200);
});

it('refuses a link that is expired or not signed', function (): void {
    Http::fake();
    $cardholder = hostedCardholder();

    $this->get(hostedLink($cardholder, -1))->assertForbidden()->assertSee('This link has expired');
    $this->get(route('cards.kyc.hosted', ['cardholder' => $cardholder->id]))->assertForbidden()->assertSee('This link has expired');

    Http::assertNothingSent();
});

it('tells a cardholder whose check is already submitted to go back to the app', function (): void {
    Http::fake();
    $cardholder = hostedCardholder('in_review');

    $this->get(hostedLink($cardholder))->assertOk()->assertSee('already been sent')->assertDontSee('sns-websdk-builder.js', false);
    Http::assertNothingSent();
});

it('does not open the page for another provider', function (): void {
    Http::fake();
    $cardholder = hostedCardholder('pending', 'fincard');

    $this->get(hostedLink($cardholder))->assertNotFound();
    Http::assertNothingSent();
});

it('asks to try again later when Lepo cannot start the check', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['message' => 'down'], 500)]);
    $cardholder = hostedCardholder();

    $this->get(hostedLink($cardholder))->assertStatus(502)->assertSee('could not start right now');
});

it('never hands the web SDK the hosted verification link from kyc-url', function (): void {
    // Lepo: the accessToken from sumsub/kyc-url is a link to their hosted
    // page, not an SDK token. The SDK token comes from sumsub-access-token.
    Http::fake([
        'lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['success' => true, 'token' => 'sdk-token']),
        'lepo.test/api/v2/users/12345/sumsub/kyc-url'          => Http::response(['success' => true, 'accessToken' => 'https://verify.example/link']),
    ]);
    $cardholder = hostedCardholder();

    $this->get(hostedLink($cardholder))->assertOk()->assertSee('"sdk-token"', false)->assertDontSee('verify.example');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/sumsub/kyc-url'));
});

it('shows the sent page and syncs the status when Lepo says the check is already approved', function (): void {
    Http::fake([
        'lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['message' => 'KYC already approved', 'code' => 'KYC_ALREADY_APPROVED'], 400),
        'lepo.test/api/v2/users/12345/kyc/detailed-status'     => Http::response(['cardIssuerKycApproved' => true, 'interlace' => ['status' => 'approved', 'approved' => true]]),
    ]);
    $cardholder = hostedCardholder();

    $this->get(hostedLink($cardholder))->assertOk()->assertSee('already been sent');

    expect($cardholder->fresh()?->kyc_status)->toBe('verified');
});

it('tells the page to stop renewing the token once the check is already approved', function (): void {
    Http::fake([
        'lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['code' => 'KYC_ALREADY_APPROVED'], 400),
        'lepo.test/api/v2/users/12345/kyc/detailed-status'     => Http::response(['lepoFinanceKycApproved' => true]),
    ]);
    $cardholder = hostedCardholder();
    $refresh = URL::temporarySignedRoute('cards.kyc.hosted.token', now()->addMinutes(60), ['cardholder' => $cardholder->id], absolute: false);

    $this->getJson($refresh)->assertStatus(409)->assertExactJson(['error' => 'submitted']);

    expect($cardholder->fresh()?->kyc_status)->toBe('in_review');
});
