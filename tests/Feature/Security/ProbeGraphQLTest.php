<?php

declare(strict_types=1);

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use Illuminate\Support\Facades\Cache;
use Nuwave\Lighthouse\Schema\SchemaBuilder;

/*
| POST /graphql answered an outsider in production. Introspection is now off
| by default in production, and every root Query and Mutation field must
| refuse a request without a signed-in user, except the public fields below.
*/

/**
 * Root fields that answer without a signed-in user, and why.
 *
 * @var array<string, string>
 */
const PGQ_PUBLIC_FIELDS = [
    'Query.x402ProtocolStatus'    => 'Same public protocol status as GET /api/v1/x402/status: whether x402 is on, its version and networks.',
    'Query.x402SupportedNetworks' => 'Same public network list as GET /api/v1/x402/supported, used by payers before they sign in.',
    'Query.smsInfo'               => 'Same public SMS service status as GET /api/v1/sms/info: provider name, networks and whether SMS is on.',
];

beforeEach(function (): void {
    Cache::flush();
    // One request per root field: lift the guest rate limit for the walk.
    config(['cache.default' => 'array', 'lighthouse.rate_limiting.guest_limit' => 100000]);
});

/**
 * Evaluate config/lighthouse.php with the given environment variables.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function pgqLighthouseConfigUnderEnv(array $env): array
{
    $saved = [];
    foreach ($env as $key => $value) {
        $saved[$key] = [array_key_exists($key, $_SERVER), $_SERVER[$key] ?? null, array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
        if ($value === null) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        } else {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    try {
        /** @var array<string, mixed> $config */
        $config = require base_path('config/lighthouse.php');

        return $config;
    } finally {
        foreach ($saved as $key => [$hasServer, $server, $hasEnv, $envValue, $getenv]) {
            if ($hasServer) {
                $_SERVER[$key] = $server;
            } else {
                unset($_SERVER[$key]);
            }
            if ($hasEnv) {
                $_ENV[$key] = $envValue;
            } else {
                unset($_ENV[$key]);
            }
            if ($getenv === false) {
                putenv($key);
            } else {
                putenv("{$key}={$getenv}");
            }
        }
    }
}

/**
 * A literal for an argument of the given type: required input fields only.
 */
function pgqLiteral(Type $type, int $depth = 0): string
{
    if ($type instanceof NonNull) {
        return pgqLiteral($type->getWrappedType(), $depth);
    }
    if ($type instanceof ListOfType) {
        return '[' . pgqLiteral($type->getWrappedType(), $depth) . ']';
    }
    if ($type instanceof EnumType) {
        $values = $type->getValues();

        return $values === [] ? 'null' : $values[0]->name;
    }
    if ($type instanceof InputObjectType) {
        if ($depth > 4) {
            return '{}';
        }
        $fields = [];
        foreach ($type->getFields() as $field) {
            if ($field->getType() instanceof NonNull && ! $field->defaultValueExists()) {
                $fields[] = $field->name . ': ' . pgqLiteral($field->getType(), $depth + 1);
            }
        }

        return '{' . implode(', ', $fields) . '}';
    }
    if ($type instanceof ScalarType) {
        return match ($type->name) {
            'Int'      => '1',
            'Float'    => '1.5',
            'Boolean'  => 'false',
            'ID'       => '"1"',
            'Date'     => '"2026-01-01"',
            'DateTime' => '"2026-01-01 00:00:00"',
            default    => '"probe"',
        };
    }

    return 'null';
}

/**
 * The GraphQL document that calls one root field with dummy arguments.
 */
function pgqDocument(string $operation, string $fieldName, GraphQL\Type\Definition\FieldDefinition $field): string
{
    $arguments = [];
    foreach ($field->args as $argument) {
        if ($argument->getType() instanceof NonNull && ! $argument->defaultValueExists()) {
            $arguments[] = $argument->name . ': ' . pgqLiteral($argument->getType());
        }
    }

    $named = Type::getNamedType($field->getType());
    $selection = $named instanceof ObjectType || $named instanceof InterfaceType || $named instanceof UnionType
        ? ' { __typename }'
        : '';

    return sprintf('%s { %s%s%s }', $operation, $fieldName, $arguments === [] ? '' : '(' . implode(', ', $arguments) . ')', $selection);
}

/**
 * @return array<string, GraphQL\Type\Definition\FieldDefinition>
 */
function pgqRootFields(): array
{
    $schema = app(SchemaBuilder::class)->schema();
    $fields = [];
    foreach (['Query', 'Mutation'] as $root) {
        $type = $schema->getType($root);
        if (! $type instanceof ObjectType) {
            continue;
        }
        foreach ($type->getFields() as $name => $field) {
            $fields["{$root}.{$name}"] = $field;
        }
    }

    return $fields;
}

describe('introspection', function (): void {
    it('is off by default in production and on elsewhere, and the env var wins', function (): void {
        $disabled = GraphQL\Validator\Rules\DisableIntrospection::ENABLED;
        $enabled = GraphQL\Validator\Rules\DisableIntrospection::DISABLED;

        foreach (['production', 'Production', null] as $appEnv) {
            $config = pgqLighthouseConfigUnderEnv(['APP_ENV' => $appEnv, 'LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION' => null]);
            expect($config['security']['disable_introspection'])->toBe($disabled);
        }

        foreach (['local', 'testing', 'demo'] as $appEnv) {
            $config = pgqLighthouseConfigUnderEnv(['APP_ENV' => $appEnv, 'LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION' => null]);
            expect($config['security']['disable_introspection'])->toBe($enabled);
        }

        $config = pgqLighthouseConfigUnderEnv(['APP_ENV' => 'production', 'LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION' => 'false']);
        expect($config['security']['disable_introspection'])->toBe($enabled);

        $config = pgqLighthouseConfigUnderEnv(['APP_ENV' => 'local', 'LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION' => 'true']);
        expect($config['security']['disable_introspection'])->toBe($disabled);
    });

    it('refuses an introspection query while disabled', function (): void {
        config(['lighthouse.security.disable_introspection' => GraphQL\Validator\Rules\DisableIntrospection::ENABLED]);

        $response = $this->postJson('/graphql', ['query' => '{ __schema { queryType { name } } }']);

        expect($response->json('data'))->toBeNull()
            ->and((string) $response->json('errors.0.message'))->toContain('introspection');
    });
});

describe('root fields without a signed-in user', function (): void {
    it('puts @guard on every root Query and Mutation field except the listed public ones', function (): void {
        $unguarded = [];
        foreach (pgqRootFields() as $key => $field) {
            $directives = array_map(
                static fn (GraphQL\Language\AST\DirectiveNode $directive): string => $directive->name->value,
                iterator_to_array($field->astNode->directives ?? []),
            );
            if (! in_array('guard', $directives, true)) {
                $unguarded[] = $key;
            }
        }

        expect($unguarded)->toEqualCanonicalizing(array_keys(PGQ_PUBLIC_FIELDS));
    });

    it('refuses every root Query and Mutation field except the listed public ones', function (): void {
        $fields = pgqRootFields();
        expect(count($fields))->toBeGreaterThan(100);

        $answered = [];
        $notRefused = [];

        foreach ($fields as $key => $field) {
            if (array_key_exists($key, PGQ_PUBLIC_FIELDS)) {
                continue;
            }

            [$root, $name] = explode('.', $key, 2);
            $document = pgqDocument($root === 'Query' ? 'query' : 'mutation', $name, $field);

            $response = $this->postJson('/graphql', ['query' => $document]);
            $json = (array) $response->json();
            $data = is_array($json['data'] ?? null) ? ($json['data'][$name] ?? null) : null;

            if ($data !== null) {
                $answered[] = $key;

                continue;
            }

            // The refusal is "Unauthenticated." from @guard. Argument
            // validation runs before @guard, so a field whose dummy
            // arguments fail validation answers with that error instead; the
            // test above makes sure it carries @guard.
            $messages = array_map(
                static fn (mixed $error): string => is_array($error) ? (string) ($error['message'] ?? '') : '',
                (array) ($json['errors'] ?? []),
            );
            $refused = in_array('Unauthenticated.', $messages, true)
                || in_array("Validation failed for the field [{$name}].", $messages, true);
            if (! $refused) {
                $notRefused[$key] = implode(' | ', $messages);
            }
        }

        expect($answered)->toBe([], 'Root fields that returned data without a signed-in user')
            ->and($notRefused)->toBe([], 'Root fields that did not refuse a request without a signed-in user');
    });

    it('answers the listed public fields without a signed-in user', function (): void {
        $response = $this->postJson('/graphql', ['query' => '{ x402ProtocolStatus { __typename } smsInfo { __typename } }']);

        $response->assertOk();
        expect($response->json('errors'))->toBeNull();
    });
});
