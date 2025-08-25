<?php

declare(strict_types=1);

namespace App\Domain\Product\Aggregates;

use App\Domain\Product\Events\FeatureAdded;
use App\Domain\Product\Events\FeatureRemoved;
use App\Domain\Product\Events\PriceUpdated;
use App\Domain\Product\Events\ProductActivated;
use App\Domain\Product\Events\ProductCreated;
use App\Domain\Product\Events\ProductDeactivated;
use App\Domain\Product\Events\ProductUpdated;
use App\Domain\Product\ValueObjects\Feature;
use App\Domain\Product\ValueObjects\Price;
use DateTimeImmutable;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class Product extends AggregateRoot
{
    private string $productId;

    private string $name;

    private string $description;

    private string $category;

    private string $type;

    private array $features = [];

    private array $prices = [];

    private string $status = 'draft';

    private array $metadata = [];

    private ?DateTimeImmutable $activatedAt = null;

    private ?DateTimeImmutable $deactivatedAt = null;

    public static function create(
        string $productId,
        string $name,
        string $description,
        string $category,
        string $type,
        array $metadata = []
    ): self {
        $product = (new self())->loadUuid($productId);
        $product->recordThat(new ProductCreated(
            productId: $productId,
            name: $name,
            description: $description,
            category: $category,
            type: $type,
            metadata: $metadata,
            createdAt: new DateTimeImmutable()
        ));

        return $product;
    }

    public function update(array $data, string $updatedBy): self
    {
        $allowedFields = ['name', 'description', 'category', 'type', 'metadata'];
        $updates = array_intersect_key($data, array_flip($allowedFields));

        if (! empty($updates)) {
            $this->recordThat(new ProductUpdated(
                productId: $this->productId,
                updates: $updates,
                updatedBy: $updatedBy,
                updatedAt: new DateTimeImmutable()
            ));
        }

        return $this;
    }

    public function addFeature(Feature $feature, string $addedBy): self
    {
        if (isset($this->features[$feature->getCode()])) {
            throw new \DomainException("Feature {$feature->getCode()} already exists");
        }

        $this->recordThat(new FeatureAdded(
            productId: $this->productId,
            feature: $feature->toArray(),
            addedBy: $addedBy,
            addedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    public function removeFeature(string $featureCode, string $removedBy): self
    {
        if (! isset($this->features[$featureCode])) {
            throw new \DomainException("Feature {$featureCode} does not exist");
        }

        $this->recordThat(new FeatureRemoved(
            productId: $this->productId,
            featureCode: $featureCode,
            removedBy: $removedBy,
            removedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    public function updatePrice(Price $price, string $updatedBy): self
    {
        $this->recordThat(new PriceUpdated(
            productId: $this->productId,
            price: $price->toArray(),
            updatedBy: $updatedBy,
            updatedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    public function activate(string $activatedBy): self
    {
        if ($this->status === 'active') {
            throw new \DomainException('Product is already active');
        }

        $this->recordThat(new ProductActivated(
            productId: $this->productId,
            activatedBy: $activatedBy,
            activatedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    public function deactivate(string $reason, string $deactivatedBy): self
    {
        if ($this->status !== 'active') {
            throw new \DomainException('Product is not active');
        }

        $this->recordThat(new ProductDeactivated(
            productId: $this->productId,
            reason: $reason,
            deactivatedBy: $deactivatedBy,
            deactivatedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    // Apply methods
    protected function applyProductCreated(ProductCreated $event): void
    {
        $this->productId = $event->productId;
        $this->name = $event->name;
        $this->description = $event->description;
        $this->category = $event->category;
        $this->type = $event->type;
        $this->metadata = $event->metadata;
        $this->status = 'draft';
    }

    protected function applyProductUpdated(ProductUpdated $event): void
    {
        foreach ($event->updates as $field => $value) {
            if (property_exists($this, $field)) {
                $this->$field = $value;
            }
        }
    }

    protected function applyFeatureAdded(FeatureAdded $event): void
    {
        $feature = Feature::fromArray($event->feature);
        $this->features[$feature->getCode()] = $feature;
    }

    protected function applyFeatureRemoved(FeatureRemoved $event): void
    {
        unset($this->features[$event->featureCode]);
    }

    protected function applyPriceUpdated(PriceUpdated $event): void
    {
        $price = Price::fromArray($event->price);
        $this->prices[$price->getCurrency()] = $price;
    }

    protected function applyProductActivated(ProductActivated $event): void
    {
        $this->status = 'active';
        $this->activatedAt = $event->activatedAt;
    }

    protected function applyProductDeactivated(ProductDeactivated $event): void
    {
        $this->status = 'inactive';
        $this->deactivatedAt = $event->deactivatedAt;
    }

    // Getters
    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getFeatures(): array
    {
        return $this->features;
    }

    public function getPrices(): array
    {
        return $this->prices;
    }

    public function getPrice(string $currency = 'USD'): ?Price
    {
        return $this->prices[$currency] ?? null;
    }
}
