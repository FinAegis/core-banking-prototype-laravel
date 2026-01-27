<?php

declare(strict_types=1);

namespace App\Exceptions;

use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;

class TenantCouldNotBeIdentifiedByTeamException extends TenantCouldNotBeIdentifiedException
{
    public function __construct(
        private readonly ?int $teamId = null
    ) {
        parent::__construct(
            $teamId
                ? "Tenant could not be identified for team ID: {$teamId}"
                : 'Tenant could not be identified: No team context available'
        );
    }

    public function getTeamId(): ?int
    {
        return $this->teamId;
    }
}
