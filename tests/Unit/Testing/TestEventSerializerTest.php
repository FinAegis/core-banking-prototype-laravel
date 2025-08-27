<?php

namespace Tests\Unit\Testing;

use App\Testing\ArrayEventSerializer;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;
use Tests\Unit\Testing\Support\EventWithCarbonDate;
use Tests\Unit\Testing\Support\EventWithPrivateProperties;
use Tests\Unit\Testing\Support\SimpleTestEvent;

class TestEventSerializerTest extends DomainTestCase
{
    private ArrayEventSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new ArrayEventSerializer();
    }

    #[Test]
    public function test_serialize_simple_event(): void
    {
        $event = new SimpleTestEvent();
        $event->name = 'Test Event';
        $event->value = 42;

        $serialized = $this->serializer->serialize($event);

        $this->assertIsArray($serialized);
        $this->assertEquals(SimpleTestEvent::class, $serialized['class']);
        $this->assertArrayHasKey('data', $serialized);
        $this->assertEquals('Test Event', $serialized['data']['name']);
        $this->assertEquals(42, $serialized['data']['value']);
    }

    #[Test]
    public function test_serialize_event_with_carbon_dates(): void
    {
        $event = new EventWithCarbonDate();
        $event->title = 'Date Event';
        $event->createdAt = Carbon::parse('2024-01-15 10:30:00');
        $event->updatedAt = Carbon::parse('2024-01-16 15:45:30');

        $serialized = $this->serializer->serialize($event);

        $this->assertEquals(EventWithCarbonDate::class, $serialized['class']);
        $this->assertEquals('Date Event', $serialized['data']['title']);
        $this->assertEquals('2024-01-15T10:30:00+00:00', $serialized['data']['createdAt']);
        $this->assertEquals('2024-01-16T15:45:30+00:00', $serialized['data']['updatedAt']);
    }

    #[Test]
    public function test_serialize_event_with_null_carbon_date(): void
    {
        $event = new EventWithCarbonDate();
        $event->title = 'Date Event';
        $event->createdAt = Carbon::now();
        $event->updatedAt = null;

        $serialized = $this->serializer->serialize($event);

        $this->assertArrayHasKey('updatedAt', $serialized['data']);
        $this->assertNull($serialized['data']['updatedAt']);
    }

    #[Test]
    public function test_serialize_ignores_private_properties(): void
    {
        $event = new EventWithPrivateProperties();
        $reflection = new ReflectionClass($event);
        $privateProperty = $reflection->getProperty('privateData');
        $privateProperty->setAccessible(true);
        $privateProperty->setValue($event, 'secret');

        $event->publicData = 'visible';

        $serialized = $this->serializer->serialize($event);

        $this->assertArrayHasKey('publicData', $serialized['data']);
        $this->assertEquals('visible', $serialized['data']['publicData']);
        $this->assertArrayNotHasKey('privateData', $serialized['data']);
    }

    #[Test]
    public function test_deserialize_simple_event(): void
    {
        $serialized = [
            'class' => SimpleTestEvent::class,
            'data'  => [
                'name'  => 'Deserialized Event',
                'value' => 99,
            ],
        ];

        $event = $this->serializer->deserialize($serialized);

        $this->assertInstanceOf(SimpleTestEvent::class, $event);
        $this->assertEquals('Deserialized Event', $event->name);
        $this->assertEquals(99, $event->value);
    }

    #[Test]
    public function test_deserialize_event_with_carbon_dates(): void
    {
        $serialized = [
            'class' => EventWithCarbonDate::class,
            'data'  => [
                'title'     => 'Restored Date Event',
                'createdAt' => '2024-02-20T14:30:00+00:00',
                'updatedAt' => '2024-02-21T09:15:00+00:00',
            ],
        ];

        $event = $this->serializer->deserialize($serialized);

        $this->assertInstanceOf(EventWithCarbonDate::class, $event);
        $this->assertEquals('Restored Date Event', $event->title);
        $this->assertInstanceOf(Carbon::class, $event->createdAt);
        $this->assertEquals('2024-02-20 14:30:00', $event->createdAt->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(Carbon::class, $event->updatedAt);
        $this->assertEquals('2024-02-21 09:15:00', $event->updatedAt->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function test_deserialize_handles_missing_properties(): void
    {
        $serialized = [
            'class' => SimpleTestEvent::class,
            'data'  => [
                'name'                => 'Partial Event',
                'nonExistentProperty' => 'ignored',
            ],
        ];

        $event = $this->serializer->deserialize($serialized);

        $this->assertInstanceOf(SimpleTestEvent::class, $event);
        $this->assertEquals('Partial Event', $event->name);
        // Value property should remain uninitialized (or default)
    }

    #[Test]
    public function test_deserialize_preserves_null_values(): void
    {
        $serialized = [
            'class' => EventWithCarbonDate::class,
            'data'  => [
                'title'     => 'Null Date Event',
                'createdAt' => '2024-03-01T00:00:00+00:00',
                'updatedAt' => null,
            ],
        ];

        $event = $this->serializer->deserialize($serialized);

        $this->assertInstanceOf(EventWithCarbonDate::class, $event);
        $this->assertEquals('Null Date Event', $event->title);
        $this->assertInstanceOf(Carbon::class, $event->createdAt);
        $this->assertNull($event->updatedAt);
    }

    #[Test]
    public function test_from_array_creates_event_from_data(): void
    {
        $data = [
            'name'  => 'Array Event',
            'value' => 123,
        ];

        $event = ArrayEventSerializer::fromArray(SimpleTestEvent::class, $data);

        $this->assertInstanceOf(SimpleTestEvent::class, $event);
        $this->assertEquals('Array Event', $event->name);
        $this->assertEquals(123, $event->value);
    }

    #[Test]
    public function test_from_array_handles_carbon_type_hints(): void
    {
        $data = [
            'title'     => 'Carbon Array Event',
            'createdAt' => '2024-04-10T12:00:00+00:00',
            'updatedAt' => '2024-04-11T13:30:00+00:00',
        ];

        $event = ArrayEventSerializer::fromArray(EventWithCarbonDate::class, $data);

        $this->assertInstanceOf(EventWithCarbonDate::class, $event);
        $this->assertEquals('Carbon Array Event', $event->title);
        $this->assertInstanceOf(Carbon::class, $event->createdAt);
        $this->assertInstanceOf(Carbon::class, $event->updatedAt);
    }

    #[Test]
    public function test_from_array_handles_non_typed_properties(): void
    {
        // Create a test class with non-typed property
        $className = 'TestEventWithNonTypedProperty_' . uniqid();
        eval("class $className { public \$untypedProperty; public string \$typedProperty; }");

        $data = [
            'untypedProperty' => 'any value',
            'typedProperty'   => 'string value',
        ];

        $event = ArrayEventSerializer::fromArray($className, $data);

        $this->assertEquals('any value', $event->untypedProperty);
        $this->assertEquals('string value', $event->typedProperty);
    }

    #[Test]
    public function test_serialize_deserialize_roundtrip(): void
    {
        // Create original event
        $original = new EventWithCarbonDate();
        $original->title = 'Roundtrip Test';
        $original->createdAt = Carbon::parse('2024-05-15 16:20:00');
        $original->updatedAt = Carbon::parse('2024-05-16 08:45:00');

        // Serialize and deserialize
        $serialized = $this->serializer->serialize($original);
        $restored = $this->serializer->deserialize($serialized);

        // Verify restoration
        $this->assertInstanceOf(EventWithCarbonDate::class, $restored);
        $this->assertEquals($original->title, $restored->title);
        $this->assertEquals(
            $original->createdAt->format('Y-m-d H:i:s'),
            $restored->createdAt->format('Y-m-d H:i:s')
        );
        $this->assertEquals(
            $original->updatedAt->format('Y-m-d H:i:s'),
            $restored->updatedAt->format('Y-m-d H:i:s')
        );
    }

    #[Test]
    public function test_handles_reflection_union_types(): void
    {
        // PHP 8+ union types (project requires PHP 8.3)
        $className = 'TestEventWithUnionType_' . uniqid();
        eval("class $className { public string|int \$unionProperty; public string \$normalProperty; }");

        $data = [
            'unionProperty'  => 'string value',
            'normalProperty' => 'normal',
        ];

        $event = ArrayEventSerializer::fromArray($className, $data);

        $this->assertEquals('string value', $event->unionProperty);
        $this->assertEquals('normal', $event->normalProperty);
    }
}
