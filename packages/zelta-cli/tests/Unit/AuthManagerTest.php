<?php

declare(strict_types=1);

namespace ZeltaCli\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZeltaCli\Services\AuthManager;

class AuthManagerTest extends TestCase
{
    private string $tempDir;

    private string $credPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/zelta-test-' . uniqid();
        mkdir($this->tempDir, 0700, true);
        $this->credPath = $this->tempDir . '/credentials.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->credPath)) {
            unlink($this->credPath);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_login_stores_credentials(): void
    {
        $auth = new AuthManager($this->credPath);
        $auth->login('zk_test_abc', 'default', 'https://api.test');

        $this->assertTrue($auth->isAuthenticated());
        $this->assertEquals('zk_test_abc', $auth->getApiKey());
        $this->assertEquals('https://api.test', $auth->getBaseUrl());
    }

    public function test_logout_removes_profile(): void
    {
        $auth = new AuthManager($this->credPath);
        $auth->login('zk_test_abc', 'default');
        $auth->logout('default');

        $this->assertFalse($auth->isAuthenticated());
        $this->assertNull($auth->getApiKey());
    }

    public function test_multi_profile_support(): void
    {
        $auth = new AuthManager($this->credPath);
        $auth->login('zk_prod_111', 'production', 'https://api.zelta.app');
        $auth->login('zk_stag_222', 'staging', 'https://staging.zelta.app');

        $this->assertEquals('staging', $auth->getActiveProfile());
        $this->assertEquals('zk_stag_222', $auth->getApiKey());
        $this->assertEquals('zk_prod_111', $auth->getApiKey('production'));
    }

    public function test_credentials_file_permissions(): void
    {
        $auth = new AuthManager($this->credPath);
        $auth->login('zk_test_abc', 'default');

        $this->assertFileExists($this->credPath);
        $perms = fileperms($this->credPath) & 0777;
        $this->assertEquals(0600, $perms, 'Credentials file should be owner-only readable');
    }

    public function test_unauthenticated_returns_null(): void
    {
        $auth = new AuthManager($this->credPath);

        $this->assertFalse($auth->isAuthenticated());
        $this->assertNull($auth->getApiKey());
    }

    public function test_list_profiles(): void
    {
        $auth = new AuthManager($this->credPath);
        $auth->login('key1', 'prod');
        $auth->login('key2', 'staging');

        $profiles = $auth->listProfiles();
        $this->assertArrayHasKey('prod', $profiles);
        $this->assertArrayHasKey('staging', $profiles);
        $this->assertCount(2, $profiles);
    }

    public function test_api_key_masked_correctly(): void
    {
        $auth = new AuthManager($this->credPath);
        $auth->login('zk_live_A1b2C3d4E5f6', 'default');

        $key = $auth->getApiKey();
        $masked = '***' . substr($key ?? '', -4);
        $this->assertEquals('***E5f6', $masked);
        $this->assertStringNotContainsString('zk_live', $masked);
    }
}
