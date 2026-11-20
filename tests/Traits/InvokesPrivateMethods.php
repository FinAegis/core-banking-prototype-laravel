<?php

declare(strict_types=1);

namespace Tests\Traits;

use ReflectionClass;
use ReflectionException;

/**
 * Trait for invoking private/protected methods in unit tests.
 *
 * Use this trait when you need to test private methods directly.
 * While testing through public interfaces is preferred, some complex
 * business logic (e.g., fraud detection algorithms, financial calculations)
 * may warrant direct private method testing.
 *
 * @example
 * ```php
 * class MyServiceTest extends TestCase
 * {
 *     use InvokesPrivateMethods;
 *
 *     public function test_private_calculation(): void
 *     {
 *         $result = $this->invokeMethod($this->service, 'calculateScore', [100, 0.5]);
 *         $this->assertEquals(50, $result);
 *     }
 * }
 * ```
 */
trait InvokesPrivateMethods
{
    /**
     * Invoke a private or protected method on an object.
     *
     * @param  object        $object      The object instance
     * @param  string        $methodName  The method name to invoke
     * @param  array<mixed>  $arguments   Arguments to pass to the method
     * @return mixed The method's return value
     *
     * @throws ReflectionException If method doesn't exist
     */
    protected function invokeMethod(object $object, string $methodName, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($object, $arguments);
    }

    /**
     * Get a private or protected property value from an object.
     *
     * @param  object  $object        The object instance
     * @param  string  $propertyName  The property name to access
     * @return mixed The property value
     *
     * @throws ReflectionException If property doesn't exist
     */
    protected function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);

        return $property->getValue($object);
    }

    /**
     * Set a private or protected property value on an object.
     *
     * @param  object  $object        The object instance
     * @param  string  $propertyName  The property name to set
     * @param  mixed   $value         The value to set
     *
     * @throws ReflectionException If property doesn't exist
     */
    protected function setPrivateProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setValue($object, $value);
    }
}
