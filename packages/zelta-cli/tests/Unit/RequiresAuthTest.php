<?php

declare(strict_types=1);

namespace ZeltaCli\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use ZeltaCli\Concerns\RequiresAuth;
use ZeltaCli\Services\AuthManager;

class RequiresAuthTest extends TestCase
{
    use RequiresAuth;

    private string $credPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->credPath = sys_get_temp_dir() . '/zelta-auth-test-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->credPath)) {
            unlink($this->credPath);
        }
        parent::tearDown();
    }

    public function test_returns_true_when_authenticated(): void
    {
        $auth = new AuthManager($this->credPath);
        $auth->login('zk_test_key', 'default');

        $output = new BufferedOutput();
        $this->assertTrue($this->ensureAuthenticated($auth, $output));
        $this->assertEmpty($output->fetch());
    }

    public function test_returns_false_and_prints_error_when_not_authenticated(): void
    {
        $auth = new AuthManager($this->credPath);

        $output = new BufferedOutput();
        $this->assertFalse($this->ensureAuthenticated($auth, $output));
        $this->assertStringContainsString('Not authenticated', $output->fetch());
    }

    public function test_works_without_output_parameter(): void
    {
        $auth = new AuthManager($this->credPath);

        // Should not crash when output is null
        $this->assertFalse($this->ensureAuthenticated($auth));
    }
}
