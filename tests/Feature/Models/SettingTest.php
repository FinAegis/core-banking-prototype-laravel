<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
            // If value starts with 'encrypted:', remove it and return JSON encoded result
            if (str_starts_with($value, 'encrypted:')) {
                $decrypted = str_replace('encrypted:', '', $value);

                // The Setting model expects the decrypted value to be JSON encoded
                return json_encode($decrypted);
            }

            // Otherwise just return the value (for decrypting actual encrypted values)
            return $value;
        });
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    #[Test]
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

    #[Test]
    public function it_has_correct_casts()
    {
        $setting = new Setting();
        $casts = $setting->getCasts();

        $this->assertEquals('boolean', $casts['is_encrypted']);
    }

    #[Test]
    public function it_encrypts_value_when_setting_encrypted()
    {
        $setting = new Setting();
        $setting->is_encrypted = true;
        $setting->value = 'secret';

        // Trigger the mutator
        $setting->setAttribute('value', 'secret');

        // The Setting model JSON encodes the encrypted value
        $this->assertEquals('"encrypted:\\"secret\\""', $setting->getAttributes()['value']);
    }

    #[Test]
    public function it_does_not_encrypt_value_when_not_encrypted()
    {
        $setting = new Setting();
        $setting->is_encrypted = false;
        $setting->value = 'plain';

        // Trigger the mutator
        $setting->setAttribute('value', 'plain');

        // The Setting model JSON encodes the value
        $this->assertEquals('"plain"', $setting->getAttributes()['value']);
    }

    #[Test]
    public function it_decrypts_value_when_getting_encrypted()
    {
        $setting = new Setting();
        $setting->is_encrypted = true;
        $setting->type = 'string';
        // The value in the database is JSON encoded encrypted value
        $setting->setRawAttributes(['value' => '"encrypted:secret"', 'type' => 'string', 'is_encrypted' => true]);

        $this->assertEquals('secret', $setting->value);
    }

    #[Test]
    public function it_does_not_decrypt_value_when_not_encrypted()
    {
        $setting = new Setting();
        $setting->is_encrypted = false;
        $setting->type = 'string';
        // The value in the database is JSON encoded
        $setting->setRawAttributes(['value' => '"plain"']);

        $this->assertEquals('plain', $setting->value);
    }

    #[Test]
    public function it_casts_value_based_on_type()
    {
        $setting = new Setting();
        $setting->is_encrypted = false;

        // Boolean - PHP's (bool) cast: non-empty string = true, empty/0/"0" = false
        $setting->type = 'boolean';
        $setting->setRawAttributes(['value' => 'true', 'type' => 'boolean']);
        $this->assertTrue($setting->value);

        $setting->setRawAttributes(['value' => '1', 'type' => 'boolean']);
        $this->assertTrue($setting->value);

        $setting->setRawAttributes(['value' => '0', 'type' => 'boolean']);
        $this->assertFalse($setting->value);

        $setting->setRawAttributes(['value' => 'null', 'type' => 'boolean']);
        $this->assertFalse($setting->value);

        // Integer
        $setting->type = 'integer';
        $setting->setRawAttributes(['value' => '42', 'type' => 'integer']);
        $this->assertSame(42, $setting->value);

        // Float
        $setting->type = 'float';
        $setting->setRawAttributes(['value' => '3.14', 'type' => 'float']);
        $this->assertSame(3.14, $setting->value);

        // JSON
        $setting->type = 'json';
        $setting->setRawAttributes(['value' => '{"key":"value"}', 'type' => 'json']);
        $this->assertEquals(['key' => 'value'], $setting->value);

        // Array
        $setting->type = 'array';
        $setting->setRawAttributes(['value' => '["item1","item2"]', 'type' => 'array']);
        $this->assertEquals(['item1', 'item2'], $setting->value);

        // String (default)
        $setting->type = 'string';
        $setting->setRawAttributes(['value' => '"test"', 'type' => 'string']);
        $this->assertEquals('test', $setting->value);
    }

    #[Test]
    public function it_handles_invalid_json()
    {
        $setting = new Setting();
        $setting->is_encrypted = false;
        $setting->type = 'json';
        $setting->setRawAttributes(['value' => '"invalid json"']);

        $this->assertEquals('invalid json', $setting->value);
    }

    #[Test]
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

    #[Test]
    public function it_handles_null_values()
    {
        $setting = new Setting();
        $setting->is_encrypted = false;
        $setting->setRawAttributes(['value' => null, 'type' => 'string']);

        // When value is null and type is string, PHP's (string) cast converts null to empty string
        $this->assertEquals('', $setting->value);

        // Test with json type to get actual null
        $setting->setRawAttributes(['value' => null, 'type' => 'json']);
        $this->assertNull($setting->value);
    }

    #[Test]
    public function it_does_not_save_old_value_to_database()
    {
        $setting = new Setting();
        $setting->oldValue = 'previous';

        $attributes = $setting->getAttributes();
        $this->assertArrayNotHasKey('oldValue', $attributes);
    }

    #[Test]
    public function it_tracks_old_value_in_memory()
    {
        $setting = new Setting();
        $setting->oldValue = 'previous';

        $this->assertEquals('previous', $setting->oldValue);
    }
}
