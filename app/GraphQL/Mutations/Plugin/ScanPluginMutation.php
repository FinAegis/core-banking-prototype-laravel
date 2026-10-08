<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Plugin;

use App\Domain\Shared\Models\Plugin;
use App\Infrastructure\Plugins\PluginSecurityScanner;
use App\Support\StaffRoles;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class ScanPluginMutation
{
    public function __construct(
        private readonly PluginSecurityScanner $securityScanner,
    ) {
    }

    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     * @return array{plugin: string, safe: bool, issue_count: int}
     */
    public function __invoke($_, array $args): array
    {
        // Runtime plugin management (ADMIN_RUNTIME_MANAGEMENT_ENABLED).
        if (! config('modules.runtime_management_enabled')) {
            throw new Error('This operation is not available.');
        }

        // Plugin management is for administrators only.
        if (! StaffRoles::userHasAny(Auth::user(), StaffRoles::ADMINS)) {
            throw new AuthorizationException('You are not authorized to manage plugins.');
        }

        /** @var Plugin $plugin */
        $plugin = Plugin::findOrFail($args['id']);
        $result = $this->securityScanner->scan($plugin->path ?? '');

        return [
            'plugin'      => $plugin->getFullName(),
            'safe'        => $result['safe'],
            'issue_count' => count($result['issues']),
        ];
    }
}
