<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared\EventSourcing;

use App\Domain\Shared\EventSourcing\MessageSchemaRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class MessageSchemaRegistryTest extends TestCase
{
    private MessageSchemaRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('event-streaming.schema_validation.enabled', true);

        $this->registry = new MessageSchemaRegistry();
    }

    public function test_register_schema_stores_schema(): void
    {
        $schema = [
            'id'     => 'string',
            'amount' => 'float',
            'active' => 'bool',
        ];

        $this->registry->registerSchema('AccountCreated', $schema);

        $this->assertTrue($this->registry->hasSchema('AccountCreated'));
        $this->assertEquals($schema, $this->registry->getSchema('AccountCreated'));
    }

    public function test_register_schema_rejects_empty_event_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event type cannot be empty.');

        $this->registry->registerSchema('', ['id' => 'string']);
    }

    public function test_register_schema_rejects_empty_schema(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema cannot be empty.');

        $this->registry->registerSchema('AccountCreated', []);
    }

    public function test_register_schema_rejects_invalid_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid type 'invalid_type' for field 'data'");

        $this->registry->registerSchema('AccountCreated', [
            'id'   => 'string',
            'data' => 'invalid_type',
        ]);
    }

    public function test_register_schema_accepts_all_valid_types(): void
    {
        $schema = [
            'field_string'  => 'string',
            'field_int'     => 'int',
            'field_integer' => 'integer',
            'field_float'   => 'float',
            'field_double'  => 'double',
            'field_bool'    => 'bool',
            'field_boolean' => 'boolean',
            'field_array'   => 'array',
            'field_object'  => 'object',
            'field_null'    => 'null',
            'field_mixed'   => 'mixed',
        ];

        $this->registry->registerSchema('AllTypes', $schema);

        $this->assertTrue($this->registry->hasSchema('AllTypes'));
    }

    public function test_validate_message_passes_for_valid_message(): void
    {
        $this->registry->registerSchema('AccountCreated', [
            'id'     => 'string',
            'amount' => 'float',
            'active' => 'bool',
        ]);

        $result = $this->registry->validateMessage('AccountCreated', [
            'id'     => 'abc-123',
            'amount' => 100.50,
            'active' => true,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_message_fails_for_missing_fields(): void
    {
        $this->registry->registerSchema('AccountCreated', [
            'id'     => 'string',
            'amount' => 'float',
            'active' => 'bool',
        ]);

        $result = $this->registry->validateMessage('AccountCreated', [
            'id' => 'abc-123',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('Missing required field: amount', $result['errors'][0]);
        $this->assertStringContainsString('Missing required field: active', $result['errors'][1]);
    }

    public function test_validate_message_fails_for_wrong_types(): void
    {
        $this->registry->registerSchema('AccountCreated', [
            'id'     => 'string',
            'amount' => 'float',
        ]);

        $result = $this->registry->validateMessage('AccountCreated', [
            'id'     => 123,        // Should be string
            'amount' => 'not-a-number', // Should be float
        ]);

        $this->assertFalse($result['valid']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString("Field 'id' expected type 'string'", $result['errors'][0]);
        $this->assertStringContainsString("Field 'amount' expected type 'float'", $result['errors'][1]);
    }

    public function test_validate_message_fails_for_unregistered_event_type(): void
    {
        $result = $this->registry->validateMessage('UnknownEvent', ['id' => 'abc']);

        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('No schema registered for event type: UnknownEvent', $result['errors'][0]);
    }

    public function test_validate_message_skips_when_disabled(): void
    {
        config()->set('event-streaming.schema_validation.enabled', false);
        $registry = new MessageSchemaRegistry();

        $result = $registry->validateMessage('AnythingGoes', []);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_message_accepts_int_for_float_type(): void
    {
        $this->registry->registerSchema('Payment', [
            'amount' => 'float',
        ]);

        $result = $this->registry->validateMessage('Payment', [
            'amount' => 100, // int should be accepted for float
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_message_accepts_mixed_type(): void
    {
        $this->registry->registerSchema('FlexibleEvent', [
            'data' => 'mixed',
        ]);

        // All types should pass for mixed
        $this->assertTrue($this->registry->validateMessage('FlexibleEvent', ['data' => 'string'])['valid']);
        $this->assertTrue($this->registry->validateMessage('FlexibleEvent', ['data' => 123])['valid']);
        $this->assertTrue($this->registry->validateMessage('FlexibleEvent', ['data' => null])['valid']);
        $this->assertTrue($this->registry->validateMessage('FlexibleEvent', ['data' => ['array']])['valid']);
    }

    public function test_get_schema_returns_null_for_unregistered(): void
    {
        $this->assertNull($this->registry->getSchema('NonExistent'));
    }

    public function test_list_schemas_returns_all_registered(): void
    {
        $this->registry->registerSchema('Event1', ['id' => 'string']);
        $this->registry->registerSchema('Event2', ['name' => 'string', 'count' => 'int']);

        $schemas = $this->registry->listSchemas();

        $this->assertCount(2, $schemas);
        $this->assertArrayHasKey('Event1', $schemas);
        $this->assertArrayHasKey('Event2', $schemas);
    }

    public function test_list_schemas_returns_empty_when_none_registered(): void
    {
        $schemas = $this->registry->listSchemas();

        $this->assertEmpty($schemas);
    }

    public function test_has_schema_returns_false_for_unregistered(): void
    {
        $this->assertFalse($this->registry->hasSchema('NonExistent'));
    }

    public function test_remove_schema_removes_registration(): void
    {
        $this->registry->registerSchema('Event1', ['id' => 'string']);
        $this->assertTrue($this->registry->hasSchema('Event1'));

        $this->registry->removeSchema('Event1');

        $this->assertFalse($this->registry->hasSchema('Event1'));
        $this->assertNull($this->registry->getSchema('Event1'));
    }

    public function test_register_schema_overwrites_existing(): void
    {
        $this->registry->registerSchema('Event1', ['id' => 'string']);
        $this->registry->registerSchema('Event1', ['name' => 'string', 'count' => 'int']);

        $schema = $this->registry->getSchema('Event1');

        $this->assertCount(2, $schema);
        $this->assertArrayHasKey('name', $schema);
        $this->assertArrayHasKey('count', $schema);
        $this->assertArrayNotHasKey('id', $schema);
    }

    public function test_validate_message_checks_array_type(): void
    {
        $this->registry->registerSchema('BatchEvent', [
            'items' => 'array',
        ]);

        $validResult = $this->registry->validateMessage('BatchEvent', [
            'items' => ['a', 'b', 'c'],
        ]);
        $this->assertTrue($validResult['valid']);

        $invalidResult = $this->registry->validateMessage('BatchEvent', [
            'items' => 'not-an-array',
        ]);
        $this->assertFalse($invalidResult['valid']);
    }

    public function test_validate_message_checks_null_type(): void
    {
        $this->registry->registerSchema('NullableEvent', [
            'deleted_at' => 'null',
        ]);

        $validResult = $this->registry->validateMessage('NullableEvent', [
            'deleted_at' => null,
        ]);
        $this->assertTrue($validResult['valid']);

        $invalidResult = $this->registry->validateMessage('NullableEvent', [
            'deleted_at' => '2026-01-01',
        ]);
        $this->assertFalse($invalidResult['valid']);
    }

    public function test_validate_message_with_extra_fields_passes(): void
    {
        $this->registry->registerSchema('MinimalEvent', [
            'id' => 'string',
        ]);

        $result = $this->registry->validateMessage('MinimalEvent', [
            'id'          => 'abc-123',
            'extra_field' => 'extra_value',
            'another'     => 42,
        ]);

        // Extra fields should not cause validation failure
        $this->assertTrue($result['valid']);
    }

    public function test_remove_schema_does_not_fail_for_nonexistent(): void
    {
        // Should not throw
        $this->registry->removeSchema('NonExistent');

        $this->assertFalse($this->registry->hasSchema('NonExistent'));
    }
}
