<?php

declare(strict_types=1);

use App\Domain\Newsletter\Models\Campaign;
use App\Domain\Newsletter\Models\Subscriber;

// subscribers has a status column (pending, active, unsubscribed, bounced)
// and no is_active column. Campaign targeting filtered on is_active: MySQL
// rejects that, and SQLite reads "is_active" as a string literal, so a
// campaign reached nobody. Only confirmed (active) subscribers may receive it.

it('counts only active subscribers as campaign targets', function (): void {
    Subscriber::factory()->create(['email' => 'active@example.com', 'status' => Subscriber::STATUS_ACTIVE]);
    Subscriber::factory()->create(['email' => 'pending@example.com', 'status' => Subscriber::STATUS_PENDING]);
    Subscriber::factory()->create(['email' => 'gone@example.com', 'status' => Subscriber::STATUS_UNSUBSCRIBED]);

    // newsletter_campaigns has no migration yet, so the campaign stays unsaved.
    $campaign = (new Campaign())->forceFill(['name' => 'Recipients', 'segment' => null]);

    expect($campaign->getTargetSubscriberCount())->toBe(1);
});
