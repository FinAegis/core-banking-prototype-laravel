<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A card funding account has deposit addresses, so the user can add money.
 * Broadcast as `cards.funding.ready` on the user's private channel.
 */
final class CardFundingAccountReady implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<string>  $networks
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $provider,
        public readonly array $networks,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'cards.funding.ready';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['provider' => $this->provider, 'networks' => $this->networks];
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('websocket.enabled', true);
    }
}
