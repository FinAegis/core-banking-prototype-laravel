<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected SettingsService $settingsService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->settingsService = app(SettingsService::class);
        $this->settingsService->initializeSettings();
    }

    public function test_settings_can_be_initialized(): void
    {
        $this->assertDatabaseCount('settings', 26); // Count all default settings
        
        $setting = Setting::where('key', 'platform_name')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('FinAegis', $setting->value);
        $this->assertEquals('platform', $setting->group);
    }

    public function test_setting_can_be_retrieved(): void
    {
        $value = Setting::get('platform_name');
        $this->assertEquals('FinAegis', $value);
        
        $defaultValue = Setting::get('non_existent_key', 'default');
        $this->assertEquals('default', $defaultValue);
    }

    public function test_setting_can_be_updated(): void
    {
        $updated = $this->settingsService->updateSetting('platform_name', 'New Platform Name');
        $this->assertTrue($updated);
        
        $value = Setting::get('platform_name');
        $this->assertEquals('New Platform Name', $value);
    }

    public function test_setting_validation_works(): void
    {
        $validation = $this->settingsService->validateSetting('transaction_fee_percentage', 5.5);
        $this->assertTrue($validation['valid']);
        
        $validation = $this->settingsService->validateSetting('transaction_fee_percentage', 15);
        $this->assertFalse($validation['valid']);
        $this->assertContains('The value field must not be greater than 10.', $validation['errors']);
    }

    public function test_settings_are_cached(): void
    {
        $value1 = Setting::get('platform_name');
        
        // Update directly in database (bypass cache)
        Setting::where('key', 'platform_name')->update(['value' => '"Direct Update"']);
        
        // Should still get cached value
        $value2 = Setting::get('platform_name');
        $this->assertEquals($value1, $value2);
        
        // Clear cache and get new value
        \Cache::forget('setting.platform_name');
        $value3 = Setting::get('platform_name');
        $this->assertEquals('Direct Update', $value3);
    }

    public function test_settings_can_be_retrieved_by_group(): void
    {
        $platformSettings = Setting::getGroup('platform');
        
        $this->assertArrayHasKey('platform_name', $platformSettings);
        $this->assertArrayHasKey('maintenance_mode', $platformSettings);
        $this->assertArrayHasKey('session_timeout', $platformSettings);
    }

    public function test_public_settings_api_endpoint(): void
    {
        // Mark some settings as public
        Setting::where('key', 'platform_name')->update(['is_public' => true]);
        Setting::where('key', 'transaction_fee_percentage')->update(['is_public' => true]);
        
        $response = $this->getJson('/api/settings');
        
        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonFragment(['platform_name' => 'FinAegis'])
            ->assertJsonFragment(['transaction_fee_percentage' => 0.01]);
    }

    public function test_settings_by_group_api_endpoint(): void
    {
        // Mark platform settings as public
        Setting::where('group', 'platform')->update(['is_public' => true]);
        
        $response = $this->getJson('/api/settings/group/platform');
        
        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonFragment(['platform_name' => 'FinAegis']);
    }

    public function test_encrypted_settings_are_protected(): void
    {
        Setting::set('api_key', 'secret-key-123', [
            'type' => 'string',
            'label' => 'API Key',
            'description' => 'Secret API key',
            'group' => 'security',
            'is_encrypted' => true,
        ]);
        
        // Direct database query should show encrypted value
        $dbValue = \DB::table('settings')->where('key', 'api_key')->value('value');
        $decodedDb = json_decode($dbValue, true);
        $this->assertIsString($decodedDb);
        $this->assertStringStartsWith('eyJpdiI6', $decodedDb); // Laravel encrypted strings start with this
        
        // But model should decrypt it
        $value = Setting::get('api_key');
        $this->assertEquals('secret-key-123', $value);
    }

    public function test_settings_export_excludes_encrypted(): void
    {
        Setting::set('api_key', 'secret-key-123', [
            'type' => 'string',
            'label' => 'API Key',
            'description' => 'Secret API key',
            'group' => 'security',
            'is_encrypted' => true,
        ]);
        
        $exported = $this->settingsService->exportSettings();
        
        $this->assertArrayNotHasKey('api_key', $exported);
        $this->assertArrayHasKey('platform_name', $exported);
    }

    public function test_settings_import_validates_each_setting(): void
    {
        $settings = [
            'platform_name' => 'Imported Platform',
            'transaction_fee_percentage' => 2.5,
            'invalid_fee' => 20, // This should fail validation
        ];
        
        $results = $this->settingsService->importSettings($settings, 'test@example.com');
        
        $this->assertContains('platform_name', $results['success']);
        $this->assertContains('transaction_fee_percentage', $results['success']);
        $this->assertContains('invalid_fee', $results['failed']);
        
        $this->assertEquals('Imported Platform', Setting::get('platform_name'));
        $this->assertEquals(2.5, Setting::get('transaction_fee_percentage'));
    }

    public function test_boolean_settings_are_cast_correctly(): void
    {
        Setting::set('maintenance_mode', true, [
            'type' => 'boolean',
            'label' => 'Maintenance Mode',
            'description' => 'Enable maintenance mode',
            'group' => 'platform',
        ]);
        
        $value = Setting::get('maintenance_mode');
        $this->assertIsBool($value);
        $this->assertTrue($value);
        
        Setting::set('maintenance_mode', 0, [
            'type' => 'boolean',
            'label' => 'Maintenance Mode',
            'description' => 'Enable maintenance mode',
            'group' => 'platform',
        ]);
        
        $value = Setting::get('maintenance_mode');
        $this->assertIsBool($value);
        $this->assertFalse($value);
    }
}