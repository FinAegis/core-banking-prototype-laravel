<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use InvalidArgumentException;
use JsonException;

class JsonLDService
{
    private const SCHEMA_ORG_CONTEXT = 'https://schema.org';

    private const AP2_CONTEXT = 'https://ap2.protocol.org/v1/context';

    private const A2A_CONTEXT = 'https://a2a.protocol.org/v1/context';

    private array $contexts = [];

    private array $vocabularies = [];

    private array $typeDefinitions = [];

    public function __construct()
    {
        $this->initializeDefaultContexts();
        $this->loadSchemaOrgVocabulary();
    }

    public function serialize(array $data, array $context = []): string
    {
        try {
            $jsonLd = $this->buildJsonLd($data, $context);

            return json_encode($jsonLd, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to serialize JSON-LD: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deserialize(string $jsonLd): array
    {
        try {
            $data = json_decode($jsonLd, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($data)) {
                throw new InvalidArgumentException('Invalid JSON-LD format');
            }

            return $this->expandJsonLd($data);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Failed to deserialize JSON-LD: ' . $e->getMessage(), 0, $e);
        }
    }

    public function validate(array $data, ?string $type = null): array
    {
        $errors = [];

        if (! isset($data['@context'])) {
            $errors[] = 'Missing @context';
        }

        if ($type !== null) {
            if (! isset($data['@type'])) {
                $errors[] = 'Missing @type';
            } elseif ($data['@type'] !== $type && ! in_array($type, (array) $data['@type'], true)) {
                $errors[] = "Type mismatch: expected {$type}, got " . json_encode($data['@type']);
            }
        }

        if (isset($data['@type'])) {
            $typeErrors = $this->validateType($data['@type'], $data);
            $errors = array_merge($errors, $typeErrors);
        }

        return $errors;
    }

    public function buildAgentContext(string $agentId, array $capabilities = []): array
    {
        return [
            '@context' => [
                self::SCHEMA_ORG_CONTEXT,
                self::AP2_CONTEXT,
                self::A2A_CONTEXT,
                [
                    'agent'        => 'ap2:Agent',
                    'did'          => 'ap2:decentralizedIdentifier',
                    'capabilities' => 'ap2:capabilities',
                    'endpoints'    => 'ap2:endpoints',
                    'protocols'    => 'ap2:supportedProtocols',
                ],
            ],
            '@type'        => 'Agent',
            '@id'          => $agentId,
            'did'          => $agentId,
            'capabilities' => $capabilities,
        ];
    }

    public function buildPaymentContext(string $transactionId, array $paymentData): array
    {
        return [
            '@context' => [
                self::SCHEMA_ORG_CONTEXT,
                self::AP2_CONTEXT,
                [
                    'payment'  => 'schema:PaymentChargeSpecification',
                    'amount'   => 'schema:MonetaryAmount',
                    'currency' => 'schema:currency',
                    'sender'   => 'ap2:senderAgent',
                    'receiver' => 'ap2:receiverAgent',
                    'escrow'   => 'ap2:escrowService',
                ],
            ],
            '@type'  => 'PaymentChargeSpecification',
            '@id'    => $transactionId,
            'amount' => [
                '@type'    => 'MonetaryAmount',
                'value'    => $paymentData['amount'] ?? 0,
                'currency' => $paymentData['currency'] ?? 'USD',
            ],
            'sender'        => $paymentData['sender'] ?? null,
            'receiver'      => $paymentData['receiver'] ?? null,
            'paymentStatus' => $paymentData['status'] ?? 'pending',
            'paymentMethod' => $paymentData['method'] ?? 'agent-wallet',
        ];
    }

    public function buildCapabilityContext(string $capabilityId, array $capabilityData): array
    {
        return [
            '@context' => [
                self::SCHEMA_ORG_CONTEXT,
                self::AP2_CONTEXT,
                [
                    'capability'  => 'ap2:Capability',
                    'version'     => 'schema:version',
                    'endpoints'   => 'ap2:endpoints',
                    'parameters'  => 'ap2:parameters',
                    'permissions' => 'ap2:requiredPermissions',
                ],
            ],
            '@type'       => 'Capability',
            '@id'         => $capabilityId,
            'name'        => $capabilityData['name'] ?? '',
            'description' => $capabilityData['description'] ?? '',
            'version'     => $capabilityData['version'] ?? '1.0.0',
            'endpoints'   => $capabilityData['endpoints'] ?? [],
            'parameters'  => $capabilityData['parameters'] ?? [],
        ];
    }

    public function negotiateContent(array $acceptHeaders): string
    {
        $supportedTypes = [
            'application/ld+json'      => 1.0,
            'application/json'         => 0.9,
            'application/vnd.ap2+json' => 0.8,
            'application/vnd.a2a+json' => 0.8,
        ];

        $bestMatch = 'application/ld+json';
        $bestQuality = 0.0;

        foreach ($acceptHeaders as $header) {
            $parts = explode(',', $header);
            foreach ($parts as $part) {
                $mediaType = trim(explode(';', $part)[0]);
                if (isset($supportedTypes[$mediaType])) {
                    $quality = $this->parseQuality($part) * $supportedTypes[$mediaType];
                    if ($quality > $bestQuality) {
                        $bestQuality = $quality;
                        $bestMatch = $mediaType;
                    }
                }
            }
        }

        return $bestMatch;
    }

    public function addContext(string $name, string $url): void
    {
        $this->contexts[$name] = $url;
    }

    public function addVocabulary(string $prefix, array $terms): void
    {
        $this->vocabularies[$prefix] = $terms;
    }

    public function addTypeDefinition(string $type, array $properties): void
    {
        $this->typeDefinitions[$type] = $properties;
    }

    public function expandTerm(string $term): string
    {
        if (str_contains($term, ':')) {
            [$prefix, $localName] = explode(':', $term, 2);
            if (isset($this->vocabularies[$prefix][$localName])) {
                return $this->vocabularies[$prefix][$localName];
            }
            if (isset($this->contexts[$prefix])) {
                return $this->contexts[$prefix] . '/' . $localName;
            }
        }

        foreach ($this->vocabularies as $vocab) {
            if (isset($vocab[$term])) {
                return $vocab[$term];
            }
        }

        return $term;
    }

    public function compactIri(string $iri): string
    {
        foreach ($this->contexts as $prefix => $context) {
            if (str_starts_with($iri, $context)) {
                $localName = substr($iri, strlen($context) + 1);

                return "{$prefix}:{$localName}";
            }
        }

        foreach ($this->vocabularies as $prefix => $terms) {
            $flipped = array_flip($terms);
            if (isset($flipped[$iri])) {
                return "{$prefix}:{$flipped[$iri]}";
            }
        }

        return $iri;
    }

    private function initializeDefaultContexts(): void
    {
        $this->contexts = [
            'schema' => self::SCHEMA_ORG_CONTEXT,
            'ap2'    => self::AP2_CONTEXT,
            'a2a'    => self::A2A_CONTEXT,
            'rdf'    => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs'   => 'http://www.w3.org/2000/01/rdf-schema#',
            'xsd'    => 'http://www.w3.org/2001/XMLSchema#',
        ];
    }

    private function loadSchemaOrgVocabulary(): void
    {
        $this->vocabularies['schema'] = [
            'Thing'                      => 'https://schema.org/Thing',
            'Person'                     => 'https://schema.org/Person',
            'Organization'               => 'https://schema.org/Organization',
            'PaymentChargeSpecification' => 'https://schema.org/PaymentChargeSpecification',
            'MonetaryAmount'             => 'https://schema.org/MonetaryAmount',
            'Invoice'                    => 'https://schema.org/Invoice',
            'Order'                      => 'https://schema.org/Order',
            'Service'                    => 'https://schema.org/Service',
            'Product'                    => 'https://schema.org/Product',
            'name'                       => 'https://schema.org/name',
            'description'                => 'https://schema.org/description',
            'identifier'                 => 'https://schema.org/identifier',
            'url'                        => 'https://schema.org/url',
            'version'                    => 'https://schema.org/version',
            'dateCreated'                => 'https://schema.org/dateCreated',
            'dateModified'               => 'https://schema.org/dateModified',
            'currency'                   => 'https://schema.org/currency',
            'price'                      => 'https://schema.org/price',
            'paymentStatus'              => 'https://schema.org/paymentStatus',
            'paymentMethod'              => 'https://schema.org/paymentMethod',
        ];

        $this->vocabularies['ap2'] = [
            'Agent'                   => 'https://ap2.protocol.org/v1/Agent',
            'Capability'              => 'https://ap2.protocol.org/v1/Capability',
            'Transaction'             => 'https://ap2.protocol.org/v1/Transaction',
            'Escrow'                  => 'https://ap2.protocol.org/v1/Escrow',
            'decentralizedIdentifier' => 'https://ap2.protocol.org/v1/did',
            'capabilities'            => 'https://ap2.protocol.org/v1/capabilities',
            'endpoints'               => 'https://ap2.protocol.org/v1/endpoints',
            'supportedProtocols'      => 'https://ap2.protocol.org/v1/protocols',
            'senderAgent'             => 'https://ap2.protocol.org/v1/senderAgent',
            'receiverAgent'           => 'https://ap2.protocol.org/v1/receiverAgent',
            'escrowService'           => 'https://ap2.protocol.org/v1/escrowService',
        ];

        $this->vocabularies['a2a'] = [
            'Message'         => 'https://a2a.protocol.org/v1/Message',
            'Protocol'        => 'https://a2a.protocol.org/v1/Protocol',
            'Session'         => 'https://a2a.protocol.org/v1/Session',
            'messageType'     => 'https://a2a.protocol.org/v1/messageType',
            'protocolVersion' => 'https://a2a.protocol.org/v1/protocolVersion',
            'sessionId'       => 'https://a2a.protocol.org/v1/sessionId',
        ];
    }

    private function buildJsonLd(array $data, array $context): array
    {
        $jsonLd = [];

        if (! empty($context)) {
            $jsonLd['@context'] = $context;
        } elseif (! isset($data['@context'])) {
            $jsonLd['@context'] = [self::SCHEMA_ORG_CONTEXT];
        }

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '@')) {
                $jsonLd[$key] = $value;
            } else {
                $expandedKey = $this->expandTerm($key);
                $jsonLd[$expandedKey] = $this->processValue($value);
            }
        }

        return $jsonLd;
    }

    private function expandJsonLd(array $data): array
    {
        $expanded = [];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '@')) {
                $expanded[$key] = $value;
            } else {
                $expandedKey = $this->expandTerm($key);
                $expanded[$expandedKey] = is_array($value) && ! array_is_list($value)
                    ? $this->expandJsonLd($value)
                    : $value;
            }
        }

        return $expanded;
    }

    private function processValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map([$this, 'processValue'], $value);
            }

            return $this->buildJsonLd($value, []);
        }

        if (is_string($value) && $this->isIri($value)) {
            return ['@id' => $value];
        }

        return $value;
    }

    private function isIri(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            || str_starts_with($value, 'did:')
            || str_starts_with($value, 'urn:');
    }

    private function validateType(string|array $types, array $data): array
    {
        $errors = [];
        $types = (array) $types;

        foreach ($types as $type) {
            if (! isset($this->typeDefinitions[$type])) {
                continue;
            }

            $definition = $this->typeDefinitions[$type];
            foreach ($definition['required'] ?? [] as $requiredProperty) {
                if (! isset($data[$requiredProperty])) {
                    $errors[] = "Missing required property '{$requiredProperty}' for type '{$type}'";
                }
            }

            foreach ($definition['properties'] ?? [] as $property => $propertyDef) {
                if (isset($data[$property]) && isset($propertyDef['type'])) {
                    $actualType = gettype($data[$property]);
                    $expectedType = $propertyDef['type'];
                    if ($actualType !== $expectedType && ! $this->isTypeCompatible($actualType, $expectedType)) {
                        $errors[] = "Property '{$property}' type mismatch: expected {$expectedType}, got {$actualType}";
                    }
                }
            }
        }

        return $errors;
    }

    private function isTypeCompatible(string $actual, string $expected): bool
    {
        $compatibilityMap = [
            'integer' => ['double', 'string'],
            'double'  => ['integer', 'string'],
            'string'  => ['integer', 'double', 'boolean'],
        ];

        return in_array($actual, $compatibilityMap[$expected] ?? [], true);
    }

    private function parseQuality(string $mediaRange): float
    {
        if (preg_match('/;\s*q\s*=\s*([0-9.]+)/', $mediaRange, $matches)) {
            return min(1.0, max(0.0, (float) $matches[1]));
        }

        return 1.0;
    }
}
