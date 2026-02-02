<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Privacy\ValueObjects;

use App\Domain\Privacy\ValueObjects\SrsCircuit;
use JsonSerializable;
use ReflectionClass;
use Tests\TestCase;

/**
 * Tests for SrsCircuit value object.
 */
class SrsCircuitTest extends TestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        $circuit = new SrsCircuit(
            name: 'test_circuit',
            version: '1.0.0',
            size: 1000000,
            required: true,
            downloadUrl: 'https://cdn.example.com/srs/1.0.0/test_circuit.srs',
            checksum: 'abc123',
            checksumAlgorithm: 'sha256'
        );

        $this->assertEquals('test_circuit', $circuit->name);
        $this->assertEquals('1.0.0', $circuit->version);
        $this->assertEquals(1000000, $circuit->size);
        $this->assertTrue($circuit->required);
        $this->assertEquals('https://cdn.example.com/srs/1.0.0/test_circuit.srs', $circuit->downloadUrl);
        $this->assertEquals('abc123', $circuit->checksum);
        $this->assertEquals('sha256', $circuit->checksumAlgorithm);
    }

    public function test_constructor_uses_default_checksum_algorithm(): void
    {
        $circuit = new SrsCircuit(
            name: 'test_circuit',
            version: '1.0.0',
            size: 1000000,
            required: true,
            downloadUrl: 'https://cdn.example.com/srs/1.0.0/test_circuit.srs',
            checksum: 'abc123'
        );

        $this->assertEquals('sha256', $circuit->checksumAlgorithm);
    }

    public function test_from_config_creates_circuit(): void
    {
        $config = [
            'size'     => 15000000,
            'required' => true,
        ];

        $circuit = SrsCircuit::fromConfig(
            name: 'shield_1_1',
            config: $config,
            cdnBaseUrl: 'https://cdn.example.com/srs',
            version: '1.0.0'
        );

        $this->assertEquals('shield_1_1', $circuit->name);
        $this->assertEquals('1.0.0', $circuit->version);
        $this->assertEquals(15000000, $circuit->size);
        $this->assertTrue($circuit->required);
        $this->assertEquals('https://cdn.example.com/srs/1.0.0/shield_1_1.srs', $circuit->downloadUrl);
    }

    public function test_from_config_uses_provided_checksum(): void
    {
        $config = [
            'size'     => 1000,
            'required' => false,
            'checksum' => 'custom_checksum_value',
        ];

        $circuit = SrsCircuit::fromConfig('test', $config, 'https://cdn.example.com', '1.0.0');

        $this->assertEquals('custom_checksum_value', $circuit->checksum);
    }

    public function test_from_config_generates_checksum_when_not_provided(): void
    {
        $config = [
            'size'     => 1000,
            'required' => false,
        ];

        $circuit = SrsCircuit::fromConfig('test', $config, 'https://cdn.example.com', '1.0.0');

        // Should generate checksum from name and version
        $expectedChecksum = hash('sha256', 'test_1.0.0');
        $this->assertEquals($expectedChecksum, $circuit->checksum);
    }

    public function test_get_human_readable_size_for_bytes(): void
    {
        $circuit = new SrsCircuit(
            name: 'small',
            version: '1.0.0',
            size: 500,
            required: false,
            downloadUrl: 'https://example.com/small.srs',
            checksum: 'abc'
        );

        $this->assertEquals('500 B', $circuit->getHumanReadableSize());
    }

    public function test_get_human_readable_size_for_kilobytes(): void
    {
        $circuit = new SrsCircuit(
            name: 'medium',
            version: '1.0.0',
            size: 2048,
            required: false,
            downloadUrl: 'https://example.com/medium.srs',
            checksum: 'abc'
        );

        $this->assertEquals('2 KB', $circuit->getHumanReadableSize());
    }

    public function test_get_human_readable_size_for_megabytes(): void
    {
        $circuit = new SrsCircuit(
            name: 'large',
            version: '1.0.0',
            size: 15000000,
            required: false,
            downloadUrl: 'https://example.com/large.srs',
            checksum: 'abc'
        );

        $humanSize = $circuit->getHumanReadableSize();

        $this->assertStringContainsString('MB', $humanSize);
        $this->assertStringContainsString('14', $humanSize);
    }

    public function test_get_human_readable_size_for_gigabytes(): void
    {
        $circuit = new SrsCircuit(
            name: 'huge',
            version: '1.0.0',
            size: 2147483648, // 2 GB
            required: false,
            downloadUrl: 'https://example.com/huge.srs',
            checksum: 'abc'
        );

        $this->assertEquals('2 GB', $circuit->getHumanReadableSize());
    }

    public function test_implements_json_serializable(): void
    {
        $circuit = new SrsCircuit(
            name: 'test',
            version: '1.0.0',
            size: 1000,
            required: true,
            downloadUrl: 'https://example.com/test.srs',
            checksum: 'abc'
        );

        $this->assertInstanceOf(JsonSerializable::class, $circuit);
    }

    public function test_json_serialize_returns_expected_structure(): void
    {
        $circuit = new SrsCircuit(
            name: 'test_circuit',
            version: '1.0.0',
            size: 1000000,
            required: true,
            downloadUrl: 'https://cdn.example.com/test.srs',
            checksum: 'abc123',
            checksumAlgorithm: 'sha256'
        );

        $json = $circuit->jsonSerialize();

        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('version', $json);
        $this->assertArrayHasKey('size', $json);
        $this->assertArrayHasKey('size_human', $json);
        $this->assertArrayHasKey('required', $json);
        $this->assertArrayHasKey('download_url', $json);
        $this->assertArrayHasKey('checksum', $json);
        $this->assertArrayHasKey('checksum_algorithm', $json);

        $this->assertEquals('test_circuit', $json['name']);
        $this->assertEquals('1.0.0', $json['version']);
        $this->assertEquals(1000000, $json['size']);
        $this->assertTrue($json['required']);
        $this->assertEquals('https://cdn.example.com/test.srs', $json['download_url']);
        $this->assertEquals('abc123', $json['checksum']);
        $this->assertEquals('sha256', $json['checksum_algorithm']);
    }

    public function test_to_array_returns_same_as_json_serialize(): void
    {
        $circuit = new SrsCircuit(
            name: 'test',
            version: '1.0.0',
            size: 5000,
            required: false,
            downloadUrl: 'https://example.com/test.srs',
            checksum: 'xyz789'
        );

        $this->assertEquals($circuit->jsonSerialize(), $circuit->toArray());
    }

    public function test_circuit_is_readonly(): void
    {
        $reflection = new ReflectionClass(SrsCircuit::class);

        $this->assertTrue($reflection->isReadOnly());
    }

    public function test_json_encode_produces_valid_json(): void
    {
        $circuit = new SrsCircuit(
            name: 'shield_1_1',
            version: '2.0.0',
            size: 15000000,
            required: true,
            downloadUrl: 'https://cdn.finaegis.com/srs/2.0.0/shield_1_1.srs',
            checksum: 'deadbeef'
        );

        $json = json_encode($circuit);

        $this->assertNotFalse($json);
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals('shield_1_1', $decoded['name']);
        $this->assertEquals('2.0.0', $decoded['version']);
    }
}
