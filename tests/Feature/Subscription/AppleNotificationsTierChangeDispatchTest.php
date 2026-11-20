<?php

/**
 * Tests that AppleNotificationsWebhookController dispatches
 * SubscriptionTierChanged after every IapSubscription status mutation —
 * the missing symmetric path to the Stripe webhook tier-change dispatch
 * (which landed in PR #1095). Without these, IAP-driven Pro upgrades /
 * downgrades wouldn't auto-trigger the Bridge dev-fee PATCH.
 *
 * We invoke the private status-change paths through the public webhook
 * handler indirectly is heavyweight (requires full Apple JWS payload). For
 * test simplicity we exercise dispatchTierChange via reflection on
 * representative state-change paths.
 */

declare(strict_types=1);

use App\Domain\Subscription\Events\SubscriptionTierChanged;
use App\Domain\Subscription\Models\IapSubscription;
use App\Domain\Subscription\Webhooks\AppleNotificationsWebhookController;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('exposes a private dispatchTierChange that fires SubscriptionTierChanged for a user', function () {
    Event::fake([SubscriptionTierChanged::class]);

    $user = User::factory()->create();
    $sub = new IapSubscription();
    $sub->user_id = $user->id;

    // Invoke the private helper via reflection — the same code path that
    // markStatus + onRefund + onSubscribed.RESUBSCRIBE + onDidRenew +
    // onRefundDeclined all call inline.
    $controller = app(AppleNotificationsWebhookController::class);
    $method = (new ReflectionClass($controller))->getMethod('dispatchTierChange');
    $method->invoke($controller, $sub, 'apple_iap.test.source');

    Event::assertDispatched(
        SubscriptionTierChanged::class,
        fn (SubscriptionTierChanged $e) => $e->userId === $user->id
            && $e->source === 'apple_iap.test.source',
    );
});

it('dispatches with the source string verbatim so listeners can see which Apple notification triggered it', function () {
    Event::fake([SubscriptionTierChanged::class]);

    $user = User::factory()->create();
    $sub = new IapSubscription();
    $sub->user_id = $user->id;

    $controller = app(AppleNotificationsWebhookController::class);
    $method = (new ReflectionClass($controller))->getMethod('dispatchTierChange');
    $method->invoke($controller, $sub, 'apple_iap.refund');

    Event::assertDispatched(
        SubscriptionTierChanged::class,
        fn (SubscriptionTierChanged $e) => $e->source === 'apple_iap.refund',
    );
});
