<?php

declare(strict_types=1);

namespace App\GraphQL\Directives;

use App\Models\User;
use Closure;
use GraphQL\Error\Error;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Laravel\Sanctum\TransientToken;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * GraphQL counterpart of the EnforceMethodScope middleware of the `api`
 * route group, which /graphql does not run.
 *
 * Registered as a global field middleware (config/lighthouse.php,
 * `field_middleware`). It wraps only the root fields, so the check runs once
 * per Query, Mutation or Subscription field, after the document is parsed or
 * loaded from the persisted-query cache and before any directive or resolver
 * of the field runs:
 *  - Query and Subscription fields need the `read` ability;
 *  - Mutation fields need `write`. The `create` and `update` permissions of
 *    the API tokens made on the dashboard (Jetstream) count as `write`, so a
 *    token whose owner granted them keeps working.
 *
 * As in EnforceMethodScope, callers without a Sanctum personal access token
 * pass: guests (field guards still apply), session users (TransientToken),
 * and tokens issued with `*`, which tokenCan() accepts for every ability.
 */
final class TokenAbilitiesDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * Abilities that allow a root field, by root type. One of them is enough;
     * the first one is the scope named in the error.
     */
    private const ABILITIES = [
        RootType::QUERY        => ['read'],
        RootType::SUBSCRIPTION => ['read'],
        RootType::MUTATION     => ['write', 'create', 'update'],
    ];

    public function __construct(
        private readonly AuthFactory $authFactory,
    ) {
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Internal: applied to every root field through `field_middleware` in
config/lighthouse.php. Requires the `read` token ability for queries and
subscriptions and `write` for mutations. Not meant for use in the schema.
"""
directive @tokenAbilities on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $abilities = self::ABILITIES[$fieldValue->getParentName()] ?? null;
        if ($abilities === null) {
            return;
        }

        $fieldValue->wrapResolver(fn (callable $resolver): Closure => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $abilities) {
            // A broadcast resolves the subscription field again for each
            // stored subscriber. Their ability was checked when they
            // subscribed, and a request in progress belongs to someone else.
            if (! $root instanceof Subscriber) {
                $this->authorize($abilities);
            }

            return $resolver($root, $args, $context, $resolveInfo);
        });
    }

    /**
     * @param  non-empty-list<string>  $abilities
     */
    private function authorize(array $abilities): void
    {
        // The schema authenticates with @guard(with: ["sanctum"]). The guard
        // returns a session user with a TransientToken, or the owner of the
        // bearer token with that token attached.
        $user = $this->authFactory->guard('sanctum')->user();
        if (! $user instanceof User) {
            return;
        }

        $token = $user->currentAccessToken();
        if ($token === null || $token instanceof TransientToken) {
            return;
        }

        foreach ($abilities as $ability) {
            if ($user->tokenCan($ability)) {
                return;
            }
        }

        throw new Error(
            "Insufficient permissions. Required scope: {$abilities[0]}",
            extensions: [
                'category'       => 'authorization',
                'code'           => 'INSUFFICIENT_SCOPE',
                'required_scope' => $abilities[0],
            ],
        );
    }
}
