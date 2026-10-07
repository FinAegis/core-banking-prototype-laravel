<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Plugin;

use App\Domain\Shared\Models\Plugin;
use App\Infrastructure\Plugins\PluginManager;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class EnablePluginMutation
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
        // Plugin management is for administrators only.
        if (! StaffRoles::userHasAny(Auth::user(), StaffRoles::ADMINS)) {
            throw new AuthorizationException('You are not authorized to manage plugins.');
        }

        /** @var Plugin $plugin */
        $plugin = Plugin::findOrFail($args['id']);

        return $this->pluginManager->enable($plugin->vendor, $plugin->name);
    }
}
