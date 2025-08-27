<?php

namespace Tests\Unit\Domain\Stablecoin\Repositories;

use App\Domain\Stablecoin\Models\StablecoinEvent;
use App\Domain\Stablecoin\Repositories\StablecoinEventRepository;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;
use Tests\DomainTestCase;

class StablecoinEventRepositoryTest extends DomainTestCase
{
    #[Test]
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(StablecoinEventRepository::class));
    }

    #[Test]
    public function test_extends_eloquent_stored_event_repository(): void
    {
        $repository = new StablecoinEventRepository();
        $this->assertInstanceOf(EloquentStoredEventRepository::class, $repository);
    }

    #[Test]
    public function test_is_final_class(): void
    {
        $reflection = new ReflectionClass(StablecoinEventRepository::class);
        $this->assertTrue($reflection->isFinal());
    }

    #[Test]
    public function test_constructor_has_correct_signature(): void
    {
        $reflection = new ReflectionClass(StablecoinEventRepository::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertEquals(1, $constructor->getNumberOfParameters());

        $parameter = $constructor->getParameters()[0];
        $this->assertEquals('storedEventModel', $parameter->getName());
        $this->assertEquals('string', $parameter->getType()->getName());
        $this->assertTrue($parameter->isDefaultValueAvailable());
        $this->assertEquals(StablecoinEvent::class, $parameter->getDefaultValue());
    }

    #[Test]
    public function test_constructor_property_is_protected(): void
    {
        $reflection = new ReflectionClass(StablecoinEventRepository::class);
        $property = $reflection->getProperty('storedEventModel');

        $this->assertTrue($property->isProtected());
        $this->assertEquals('string', $property->getType()->getName());
    }

    #[Test]
    public function test_uses_stablecoin_event_model(): void
    {
        $reflection = new ReflectionClass(StablecoinEventRepository::class);

        // Check that the class imports StablecoinEvent
        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        $this->assertStringContainsString('use App\Domain\Stablecoin\Models\StablecoinEvent;', $fileContent);
        $this->assertStringContainsString('= StablecoinEvent::class', $fileContent);
    }

    #[Test]
    public function test_constructor_validates_model(): void
    {
        // Test that constructor validates the model extends EloquentStoredEvent
        $reflection = new ReflectionClass(StablecoinEventRepository::class);
        $constructor = $reflection->getConstructor();

        $fileName = $reflection->getFileName();
        $startLine = $constructor->getStartLine();
        $endLine = $constructor->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('instanceof EloquentStoredEvent', $source);
        $this->assertStringContainsString('throw new InvalidEloquentStoredEventModel', $source);
    }

    #[Test]
    public function test_exception_message_format(): void
    {
        $reflection = new ReflectionClass(StablecoinEventRepository::class);
        $constructor = $reflection->getConstructor();

        $fileName = $reflection->getFileName();
        $startLine = $constructor->getStartLine();
        $endLine = $constructor->getEndLine();
        $source = implode('', array_slice(file($fileName), $startLine - 1, $endLine - $startLine + 1));

        // Check exception message format
        $this->assertStringContainsString('"The class {$this->storedEventModel} must extend EloquentStoredEvent"', $source);
    }

    #[Test]
    public function test_has_phpdoc_annotations(): void
    {
        $reflection = new ReflectionClass(StablecoinEventRepository::class);
        $constructor = $reflection->getConstructor();

        $docComment = $constructor->getDocComment();
        $this->assertNotFalse($docComment);

        // Check PHPDoc annotations
        $this->assertStringContainsString('@param string $storedEventModel', $docComment);
        $this->assertStringContainsString('@throws InvalidEloquentStoredEventModel', $docComment);
    }

    #[Test]
    public function test_imports_correct_exceptions(): void
    {
        $reflection = new ReflectionClass(StablecoinEventRepository::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        $this->assertStringContainsString('use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;', $fileContent);
    }

    #[Test]
    public function test_imports_correct_base_classes(): void
    {
        $reflection = new ReflectionClass(StablecoinEventRepository::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        $this->assertStringContainsString('use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;', $fileContent);
        $this->assertStringContainsString('use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;', $fileContent);
    }

    #[Test]
    public function test_namespace_is_correct(): void
    {
        $reflection = new ReflectionClass(StablecoinEventRepository::class);
        $this->assertEquals('App\Domain\Stablecoin\Repositories', $reflection->getNamespaceName());
    }

    #[Test]
    public function test_class_structure(): void
    {
        $repository = new StablecoinEventRepository();

        // Test that it inherits all necessary methods from parent
        $this->assertTrue(method_exists($repository, 'find'));
        $this->assertTrue(method_exists($repository, 'persist'));
        $this->assertTrue(method_exists($repository, 'persistMany'));
        $this->assertTrue(method_exists($repository, 'update'));
    }

    #[Test]
    public function test_instantiation_with_default_model(): void
    {
        $repository = new StablecoinEventRepository();

        // Repository should instantiate without errors when using default model
        $this->assertInstanceOf(StablecoinEventRepository::class, $repository);
    }

    #[Test]
    public function test_uses_strict_types(): void
    {
        $reflection = new ReflectionClass(StablecoinEventRepository::class);

        $fileName = $reflection->getFileName();
        $fileContent = file_get_contents($fileName);

        // Check for strict types declaration
        $this->assertStringContainsString('declare(strict_types=1);', $fileContent);
    }
}
