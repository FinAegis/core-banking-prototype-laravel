<?php

declare(strict_types=1);

use App\Models\InvestorInquiry;
use App\Notifications\InvestorInquirySubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

/**
 * Build a fresh, valid form submission. Tests can override individual fields
 * by passing an associative array.
 *
 * Email uses gmail.com so the `email:rfc,dns` MX-record check passes — the
 * default Laravel dns validator hits real DNS even in tests.
 *
 * @param  array<string, string|int>  $overrides
 * @return array<string, string|int>
 */
function inquiryPayload(array $overrides = []): array
{
    return array_replace([
        'name'             => 'Jane Investor',
        'email'            => 'jane@gmail.com',
        'linkedin_url'     => 'https://linkedin.com/in/jane-investor',
        'investing_as'     => 'angel',
        'path_of_interest' => 'licensed',
        'check_size_range' => '25k_100k',
        'questions'        => 'How is the licence cycle progressing?',
        'gdpr_consent'     => '1',
    ], $overrides);
}

beforeEach(function (): void {
    // Clear the throttle counters set by other tests in this file.
    Cache::flush();
    RateLimiter::clear('throttle');
});

it('shows the invest page anonymously', function (): void {
    $response = $this->get(route('invest.show'));

    $response->assertOk()
        ->assertSeeText('FinAegis.')
        ->assertSeeText('Two ways to invest.')
        ->assertSeeText('Side by side.')
        ->assertSee('content="noindex,nofollow"', false);
});

it('requires GDPR consent', function (): void {
    Notification::fake();

    $response = $this->from(route('invest.show'))
        ->post(route('invest.submit'), inquiryPayload(['gdpr_consent' => '']));

    $response->assertSessionHasErrors('gdpr_consent');
    expect(InvestorInquiry::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('rejects invalid LinkedIn URLs', function (): void {
    Notification::fake();

    $response = $this->from(route('invest.show'))
        ->post(route('invest.submit'), inquiryPayload(['linkedin_url' => 'https://example.com/profile']));

    $response->assertSessionHasErrors('linkedin_url');
    expect(InvestorInquiry::query()->count())->toBe(0);
});

it('accepts a valid LinkedIn URL', function (): void {
    Notification::fake();

    $response = $this->post(route('invest.submit'), inquiryPayload([
        'linkedin_url' => 'https://www.linkedin.com/in/founder-handle',
    ]));

    $response->assertRedirect(route('invest.thanks'));
    expect(InvestorInquiry::query()->count())->toBe(1);
});

it('stores the submission and queues the notification', function (): void {
    Notification::fake();

    $response = $this->post(route('invest.submit'), inquiryPayload());

    $response->assertRedirect(route('invest.thanks'));

    $inquiry = InvestorInquiry::query()->firstOrFail();
    expect($inquiry->name)->toBe('Jane Investor')
        ->and($inquiry->email)->toBe('jane@gmail.com')
        ->and($inquiry->path_of_interest)->toBe('licensed')
        ->and($inquiry->check_size_range)->toBe('25k_100k')
        ->and($inquiry->investing_as)->toBe('angel');

    Notification::assertSentOnDemand(
        InvestorInquirySubmitted::class,
        function (InvestorInquirySubmitted $notification, array $channels, object $notifiable) {
            // The notifiable for an on-demand mail notification carries the
            // routes; assert the founder address resolved correctly.
            $routes = (array) $notifiable->routes;

            return ($routes['mail'] ?? null) === config('investor.notification_email');
        },
    );
});

it('silently swallows honeypot submissions', function (): void {
    Notification::fake();

    $response = $this->post(route('invest.submit'), inquiryPayload(['website' => 'http://spam.example.com']));

    $response->assertRedirect(route('invest.thanks'));
    expect(InvestorInquiry::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('hashes the IP rather than storing it raw', function (): void {
    Notification::fake();

    $this->post(route('invest.submit'), inquiryPayload());

    $inquiry = InvestorInquiry::query()->firstOrFail();
    expect(strlen($inquiry->ip_hash))->toBe(64)
        ->and($inquiry->ip_hash)->toMatch('/^[a-f0-9]{64}$/');

    // sha256 of an IP shouldn't equal the IP itself.
    expect($inquiry->ip_hash)->not->toBe('127.0.0.1');
});

it('rate-limits beyond 5 submissions in 24h', function (): void {
    Notification::fake();

    for ($i = 0; $i < 5; $i++) {
        $response = $this->post(route('invest.submit'), inquiryPayload([
            'email' => "investor{$i}@gmail.com",
        ]));
        $response->assertRedirect(route('invest.thanks'));
    }

    $sixth = $this->post(route('invest.submit'), inquiryPayload([
        'email' => 'investor5@gmail.com',
    ]));
    $sixth->assertStatus(429);

    expect(InvestorInquiry::query()->count())->toBe(5);
});

it('returns 403 on the data-room route by default', function (): void {
    $response = $this->get(route('invest.data-room'));

    $response->assertStatus(403)
        ->assertSeeText('You need to request access first.')
        ->assertSeeText('Request access');
});
