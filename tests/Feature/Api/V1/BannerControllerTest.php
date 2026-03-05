<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BannerControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_list_banners_requires_auth(): void
    {
        $this->getJson('/api/v1/banners')
            ->assertStatus(401);
    }

    public function test_list_banners_returns_active_visible_banners(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        Banner::create(['title' => 'Active Banner', 'active' => true, 'position' => 1]);
        Banner::create(['title' => 'Inactive Banner', 'active' => false, 'position' => 2]);

        $response = $this->getJson('/api/v1/banners')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'subtitle', 'image_url', 'action_url', 'action_type', 'cta_label', 'position'],
                ],
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Active Banner', $response->json('data.0.title'));
    }

    public function test_list_banners_filters_by_date_range(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        Banner::create([
            'title'     => 'Future Banner',
            'active'    => true,
            'starts_at' => now()->addDay(),
            'position'  => 1,
        ]);
        Banner::create([
            'title'    => 'Expired Banner',
            'active'   => true,
            'ends_at'  => now()->subDay(),
            'position' => 2,
        ]);
        Banner::create([
            'title'     => 'Current Banner',
            'active'    => true,
            'starts_at' => now()->subDay(),
            'ends_at'   => now()->addDay(),
            'position'  => 3,
        ]);

        $response = $this->getJson('/api/v1/banners')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Current Banner', $response->json('data.0.title'));
    }

    public function test_list_banners_excludes_dismissed(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        Banner::create([
            'title'        => 'Dismissed',
            'active'       => true,
            'dismissed_by' => [$this->user->id],
            'position'     => 1,
        ]);
        Banner::create(['title' => 'Visible', 'active' => true, 'position' => 2]);

        $response = $this->getJson('/api/v1/banners')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Visible', $response->json('data.0.title'));
    }

    public function test_dismiss_banner(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $banner = Banner::create(['title' => 'Dismissable', 'active' => true, 'position' => 1]);

        $this->postJson("/api/v1/banners/{$banner->id}/dismiss")
            ->assertOk()
            ->assertJsonPath('message', 'Banner dismissed');

        $banner->refresh();
        $this->assertTrue($banner->isDismissedBy($this->user->id));

        // Verify it no longer appears in listing
        $response = $this->getJson('/api/v1/banners')->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_dismiss_nonexistent_banner_returns_404(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $this->postJson('/api/v1/banners/99999/dismiss')
            ->assertStatus(404);
    }

    public function test_cta_label_uses_custom_value_from_db(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        Banner::create([
            'title'       => 'Custom CTA',
            'active'      => true,
            'action_type' => 'url',
            'cta_label'   => 'Shop Now',
            'position'    => 1,
        ]);
        Banner::create([
            'title'       => 'Default CTA',
            'active'      => true,
            'action_type' => 'screen',
            'position'    => 2,
        ]);

        $response = $this->getJson('/api/v1/banners')->assertOk();

        $data = $response->json('data');
        $custom = collect($data)->firstWhere('title', 'Custom CTA');
        $default = collect($data)->firstWhere('title', 'Default CTA');

        $this->assertEquals('Shop Now', $custom['cta_label']);
        $this->assertEquals('View', $default['cta_label']);
    }
}
