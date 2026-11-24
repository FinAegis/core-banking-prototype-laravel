<?php

declare(strict_types=1);

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Cgo\Models\CgoInvestment;
use App\Domain\Cgo\Models\CgoNotification;
use App\Domain\Compliance\Models\KycDocument;
use App\Domain\Exchange\Projections\OrderBook;
use App\Filament\Admin\Resources\BasketAssetResource\Widgets\BasketPerformanceWidget;
use App\Filament\Admin\Resources\CgoNotificationResource\Pages\ManageCgoNotifications;
use App\Filament\Exports\CgoNotificationExporter;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * These admin pages crashed when opened: Settings and Bank Allocations
 * imported models from namespaces that do not exist, Bank Operations fed a
 * plain collection to a Filament table and called methods Filament 3 removed,
 * and Audit Log was an empty scaffold on a model that does not exist.
 */

beforeEach(function (): void {
    $this->actingAs(User::factory()->withAdminRole()->create());
});

it('opens the settings page', function (): void {
    $this->get('/admin/settings')->assertOk();
});

it('opens the bank allocations list', function (): void {
    $this->get('/admin/user-bank-preferences')->assertOk();
});

it('no longer registers the pages that could never work', function (): void {
    $routes = collect(app('router')->getRoutes()->getRoutes())->map->uri()->all();

    expect($routes)->not->toContain('admin/bank-operations')
        ->and($routes)->not->toContain('admin/audit-logs');
});

/*
 * Kyc Documents, CGO Investments and Order Books were empty scaffolds: no
 * columns, an empty create/edit form, and delete actions. They are now
 * read-only lists of the real tables.
 */
it('lists KYC documents read-only', function (): void {
    $owner = User::factory()->create(['email' => 'kyc-owner@example.com']);
    KycDocument::factory()->create([
        'user_uuid'     => $owner->uuid,
        'document_type' => 'passport',
        'status'        => 'pending',
    ]);

    $this->get('/admin/kyc-documents')
        ->assertOk()
        ->assertSee('kyc-owner@example.com')
        ->assertSee('Passport');
});

it('lists CGO investments read-only', function (): void {
    $investor = User::factory()->create(['email' => 'cgo-investor@example.com']);
    CgoInvestment::create([
        'uuid'                 => (string) Str::uuid(),
        'user_id'              => $investor->id,
        'amount'               => '1000.00',
        'currency'             => 'USD',
        'share_price'          => '10.0000',
        'shares_purchased'     => '100.0000',
        'ownership_percentage' => '0.010000',
        'tier'                 => 'bronze',
        'status'               => 'pending',
        'payment_method'       => 'card',
        'certificate_number'   => 'CGO-CERT-0001',
    ]);

    $this->get('/admin/cgo-investments')
        ->assertOk()
        ->assertSee('cgo-investor@example.com')
        ->assertSee('CGO-CERT-0001');
});

it('lists order books read-only', function (): void {
    OrderBook::create([
        'order_book_id'  => (string) Str::uuid(),
        'base_currency'  => 'BTC',
        'quote_currency' => 'EUR',
        'best_bid'       => '41000.500000000000000000',
        'best_ask'       => '41010.000000000000000000',
        'volume_24h'     => '0',
    ]);

    $this->get('/admin/order-books')
        ->assertOk()
        ->assertSee('BTC/EUR')
        ->assertSee('41000.5');
});

it('registers no create or edit pages for the read-only lists', function (): void {
    $routes = collect(app('router')->getRoutes()->getRoutes())->map->uri()->all();

    foreach (['kyc-documents', 'cgo-investments', 'order-books'] as $slug) {
        expect($routes)->toContain("admin/{$slug}")
            ->and($routes)->not->toContain("admin/{$slug}/create")
            ->and($routes)->not->toContain("admin/{$slug}/{record}/edit");
    }
});

it('opens a basket asset with its performance history', function (): void {
    $basket = BasketAsset::factory()->create();

    $this->get("/admin/basket-assets/{$basket->id}")->assertOk();
});

it('renders the basket performance widget without a basket', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // The empty-state query used a class that does not exist.
    Livewire::test(BasketPerformanceWidget::class)->assertOk();
});

it('opens the CGO early access list', function (): void {
    CgoNotification::create(['email' => 'early@example.com']);

    $this->get('/admin/cgo-notifications')
        ->assertOk()
        ->assertSee('early@example.com');
});

it('exports CGO early access sign-ups with an exporter', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setServingStatus(true);
    Bus::fake();

    $signup = CgoNotification::create(['email' => 'export-me@example.com']);

    Livewire::test(ManageCgoNotifications::class)
        ->callTableBulkAction('export', [$signup])
        ->assertHasNoTableBulkActionErrors();

    $export = Export::query()->latest('id')->first();
    expect($export)->not->toBeNull()
        ->and($export?->exporter)->toBe(CgoNotificationExporter::class)
        ->and($export?->total_rows)->toBe(1);
});
