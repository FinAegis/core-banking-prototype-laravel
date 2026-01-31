<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\ServiceTestCase;

class SettingsServiceTest extends ServiceTestCase
{
    protected SettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SettingsService();
    }

    #[Test]
    public function it_gets_setting_value()
    {
        Setting::create([
            'key'          => 'test.key',
            'value'        => 'test_value',
            'type'         => 'string',
            'is_encrypted' => false,
            'label'        => 'Test Key',
            'group'        => 'test',
        ]);

        $result = $this->service->get('test.key');

        $this->assertEquals('test_value', $result);
    }

    #[Test]
    public function it_returns_default_when_setting_not_found()
    {
        $result = $this->service->get('non.existent.key', 'default');

        $this->assertEquals('default', $result);
    }

    #[Test]
    public function it_decrypts_encrypted_settings()
    {
        // Test encryption/decryption functionality
        $key = 'test.encrypted.setting';
        $value = 'sensitive-data-123';

        // Set an encrypted setting
        $this->service->set($key, $value, 'string', true);

        // Retrieve the encrypted setting
        $retrieved = $this->service->get($key);

        // Should match the original value (automatically decrypted)
        $this->assertEquals($value, $retrieved);

        // Verify it's stored encrypted in the database
        $setting = Setting::where('key', $key)->first();
        $this->assertTrue($setting->is_encrypted);

        // Raw value should be different from original (encrypted)
        $this->assertNotEquals($value, $setting->getRawOriginal('value'));
    }

    #[Test]
    public function it_casts_boolean_settings()
    {
        Setting::create([
            'key'          => 'test.key',
            'value'        => true,
            'type'         => 'boolean',
            'is_encrypted' => false,
            'label'        => 'Test Key',
            'group'        => 'test',
        ]);

        $result = $this->service->get('test.key');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_casts_integer_settings()
    {
        Setting::create([
            'key'          => 'test.key',
            'value'        => 42,
            'type'         => 'integer',
            'is_encrypted' => false,
            'label'        => 'Test Key',
            'group'        => 'test',
        ]);

        $result = $this->service->get('test.key');

        $this->assertSame(42, $result);
    }

    #[Test]
    public function it_casts_json_settings()
    {
        Setting::create([
            'key'          => 'test.key',
            'value'        => ['key' => 'value'],
            'type'         => 'json',
            'is_encrypted' => false,
            'label'        => 'Test Key',
            'group'        => 'test',
        ]);

        $result = $this->service->get('test.key');

        $this->assertEquals(['key' => 'value'], $result);
    }

    #[Test]
    public function it_casts_array_settings()
    {
        Setting::create([
            'key'          => 'test.key',
            'value'        => ['item1', 'item2'],
            'type'         => 'array',
            'is_encrypted' => false,
            'label'        => 'Test Key',
            'group'        => 'test',
        ]);

        $result = $this->service->get('test.key');

        $this->assertEquals(['item1', 'item2'], $result);
    }

    #[Test]
    public function it_sets_new_setting()
    {
        $this->service->set('test.key', 'test_value');

        $setting = Setting::where('key', 'test.key')->first();

        $this->assertNotNull($setting);
        $this->assertEquals('test_value', $setting->value);
        $this->assertEquals('string', $setting->type);
        $this->assertFalse($setting->is_encrypted);
    }

    #[Test]
    public function it_encrypts_sensitive_settings()
    {
        $this->service->set('test.key', 'secret', 'string', true);

        $setting = Setting::where('key', 'test.key')->first();

        $this->assertNotNull($setting);
        $this->assertTrue($setting->is_encrypted);
    }

    #[Test]
    public function it_sets_json_settings()
    {
        $this->service->set('test.key', ['key' => 'value'], 'json');

        $setting = Setting::where('key', 'test.key')->first();

        $this->assertNotNull($setting);
        $this->assertEquals(['key' => 'value'], $setting->value);
        $this->assertEquals('json', $setting->type);
    }

    #[Test]
    public function it_deletes_setting()
    {
        Setting::create([
            'key'          => 'test.key',
            'value'        => 'test_value',
            'type'         => 'string',
            'is_encrypted' => false,
            'label'        => 'Test Key',
            'group'        => 'test',
        ]);

        $result = $this->service->delete('test.key');

        $this->assertTrue($result);
        $this->assertNull(Setting::where('key', 'test.key')->first());
    }

    #[Test]
    public function it_checks_if_setting_exists()
    {
        Setting::create([
            'key'          => 'test.key',
            'value'        => 'test_value',
            'type'         => 'string',
            'is_encrypted' => false,
            'label'        => 'Test Key',
            'group'        => 'test',
        ]);

        $result = $this->service->has('test.key');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_gets_multiple_settings()
    {
        Setting::create([
            'key'          => 'key1',
            'value'        => 'value1',
            'type'         => 'string',
            'is_encrypted' => false,
            'label'        => 'Key 1',
            'group'        => 'test',
        ]);

        Setting::create([
            'key'          => 'key2',
            'value'        => 'value2',
            'type'         => 'string',
            'is_encrypted' => false,
            'label'        => 'Key 2',
            'group'        => 'test',
        ]);

        $result = $this->service->getMultiple(['key1', 'key2']);

        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2',
        ], $result);
    }

    #[Test]
    public function it_sets_multiple_settings()
    {
        $this->service->setMultiple([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        $this->assertEquals('value1', Setting::get('key1'));
        $this->assertEquals('value2', Setting::get('key2'));
    }

    #[Test]
    public function it_gets_all_settings()
    {
        Setting::create([
            'key'          => 'key1',
            'value'        => 'value1',
            'type'         => 'string',
            'is_encrypted' => false,
            'label'        => 'Key 1',
            'group'        => 'test',
        ]);

        Setting::create([
            'key'          => 'key2',
            'value'        => true,
            'type'         => 'boolean',
            'is_encrypted' => false,
            'label'        => 'Key 2',
            'group'        => 'test',
        ]);

        $result = $this->service->all();

        $this->assertEquals([
            'key1' => 'value1',
            'key2' => true,
        ], $result);
    }

    #[Test]
    public function it_gets_settings_by_prefix()
    {
        Setting::create([
            'key'          => 'app.name',
            'value'        => 'FinAegis',
            'type'         => 'string',
            'is_encrypted' => false,
            'label'        => 'Application Name',
            'group'        => 'app',
        ]);

        Setting::create([
            'key'          => 'app.debug',
            'value'        => false,
            'type'         => 'boolean',
            'is_encrypted' => false,
            'label'        => 'Debug Mode',
            'group'        => 'app',
        ]);

        $result = $this->service->getByPrefix('app');

        $this->assertEquals([
            'app.name'  => 'FinAegis',
            'app.debug' => false,
        ], $result);
    }

    #[Test]
    public function it_caches_settings()
    {
        Setting::create([
            'key'          => 'test.key',
            'value'        => 'cached_value',
            'type'         => 'string',
            'is_encrypted' => false,
            'label'        => 'Test Key',
            'group'        => 'test',
        ]);

        // First call - should cache
        $result1 = $this->service->get('test.key');

        // Update the database directly
        Setting::where('key', 'test.key')->update(['value' => json_encode('new_value')]);

        // Second call - should still return cached value
        $result2 = $this->service->get('test.key');

        $this->assertEquals('cached_value', $result1);
        $this->assertEquals('cached_value', $result2);
    }
}
