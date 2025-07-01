<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Setting;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\Crypt;
use Mockery;

class SettingTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Crypt facade
        Crypt::shouldReceive('encryptString')->andReturnUsing(function ($value) {
            return 'encrypted:' . $value;
        });
        Crypt::shouldReceive('decryptString')->andReturnUsing(function ($value) {
            return str_replace('encrypted:', '', $value);
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $setting = new Setting();
        $fillable = $setting->getFillable();
        
        $this->assertContains('key', $fillable);
        $this->assertContains('value', $fillable);
        $this->assertContains('type', $fillable);
        $this->assertContains('is_encrypted', $fillable);
        $this->assertContains('description', $fillable);
    }

    /** @test */
    public function it_has_correct_casts()
    {
        $setting = new Setting();
        $casts = $setting->getCasts();
        
        $this->assertEquals('boolean', $casts['is_encrypted']);
    }

    /** @test */
    public function it_encrypts_value_when_setting_encrypted()
    {
        $setting = new Setting();
        $setting->is_encrypted = true;
        $setting->value = 'secret';
        
        // Trigger the mutator
        $setting->setAttribute('value', 'secret');
        
        $this->assertEquals('encrypted:secret', $setting->getAttributes()['value']);
    }

    /** @test */
    public function it_does_not_encrypt_value_when_not_encrypted()
    {
        $setting = new Setting();
        $setting->is_encrypted = false;
        $setting->value = 'plain';
        
        // Trigger the mutator
        $setting->setAttribute('value', 'plain');
        
        $this->assertEquals('plain', $setting->getAttributes()['value']);
    }

    /** @test */
    public function it_decrypts_value_when_getting_encrypted()
    {
        $setting = new Setting();
        $setting->is_encrypted = true;
        $setting->setRawAttributes(['value' => 'encrypted:secret']);
        
        $this->assertEquals('secret', $setting->value);
    }

    /** @test */
    public function it_does_not_decrypt_value_when_not_encrypted()
    {
        $setting = new Setting();
        $setting->is_encrypted = false;
        $setting->setRawAttributes(['value' => 'plain']);
        
        $this->assertEquals('plain', $setting->value);
    }

    /** @test */
    public function it_casts_value_based_on_type()
    {
        $setting = new Setting();
        $setting->is_encrypted = false;
        
        // Boolean
        $setting->type = 'boolean';
        $setting->setRawAttributes(['value' => 'true']);
        $this->assertTrue($setting->getValueAttribute());
        
        $setting->setRawAttributes(['value' => 'false']);
        $this->assertFalse($setting->getValueAttribute());
        
        // Integer
        $setting->type = 'integer';
        $setting->setRawAttributes(['value' => '42']);
        $this->assertSame(42, $setting->getValueAttribute());
        
        // Float
        $setting->type = 'float';
        $setting->setRawAttributes(['value' => '3.14']);
        $this->assertSame(3.14, $setting->getValueAttribute());
        
        // JSON
        $setting->type = 'json';
        $setting->setRawAttributes(['value' => '{"key":"value"}']);
        $this->assertEquals(['key' => 'value'], $setting->getValueAttribute());
        
        // Array
        $setting->type = 'array';
        $setting->setRawAttributes(['value' => '["item1","item2"]']);
        $this->assertEquals(['item1', 'item2'], $setting->getValueAttribute());
        
        // String (default)
        $setting->type = 'string';
        $setting->setRawAttributes(['value' => 'test']);
        $this->assertEquals('test', $setting->getValueAttribute());
    }

    /** @test */
    public function it_handles_invalid_json()
    {
        $setting = new Setting();
        $setting->is_encrypted = false;
        $setting->type = 'json';
        $setting->setRawAttributes(['value' => 'invalid json']);
        
        $this->assertEquals('invalid json', $setting->getValueAttribute());
    }

    /** @test */
    public function it_serializes_value_based_on_type_when_setting()
    {
        $setting = new Setting();
        $setting->is_encrypted = false;
        
        // Boolean
        $setting->type = 'boolean';
        $setting->value = true;
        $this->assertEquals('true', $setting->getAttributes()['value']);
        
        $setting->value = false;
        $this->assertEquals('false', $setting->getAttributes()['value']);
        
        // Array/JSON
        $setting->type = 'array';
        $setting->value = ['item1', 'item2'];
        $this->assertEquals('["item1","item2"]', $setting->getAttributes()['value']);
        
        $setting->type = 'json';
        $setting->value = ['key' => 'value'];
        $this->assertEquals('{"key":"value"}', $setting->getAttributes()['value']);
        
        // Others
        $setting->type = 'integer';
        $setting->value = 42;
        $this->assertEquals('42', $setting->getAttributes()['value']);
    }

    /** @test */
    public function it_handles_null_values()
    {
        $setting = new Setting();
        $setting->is_encrypted = false;
        $setting->type = 'string';
        $setting->setRawAttributes(['value' => null]);
        
        $this->assertNull($setting->getValueAttribute());
    }

    /** @test */
    public function it_does_not_save_old_value_to_database()
    {
        $setting = new Setting();
        $setting->oldValue = 'previous';
        
        $attributes = $setting->getAttributes();
        $this->assertArrayNotHasKey('oldValue', $attributes);
    }

    /** @test */
    public function it_tracks_old_value_in_memory()
    {
        $setting = new Setting();
        $setting->oldValue = 'previous';
        
        $this->assertEquals('previous', $setting->oldValue);
    }
}