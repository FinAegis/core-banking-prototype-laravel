<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins;

use App\Infrastructure\Plugins\Contracts\PluginHookInterface;
use App\Infrastructure\Plugins\PluginHookManager;
use Tests\TestCase;

class PluginHookSingletonTest extends TestCase
{
    public function test_hook_manager_is_singleton(): void
    {
        $instance1 = app()->make(PluginHookManager::class);
        $instance2 = app()->make(PluginHookManager::class);

        $this->assertSame($instance1, $instance2);
    }

    public function test_hooks_registered_on_one_instance_are_visible_on_another(): void
    {
        $hookManager = app()->make(PluginHookManager::class);

        $this->assertFalse($hookManager->hasListeners('account.created'));

        $listener = new class implements PluginHookInterface {
            public bool $handled = false;

            public function getHookName(): string
            {
                return 'account.created';
            }

            public function getPriority(): int
            {
                return 0;
            }

            public function handle(array $payload): void
            {
                $this->handled = true;
            }
        };

        $hookManager->register($listener);

        // Resolve a second reference — should be the same singleton
        $hookManager2 = app()->make(PluginHookManager::class);

        $this->assertTrue($hookManager2->hasListeners('account.created'));
        $this->assertSame(1, $hookManager2->getListenerCount('account.created'));

        $hookManager2->dispatch('account.created', ['test' => true]);
        $this->assertTrue($listener->handled);
    }
}
