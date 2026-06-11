<?php

declare(strict_types=1);

namespace ZeltaCli\Tests\Unit\Commands;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use ZeltaCli\Commands\Ramp\KycLinkCommand;
use ZeltaCli\Commands\Ramp\StatusCommand as RampStatusCommand;
use ZeltaCli\Commands\Subscription\StatusCommand as SubscriptionStatusCommand;

/**
 * ramp:status / ramp:kyc-link / subscription:status.
 *
 * Commands construct ApiClient + AuthManager inline, so the network path
 * is not unit-testable here — these tests pin the command metadata
 * (name, --json flag) and the unauthenticated short-circuit (exit code 2,
 * reached before any HTTP call) by pointing HOME at an empty temp dir.
 */
class ProductSurfaceCommandsTest extends TestCase
{
    private string $homeBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->homeBackup = $_SERVER['HOME'];
        $_SERVER['HOME'] = sys_get_temp_dir() . '/zelta-cli-cmd-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        $credentials = $_SERVER['HOME'] . '/.zelta/credentials.json';
        if (file_exists($credentials)) {
            unlink($credentials);
        }
        $_SERVER['HOME'] = $this->homeBackup;
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: class-string<Command>, 1: string}>
     */
    public static function commandProvider(): array
    {
        return [
            'ramp:status'         => [RampStatusCommand::class, 'ramp:status'],
            'ramp:kyc-link'       => [KycLinkCommand::class, 'ramp:kyc-link'],
            'subscription:status' => [SubscriptionStatusCommand::class, 'subscription:status'],
        ];
    }

    /**
     * @param class-string<Command> $commandClass
     */
    #[DataProvider('commandProvider')]
    public function test_command_metadata(string $commandClass, string $expectedName): void
    {
        $command = new $commandClass();

        $this->assertSame($expectedName, $command->getName());
        $this->assertNotSame('', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('json'));
    }

    /**
     * @param class-string<Command> $commandClass
     */
    #[DataProvider('commandProvider')]
    public function test_exits_with_auth_code_when_not_logged_in(string $commandClass): void
    {
        $tester = new CommandTester(new $commandClass());

        $exitCode = $tester->execute([]);

        $this->assertSame(2, $exitCode);
        $this->assertStringContainsString('Not authenticated', $tester->getDisplay());
    }
}
