<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\IapSubscription;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config([
        'subscription.iap.google.package_name'         => 'app.zelta',
        'subscription.iap.google.webhook_audience'     => '', // bypass JWT in local/testing
        'subscription.iap.google.service_account_path' => null,
        'subscription.iap.google.service_account_json' => null,
        'subscription.iap.receipt_pepper'              => 'test-pepper',
    ]);
});

/**
 * Build a Google Pub/Sub push body wrapping an RTDN payload.
 *
 * @param array<string, mixed> $rtdn
 *
 * @return array<string, mixed>
 */
function googlePubSubMessage(array $rtdn, ?string $messageId = null): array
{
    return [
        'message' => [
            'data'      => base64_encode((string) json_encode($rtdn)),
            'messageId' => $messageId ?? (string) Str::uuid(),
        ],
        'subscription' => 'projects/test/subscriptions/zelta-rtdn',
    ];
}

it('dedups Google Play RTDN on messageId', function () {
    $messageId = (string) Str::uuid();

    $rtdn = [
        'subscriptionNotification' => [
            'notificationType' => 2, // SUBSCRIPTION_RENEWED
            'purchaseToken'    => 'pt-dedup',
            'subscriptionId'   => 'zelta_pro_monthly',
        ],
    ];

    $body = googlePubSubMessage($rtdn, $messageId);

    $this->postJson('/api/webhooks/google/play', $body)->assertStatus(200);
    $this->postJson('/api/webhooks/google/play', $body)->assertStatus(200);

    expect(ProcessedWebhookEvent::query()->where('provider', 'google')->count())->toBe(1);
});

it('processes SUBSCRIPTION_RENEWED and writes a renewal outbox row', function () {
    $user = User::factory()->create();
    // Pre-create a sub with the synthetic resource id the bypass verifier will compute.
    $purchaseToken = 'pt-renewal';
    $syntheticId = 'synthetic-' . hash('sha256', $purchaseToken);

    IapSubscription::query()->create([
        'id'                            => (string) Str::uuid(),
        'user_id'                       => $user->id,
        'store'                         => 'google',
        'tier'                          => 'pro',
        'status'                        => 'active',
        'play_subscription_resource_id' => $syntheticId,
        'google_purchase_token_hash'    => hash_hmac('sha256', $purchaseToken, 'test-pepper'),
        'current_period_ends_at'        => now()->subDay(),
        'cancel_at_period_end'          => false,
    ]);

    $rtdn = [
        'subscriptionNotification' => [
            'notificationType' => 2, // SUBSCRIPTION_RENEWED
            'purchaseToken'    => $purchaseToken,
            'subscriptionId'   => 'zelta_pro_monthly',
        ],
    ];

    $this->postJson('/api/webhooks/google/play', googlePubSubMessage($rtdn))->assertStatus(200);

    $outbox = RevenueOutboxEvent::query()
        ->where('source_type', 'google_play')
        ->where('event_kind', 'iap_subscription_renewal')
        ->first();
    expect($outbox)->not()->toBeNull();
});

it('processes SUBSCRIPTION_CANCELED → cancel_at_period_end=true', function () {
    $user = User::factory()->create();
    $purchaseToken = 'pt-cancel';
    $syntheticId = 'synthetic-' . hash('sha256', $purchaseToken);

    $sub = IapSubscription::query()->create([
        'id'                            => (string) Str::uuid(),
        'user_id'                       => $user->id,
        'store'                         => 'google',
        'tier'                          => 'pro',
        'status'                        => 'active',
        'play_subscription_resource_id' => $syntheticId,
        'current_period_ends_at'        => now()->addMonth(),
        'cancel_at_period_end'          => false,
    ]);

    $rtdn = [
        'subscriptionNotification' => [
            'notificationType' => 3, // SUBSCRIPTION_CANCELED
            'purchaseToken'    => $purchaseToken,
            'subscriptionId'   => 'zelta_pro_monthly',
        ],
    ];

    $this->postJson('/api/webhooks/google/play', googlePubSubMessage($rtdn))->assertStatus(200);

    $sub->refresh();
    expect($sub->cancel_at_period_end)->toBeTrue();
});

it('returns 200 on malformed Pub/Sub envelope (no 5xx to Google)', function () {
    $response = $this->postJson('/api/webhooks/google/play', [
        // missing message.data + message.messageId
    ]);

    $response->assertStatus(200);
});
