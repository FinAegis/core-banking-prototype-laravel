<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\X402;

use App\Domain\X402\Models\X402MonetizedEndpoint;

class UpdateX402MonetizedEndpointMutation
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): X402MonetizedEndpoint
    {
        $endpoint = X402MonetizedEndpoint::findOrFail($args['id']);

        $updateData = [];
        foreach (['price', 'network', 'description', 'is_active'] as $field) {
            if (array_key_exists($field, $args)) {
                $updateData[$field] = $args[$field];
            }
        }

        $endpoint->update($updateData);

        return $endpoint->fresh();
    }
}
