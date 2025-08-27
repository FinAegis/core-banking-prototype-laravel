<?php

namespace App\Testing;

use Carbon\Carbon;
use Exception;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Array-based event serializer for testing purposes.
 * Returns array structure instead of JSON string.
 */
class ArrayEventSerializer
{
    public function serialize(object $event): array
    {
        $data = [];
        $reflection = new ReflectionClass($event);

        foreach ($reflection->getProperties() as $property) {
            // Skip private properties
            if ($property->isPrivate()) {
                continue;
            }

            $property->setAccessible(true);

            // Skip uninitialized properties
            if (! $property->isInitialized($event)) {
                continue;
            }

            $value = $property->getValue($event);

            // Handle Carbon instances
            if ($value instanceof Carbon) {
                $data[$property->getName()] = $value->toIso8601String();
            } elseif (is_object($value) && method_exists($value, 'toArray')) {
                // Handle DataObjects
                $data[$property->getName()] = $value->toArray();
            } else {
                $data[$property->getName()] = $value;
            }
        }

        return [
            'class' => get_class($event),
            'data'  => $data,
        ];
    }

    public function deserialize(array $serialized): object
    {
        $eventClass = $serialized['class'];
        $data = $serialized['data'];

        // Create instance without constructor
        $event = (new ReflectionClass($eventClass))->newInstanceWithoutConstructor();

        // Set properties directly
        foreach ($data as $property => $value) {
            if (property_exists($event, $property)) {
                $reflection = new ReflectionProperty($event, $property);
                $reflection->setAccessible(true);

                // Handle type hints
                $type = $reflection->getType();
                if ($type && $type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                    $typeName = $type->getName();

                    // Handle Carbon instances
                    if ($typeName === Carbon::class && $value !== null) {
                        $value = Carbon::parse($value);
                    } elseif (is_array($value) && function_exists('hydrate')) {
                        // Handle DataObject hydration
                        try {
                            /** @var class-string<\JustSteveKing\DataObjects\Contracts\DataObjectContract> $typeName */
                            $value = hydrate($typeName, $value);
                        } catch (Exception $e) {
                            // If hydration fails, try to create instance if it has fromArray method
                            if (method_exists($typeName, 'fromArray')) {
                                $value = $typeName::fromArray($value);
                            }
                        }
                    }
                }

                $reflection->setValue($event, $value);
            }
        }

        return $event;
    }

    /**
     * Create an event instance from array data.
     * This is a helper method for testing purposes.
     */
    public static function fromArray(string $className, array $data): object
    {
        // Create instance without constructor
        /** @var class-string $className */
        $reflection = new ReflectionClass($className);
        $instance = $reflection->newInstanceWithoutConstructor();

        // Set properties
        foreach ($data as $property => $value) {
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setAccessible(true);

                // Handle type hints
                $type = $prop->getType();
                if ($type && $type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                    $typeName = $type->getName();

                    // Handle Carbon instances
                    if ($typeName === Carbon::class && $value !== null) {
                        $value = Carbon::parse($value);
                    }
                }

                $prop->setValue($instance, $value);
            }
        }

        return $instance;
    }
}
