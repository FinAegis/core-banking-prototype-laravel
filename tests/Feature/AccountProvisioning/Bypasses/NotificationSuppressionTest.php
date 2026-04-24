<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning\Bypasses;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;
use Illuminate\Notifications\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('suppresses notifications for users with suppress_notifications flag', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                => $user->id,
        'is_review_account'      => true,
        'suppress_notifications' => true,
    ]);

    $user->notify(new NotificationSuppressionTestNotification());

    // Database channel is the delivery target — listener should cancel it.
    expect($user->notifications()->count())->toBe(0);
});

it('delivers notifications for regular users without the flag', function () {
    $user = User::factory()->create();

    $user->notify(new NotificationSuppressionTestNotification());

    expect($user->notifications()->count())->toBe(1);
});

it('delivers notifications when the flag is disabled', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                => $user->id,
        'is_review_account'      => true,
        'suppress_notifications' => true,
        'disabled_at'            => now(),
    ]);

    $user->notify(new NotificationSuppressionTestNotification());

    expect($user->notifications()->count())->toBe(1);
});

class NotificationSuppressionTestNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['message' => 'test'];
    }
}
