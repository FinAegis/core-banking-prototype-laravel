<?php

namespace Tests\Unit\Helpers;

use DateTime;
use Faker\Generator;
use PHPUnit\Framework\Attributes\Test;
use ReflectionFunction;
use Tests\TestCase;

class FakerHelperTest extends TestCase
{
    #[Test]
    public function test_faker_function_exists(): void
    {
        $this->assertTrue(function_exists('faker'));
    }

    #[Test]
    public function test_faker_returns_generator_instance(): void
    {
        $faker = faker();

        $this->assertInstanceOf(Generator::class, $faker);
    }

    #[Test]
    public function test_faker_returns_new_instance_each_time(): void
    {
        $faker1 = faker();
        $faker2 = faker();

        // Each call creates a new instance
        $this->assertNotSame($faker1, $faker2);
    }

    #[Test]
    public function test_faker_can_generate_data(): void
    {
        $faker = faker();

        // Test basic faker functionality
        $this->assertIsString($faker->name());
        $this->assertIsString($faker->email());
        $this->assertIsString($faker->sentence());
        $this->assertIsInt($faker->randomNumber());
        $this->assertIsBool($faker->boolean());
    }

    #[Test]
    public function test_faker_helper_file_structure(): void
    {
        $helperFile = base_path('app/Helpers/faker.php');

        $this->assertFileExists($helperFile);

        $content = file_get_contents($helperFile);

        // Check for proper imports
        $this->assertStringContainsString('use Faker\Factory;', $content);
        $this->assertStringContainsString('use Faker\Generator;', $content);

        // Check function definition
        $this->assertStringContainsString('if (! function_exists(\'faker\'))', $content);
        $this->assertStringContainsString('function faker(): Generator', $content);

        // Check implementation
        $this->assertStringContainsString('Factory::create()', $content);
    }

    #[Test]
    public function test_faker_has_proper_documentation(): void
    {
        $reflection = new ReflectionFunction('faker');
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse($docComment);
        $this->assertStringContainsString('A shorthand for faker factory', $docComment);
        $this->assertStringContainsString('@return Generator', $docComment);
    }

    #[Test]
    public function test_faker_function_return_type(): void
    {
        $reflection = new ReflectionFunction('faker');

        $this->assertTrue($reflection->hasReturnType());
        $returnType = $reflection->getReturnType();
        $this->assertEquals('Faker\Generator', $returnType->getName());
    }

    #[Test]
    public function test_faker_function_has_no_parameters(): void
    {
        $reflection = new ReflectionFunction('faker');

        $this->assertEquals(0, $reflection->getNumberOfParameters());
    }

    #[Test]
    public function test_faker_generates_consistent_types(): void
    {
        $faker = faker();

        // Test multiple calls return consistent types
        for ($i = 0; $i < 10; $i++) {
            $this->assertIsString($faker->word());
            $this->assertIsString($faker->sentence());
            $this->assertIsInt($faker->randomDigit());
            $this->assertIsFloat($faker->randomFloat());
            $this->assertIsArray($faker->words(3));
        }
    }

    #[Test]
    public function test_faker_email_generates_valid_format(): void
    {
        $faker = faker();

        for ($i = 0; $i < 10; $i++) {
            $email = $faker->email();
            $this->assertMatchesRegularExpression('/^.+@.+\..+$/', $email);
        }
    }

    #[Test]
    public function test_faker_uuid_generates_valid_format(): void
    {
        $faker = faker();

        $uuid = $faker->uuid();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    #[Test]
    public function test_faker_date_time_methods_work(): void
    {
        $faker = faker();

        $dateTime = $faker->dateTime();
        $this->assertInstanceOf(DateTime::class, $dateTime);

        $dateTimeThisYear = $faker->dateTimeThisYear();
        $this->assertInstanceOf(DateTime::class, $dateTimeThisYear);
        $this->assertEquals(date('Y'), $dateTimeThisYear->format('Y'));
    }

    #[Test]
    public function test_faker_random_element_works(): void
    {
        $faker = faker();

        $elements = ['apple', 'banana', 'orange'];
        $randomElement = $faker->randomElement($elements);

        $this->assertContains($randomElement, $elements);
    }

    #[Test]
    public function test_faker_number_between_works(): void
    {
        $faker = faker();

        for ($i = 0; $i < 20; $i++) {
            $number = $faker->numberBetween(10, 20);
            $this->assertGreaterThanOrEqual(10, $number);
            $this->assertLessThanOrEqual(20, $number);
        }
    }

    #[Test]
    public function test_faker_generates_locale_specific_data(): void
    {
        $faker = faker();

        // Test that faker can generate locale-specific data
        $name = $faker->name();
        $this->assertIsString($name);
        $this->assertNotEmpty($name);

        $address = $faker->address();
        $this->assertIsString($address);
        $this->assertNotEmpty($address);

        // Check that faker is working correctly
        $this->assertInstanceOf(Generator::class, $faker);
    }
}
