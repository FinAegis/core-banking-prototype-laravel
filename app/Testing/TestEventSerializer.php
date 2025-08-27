<?php

namespace App\Testing;

use Carbon\Carbon;
use DateTime;
use DateTimeImmutable;
use Exception;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Spatie\EventSourcing\EventSerializers\EventSerializer;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TestEventSerializer implements EventSerializer
{
    public function serialize(ShouldBeStored $event): string
    {
        $properties = [];
        $reflection = new ReflectionClass($event);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);

            // Skip uninitialized properties
            if (! $property->isInitialized($event)) {
                continue;
            }

            $value = $property->getValue($event);

            // Handle Carbon and DateTimeImmutable instances
            if ($value instanceof Carbon) {
                $properties[$property->getName()] = $value->toIso8601String();
            } elseif ($value instanceof DateTimeImmutable || $value instanceof DateTime) {
                $properties[$property->getName()] = $value->format('c');
            } elseif (is_object($value) && method_exists($value, 'toArray')) {
                // Handle DataObjects
                $properties[$property->getName()] = $value->toArray();
            } else {
                $properties[$property->getName()] = $value;
            }
        }

        return json_encode($properties);
    }

    public function deserialize(
        string $eventClass,
        string $json,
        int $version,
        ?string $metadata = null
    ): ShouldBeStored {
        $data = json_decode($json, true);

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

                    // Handle Carbon and DateTimeImmutable instances
                    if ($typeName === Carbon::class) {
                        $value = Carbon::parse($value);
                    } elseif ($typeName === DateTimeImmutable::class) {
                        $value = new DateTimeImmutable($value);
                    } elseif ($typeName === DateTime::class) {
                        $value = new DateTime($value);
                    } elseif (is_array($value) && function_exists('hydrate')) {
                        // Handle DataObject hydration
                        try {
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
                $prop->setValue($instance, $value);
            }
        }

        return $instance;
    }
}
