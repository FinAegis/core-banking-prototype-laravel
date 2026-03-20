<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Services;

use App\Domain\VisaCli\Contracts\VisaCliClientInterface;
use App\Domain\VisaCli\DataObjects\VisaCliCard;
use App\Domain\VisaCli\Enums\VisaCliCardStatus;
use App\Domain\VisaCli\Events\VisaCliCardEnrolled;
use App\Domain\VisaCli\Events\VisaCliCardRemoved;
use App\Domain\VisaCli\Exceptions\VisaCliEnrollmentException;
use App\Domain\VisaCli\Models\VisaCliEnrolledCard;
use Illuminate\Support\Facades\Log;

/**
 * Card enrollment bridge between Visa CLI and the FinAegis card system.
 */
class VisaCliCardEnrollmentService
{
    public function __construct(
        private readonly VisaCliClientInterface $client,
    ) {
    }

    /**
     * Enroll a card for a user.
     */
    public function enrollCard(string $userId): VisaCliEnrolledCard
    {
        if (! config('visacli.enabled', false)) {
            throw new VisaCliEnrollmentException('Visa CLI integration is not enabled.');
        }

        $card = $this->client->enrollCard($userId);

        $enrolledCard = VisaCliEnrolledCard::create([
            'user_id'         => $userId,
            'card_identifier' => $card->cardIdentifier,
            'last4'           => $card->last4,
            'network'         => $card->network,
            'status'          => VisaCliCardStatus::ENROLLED,
            'github_username' => $card->githubUsername,
            'metadata'        => $card->metadata,
        ]);

        event(new VisaCliCardEnrolled(
            userId: $userId,
            cardIdentifier: $card->cardIdentifier,
            last4: $card->last4,
            network: $card->network,
            metadata: $card->metadata,
        ));

        Log::info('Visa CLI card enrolled', [
            'user_id'         => $userId,
            'card_identifier' => $card->cardIdentifier,
            'last4'           => $card->last4,
        ]);

        return $enrolledCard;
    }

    /**
     * Sync cards from Visa CLI for a user.
     *
     * @return array<VisaCliEnrolledCard>
     */
    public function syncCards(string $userId): array
    {
        $remoteCards = $this->client->listCards($userId);
        $synced = [];

        foreach ($remoteCards as $card) {
            $existing = VisaCliEnrolledCard::where('card_identifier', $card->cardIdentifier)
                ->where('user_id', $userId)
                ->first();

            if ($existing !== null) {
                $existing->update([
                    'status'          => $card->status,
                    'github_username' => $card->githubUsername,
                    'metadata'        => $card->metadata,
                ]);
                $synced[] = $existing;
            } else {
                $synced[] = VisaCliEnrolledCard::create([
                    'user_id'         => $userId,
                    'card_identifier' => $card->cardIdentifier,
                    'last4'           => $card->last4,
                    'network'         => $card->network,
                    'status'          => $card->status,
                    'github_username' => $card->githubUsername,
                    'metadata'        => $card->metadata,
                ]);
            }
        }

        return $synced;
    }

    /**
     * Get enrolled cards for a user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, VisaCliEnrolledCard>
     */
    public function getEnrolledCards(string $userId): \Illuminate\Database\Eloquent\Collection
    {
        return VisaCliEnrolledCard::where('user_id', $userId)
            ->whereIn('status', [VisaCliCardStatus::ENROLLED, VisaCliCardStatus::ACTIVE])
            ->get();
    }

    /**
     * Remove an enrolled card.
     */
    public function removeCard(string $userId, string $cardId): bool
    {
        $card = VisaCliEnrolledCard::where('user_id', $userId)
            ->where('id', $cardId)
            ->first();

        if ($card === null) {
            return false;
        }

        $card->update(['status' => VisaCliCardStatus::REMOVED]);
        $card->delete();

        event(new VisaCliCardRemoved(
            userId: $userId,
            cardIdentifier: $card->card_identifier,
        ));

        Log::info('Visa CLI card removed', [
            'user_id'         => $userId,
            'card_identifier' => $card->card_identifier,
        ]);

        return true;
    }

    /**
     * Convert enrolled card model to DTO.
     */
    public function toCard(VisaCliEnrolledCard $model): VisaCliCard
    {
        return new VisaCliCard(
            cardIdentifier: $model->card_identifier,
            last4: $model->last4,
            network: $model->network,
            status: $model->status instanceof VisaCliCardStatus ? $model->status : VisaCliCardStatus::from($model->status),
            githubUsername: $model->github_username,
            metadata: $model->metadata ?? [],
        );
    }
}
