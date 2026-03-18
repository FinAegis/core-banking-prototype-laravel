<?php

declare(strict_types=1);

namespace App\Domain\Shared\EventSourcing;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MessageSchemaRegistry
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $schemas = [];

    private readonly bool $validationEnabled;

    public function __construct()
    {
        $this->validationEnabled = (bool) config('event-streaming.schema_validation.enabled', true);
    }

    /**
     * Register a schema for an event type.
     *
     * @param  array<string, string>  $schema  Map of field names to types (e.g., ['id' => 'string', 'amount' => 'float'])
     */
    public function registerSchema(string $eventType, array $schema): void
    {
        if ($eventType === '') {
            throw new InvalidArgumentException('Event type cannot be empty.');
        }

        if ($schema === []) {
            throw new InvalidArgumentException('Schema cannot be empty.');
        }

        $allowedTypes = ['string', 'int', 'integer', 'float', 'double', 'bool', 'boolean', 'array', 'object', 'null', 'mixed'];

        foreach ($schema as $field => $type) {
            if (! in_array(strtolower($type), $allowedTypes, true)) {
                throw new InvalidArgumentException(
                    "Invalid type '{$type}' for field '{$field}'. Allowed types: " . implode(', ', $allowedTypes)
                );
            }
        }

        $this->schemas[$eventType] = $schema;

        Log::debug("Schema registered for event type: {$eventType}", [
            'fields' => array_keys($schema),
        ]);
    }

    /**
     * Validate a message against the registered schema for its event type.
     *
     * @param  array<string, mixed>  $message
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validateMessage(string $eventType, array $message): array
    {
        if (! $this->validationEnabled) {
            return ['valid' => true, 'errors' => []];
        }

        if (! isset($this->schemas[$eventType])) {
            return [
                'valid'  => false,
                'errors' => ["No schema registered for event type: {$eventType}"],
            ];
        }

        $schema = $this->schemas[$eventType];
        $errors = [];

        // Check required fields are present
        foreach ($schema as $field => $expectedType) {
            if (! array_key_exists($field, $message)) {
                $errors[] = "Missing required field: {$field}";

                continue;
            }

            $value = $message[$field];
            if (! $this->matchesType($value, $expectedType)) {
                $actualType = get_debug_type($value);
                $errors[] = "Field '{$field}' expected type '{$expectedType}', got '{$actualType}'";
            }
        }

        if ($errors !== []) {
            Log::warning("Schema validation failed for event type: {$eventType}", [
                'errors' => $errors,
            ]);
        }

        return [
            'valid'  => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Get the registered schema for an event type.
     *
     * @return array<string, string>|null
     */
    public function getSchema(string $eventType): ?array
    {
        return $this->schemas[$eventType] ?? null;
    }

    /**
     * List all registered schemas.
     *
     * @return array<string, array<string, string>>
     */
    public function listSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * Check if a schema is registered for an event type.
     */
    public function hasSchema(string $eventType): bool
    {
        return isset($this->schemas[$eventType]);
    }

    /**
     * Remove a schema registration.
     */
    public function removeSchema(string $eventType): void
    {
        unset($this->schemas[$eventType]);
    }

    /**
     * Check if a value matches the expected type.
     */
    private function matchesType(mixed $value, string $expectedType): bool
    {
        $normalizedType = strtolower($expectedType);

        if ($normalizedType === 'mixed') {
            return true;
        }

        return match ($normalizedType) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value) || is_int($value),
            'bool', 'boolean' => is_bool($value),
            'array'  => is_array($value),
            'object' => is_object($value),
            'null'   => $value === null,
            default  => false,
        };
    }
}
