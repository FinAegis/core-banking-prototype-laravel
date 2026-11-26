<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Batch;

use App\Domain\Batch\Models\BatchJob;
use App\Domain\Batch\Services\BatchProcessingService;
use App\Models\User;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Exceptions\RateLimitException;

class CreateBatchJobMutation
{
    public function __construct(
        private readonly BatchProcessingService $batchProcessingService,
    ) {
    }

    /**
     * Each item is a JSON object as a string, with the fields of a transfer:
     * {"from_account": "...", "to_account": "...", "amount": "12.50",
     * "currency": "USD", "description": "..."}. The service checks every item
     * as a single transfer is checked, including that the user owns the
     * source account.
     *
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): BatchJob
    {
        // Batches are a demo feature (BATCH_PROCESSING_ENABLED).
        if (! config('ledger.batch_processing_enabled')) {
            throw new Error('This operation is not available.');
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        try {
            return $this->batchProcessingService->createBatchJob(
                userUuid: (string) $user->uuid,
                name: (string) $args['name'],
                type: (string) $args['type'],
                items: $this->decodeItems($args['items'] ?? []),
            );
        } catch (ThrottleRequestsException) {
            // Every item counts against the same transfer limits as
            // POST /api/transfers (transaction.rate_limit:transfer).
            throw new RateLimitException('Mutation.createBatchJob');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeItems(mixed $items): array
    {
        $decoded = [];
        $errors = [];

        foreach (is_array($items) ? array_values($items) : [] as $index => $item) {
            $value = is_string($item) ? json_decode($item, true) : null;

            if (! is_array($value) || array_is_list($value)) {
                $errors["items.{$index}"][] = 'Each item must be a JSON object with from_account, to_account, amount and currency.';

                continue;
            }

            /** @var array<string, mixed> $value */
            $decoded[] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $decoded;
    }
}
