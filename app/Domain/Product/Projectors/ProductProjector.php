<?php

declare(strict_types=1);

namespace App\Domain\Product\Projectors;

use App\Domain\Product\Events\FeatureAdded;
use App\Domain\Product\Events\FeatureRemoved;
use App\Domain\Product\Events\PriceUpdated;
use App\Domain\Product\Events\ProductActivated;
use App\Domain\Product\Events\ProductCreated;
use App\Domain\Product\Events\ProductDeactivated;
use App\Domain\Product\Events\ProductUpdated;
use App\Domain\Product\Models\Product;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class ProductProjector extends Projector
{
    public function onProductCreated(ProductCreated $event): void
    {
        Product::create([
            'id'          => $event->productId,
            'name'        => $event->name,
            'description' => $event->description,
            'category'    => $event->category,
            'type'        => $event->type,
            'status'      => 'draft',
            'metadata'    => $event->metadata,
            'features'    => [],
            'prices'      => [],
            'created_at'  => $event->createdAt,
        ]);
    }

    public function onProductUpdated(ProductUpdated $event): void
    {
        $product = Product::find($event->productId);

        if ($product) {
            $product->update($event->updates);
        }
    }

    public function onFeatureAdded(FeatureAdded $event): void
    {
        $product = Product::find($event->productId);

        if ($product) {
            $features = $product->features ?? [];
            $features[] = $event->feature;
            $product->update(['features' => $features]);
        }
    }

    public function onFeatureRemoved(FeatureRemoved $event): void
    {
        $product = Product::find($event->productId);

        if ($product) {
            $features = $product->features ?? [];
            $features = array_filter($features, function ($feature) use ($event) {
                return $feature['code'] !== $event->featureCode;
            });
            $product->update(['features' => array_values($features)]);
        }
    }

    public function onPriceUpdated(PriceUpdated $event): void
    {
        $product = Product::find($event->productId);

        if ($product) {
            $prices = $product->prices ?? [];
            $currency = $event->price['currency'];

            // Update or add price for this currency
            $found = false;
            foreach ($prices as &$price) {
                if ($price['currency'] === $currency) {
                    $price = $event->price;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $prices[] = $event->price;
            }

            $product->update(['prices' => $prices]);
        }
    }

    public function onProductActivated(ProductActivated $event): void
    {
        Product::where('id', $event->productId)->update([
            'status'       => 'active',
            'activated_at' => $event->activatedAt,
        ]);
    }

    public function onProductDeactivated(ProductDeactivated $event): void
    {
        Product::where('id', $event->productId)->update([
            'status'         => 'inactive',
            'deactivated_at' => $event->deactivatedAt,
        ]);
    }
}
