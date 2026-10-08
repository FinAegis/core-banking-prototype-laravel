<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Plugin;

use App\Domain\Shared\Models\Plugin;
use App\Infrastructure\Plugins\PluginManager;
use App\Support\StaffRoles;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class DisablePluginMutation
{
    public function __construct(
        private readonly PluginManager $pluginManager,
    ) {
    }

    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     * @return array{success: bool, message: string}
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

        return $this->pluginManager->disable($plugin->vendor, $plugin->name);
    }
}
