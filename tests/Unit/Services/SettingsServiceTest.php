<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SettingsService;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\UnitTestCase;

class SettingsServiceTest extends UnitTestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected SettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Setting model
        Setting::partialMock();
        
        // Mock Cache facade
        Cache::shouldReceive('forget')->andReturn(true);
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });
        
        // Mock Crypt facade
        Crypt::shouldReceive('encryptString')->andReturnUsing(function ($value) {
            return 'encrypted:' . $value;
        });
        Crypt::shouldReceive('decryptString')->andReturnUsing(function ($value) {
            return str_replace('encrypted:', '', $value);
        });
        
        $this->service = new SettingsService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_gets_setting_value()
    {
        Setting::shouldReceive('where->first')->andReturn((object)[
            'value' => 'test_value',
            'type' => 'string',
            'is_encrypted' => false
        ]);
        
        $result = $this->service->get('test.key');
        
        $this->assertEquals('test_value', $result);
    }

    /** @test */
    public function it_returns_default_when_setting_not_found()
    {
        Setting::shouldReceive('where->first')->andReturn(null);
        
        $result = $this->service->get('test.key', 'default');
        
        $this->assertEquals('default', $result);
    }

    /** @test */
    public function it_decrypts_encrypted_settings()
    {
        Setting::shouldReceive('where->first')->andReturn((object)[
            'value' => 'encrypted:secret',
            'type' => 'string',
            'is_encrypted' => true
        ]);
        
        $result = $this->service->get('test.key');
        
        $this->assertEquals('secret', $result);
    }

    /** @test */
    public function it_casts_boolean_settings()
    {
        Setting::shouldReceive('where->first')->andReturn((object)[
            'value' => 'true',
            'type' => 'boolean',
            'is_encrypted' => false
        ]);
        
        $result = $this->service->get('test.key');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_casts_integer_settings()
    {
        Setting::shouldReceive('where->first')->andReturn((object)[
            'value' => '42',
            'type' => 'integer',
            'is_encrypted' => false
        ]);
        
        $result = $this->service->get('test.key');
        
        $this->assertSame(42, $result);
    }

    /** @test */
    public function it_casts_json_settings()
    {
        Setting::shouldReceive('where->first')->andReturn((object)[
            'value' => '{"key":"value"}',
            'type' => 'json',
            'is_encrypted' => false
        ]);
        
        $result = $this->service->get('test.key');
        
        $this->assertEquals(['key' => 'value'], $result);
    }

    /** @test */
    public function it_casts_array_settings()
    {
        Setting::shouldReceive('where->first')->andReturn((object)[
            'value' => '["item1","item2"]',
            'type' => 'array',
            'is_encrypted' => false
        ]);
        
        $result = $this->service->get('test.key');
        
        $this->assertEquals(['item1', 'item2'], $result);
    }

    /** @test */
    public function it_sets_new_setting()
    {
        $mockSetting = Mockery::mock(Setting::class);
        $mockSetting->shouldReceive('updateOrCreate')
            ->once()
            ->with(
                ['key' => 'test.key'],
                [
                    'value' => 'test_value',
                    'type' => 'string',
                    'is_encrypted' => false,
                    'description' => null
                ]
            )
            ->andReturn($mockSetting);
        
        Setting::swap($mockSetting);
        
        $this->service->set('test.key', 'test_value');
        
        Cache::shouldHaveReceived('forget')->with('settings.test.key');
    }

    /** @test */
    public function it_encrypts_sensitive_settings()
    {
        $mockSetting = Mockery::mock(Setting::class);
        $mockSetting->shouldReceive('updateOrCreate')
            ->once()
            ->with(
                ['key' => 'test.key'],
                [
                    'value' => 'encrypted:secret',
                    'type' => 'string',
                    'is_encrypted' => true,
                    'description' => null
                ]
            )
            ->andReturn($mockSetting);
        
        Setting::swap($mockSetting);
        
        $this->service->set('test.key', 'secret', 'string', true);
    }

    /** @test */
    public function it_sets_json_settings()
    {
        $mockSetting = Mockery::mock(Setting::class);
        $mockSetting->shouldReceive('updateOrCreate')
            ->once()
            ->with(
                ['key' => 'test.key'],
                [
                    'value' => '{"key":"value"}',
                    'type' => 'json',
                    'is_encrypted' => false,
                    'description' => null
                ]
            )
            ->andReturn($mockSetting);
        
        Setting::swap($mockSetting);
        
        $this->service->set('test.key', ['key' => 'value'], 'json');
    }

    /** @test */
    public function it_deletes_setting()
    {
        $mockSetting = Mockery::mock(Setting::class);
        $mockSetting->shouldReceive('where->delete')
            ->once()
            ->andReturn(true);
        
        Setting::swap($mockSetting);
        
        $result = $this->service->delete('test.key');
        
        $this->assertTrue($result);
        Cache::shouldHaveReceived('forget')->with('settings.test.key');
    }

    /** @test */
    public function it_checks_if_setting_exists()
    {
        Setting::shouldReceive('where->exists')
            ->once()
            ->andReturn(true);
        
        $result = $this->service->has('test.key');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_gets_multiple_settings()
    {
        Setting::shouldReceive('where->first')
            ->andReturn((object)[
                'value' => 'value1',
                'type' => 'string',
                'is_encrypted' => false
            ], (object)[
                'value' => 'value2',
                'type' => 'string',
                'is_encrypted' => false
            ]);
        
        $result = $this->service->getMultiple(['key1', 'key2']);
        
        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2'
        ], $result);
    }

    /** @test */
    public function it_sets_multiple_settings()
    {
        $mockSetting = Mockery::mock(Setting::class);
        $mockSetting->shouldReceive('updateOrCreate')
            ->twice()
            ->andReturn($mockSetting);
        
        Setting::swap($mockSetting);
        
        $this->service->setMultiple([
            'key1' => 'value1',
            'key2' => 'value2'
        ]);
        
        Cache::shouldHaveReceived('forget')->with('settings.key1');
        Cache::shouldHaveReceived('forget')->with('settings.key2');
    }

    /** @test */
    public function it_gets_all_settings()
    {
        $mockSetting = Mockery::mock(Setting::class);
        $mockSetting->shouldReceive('all')->andReturn(collect([
            (object)['key' => 'key1', 'value' => 'value1', 'type' => 'string', 'is_encrypted' => false],
            (object)['key' => 'key2', 'value' => 'true', 'type' => 'boolean', 'is_encrypted' => false],
        ]));
        
        Setting::swap($mockSetting);
        
        $result = $this->service->all();
        
        $this->assertEquals([
            'key1' => 'value1',
            'key2' => true
        ], $result);
    }

    /** @test */
    public function it_gets_settings_by_prefix()
    {
        $mockSetting = Mockery::mock(Setting::class);
        $mockSetting->shouldReceive('where->get')->andReturn(collect([
            (object)['key' => 'app.name', 'value' => 'FinAegis', 'type' => 'string', 'is_encrypted' => false],
            (object)['key' => 'app.debug', 'value' => 'false', 'type' => 'boolean', 'is_encrypted' => false],
        ]));
        
        Setting::swap($mockSetting);
        
        $result = $this->service->getByPrefix('app');
        
        $this->assertEquals([
            'app.name' => 'FinAegis',
            'app.debug' => false
        ], $result);
    }

    /** @test */
    public function it_caches_settings()
    {
        // Cache should be called with remember
        Cache::shouldReceive('remember')
            ->once()
            ->with('settings.test.key', 3600, Mockery::type('Closure'))
            ->andReturn('cached_value');
        
        $result = $this->service->get('test.key');
        
        $this->assertEquals('cached_value', $result);
    }
}