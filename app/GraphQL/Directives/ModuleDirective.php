<?php

declare(strict_types=1);

namespace App\GraphQL\Directives;

use App\Infrastructure\Domain\DomainManager;
use Closure;
use GraphQL\Error\Error;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;

/**
 * GraphQL counterpart of the `module.enabled:<Domain>` route middleware.
 *
 * Fields carrying `@module(name: "<Domain>")` resolve with the error
 * "This operation is not available." while that domain is listed in
 * MODULES_DISABLED (config/modules.php). Placed on a type or type extension
 * (`extend type Mutation @module(name: "Ledger") { ... }`) it applies to every
 * field declared in that block only.
 *
 * The disabled state is read at resolve time, so a cached schema follows the
 * current value: MODULES_DISABLED (DomainManager::isDisabledByConfig), or,
 * while runtime module management is on (modules.runtime_management_enabled),
 * the same source the REST module route loader uses
 * (DomainManager::isDisabled, which prefers a runtime module change stored in
 * the domain_manager_disabled cache key).
 */
class ModuleDirective extends BaseDirective implements FieldMiddleware, TypeManipulator, TypeExtensionManipulator
{
    public function __construct(
        private readonly DomainManager $domainManager,
    ) {
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Resolve the field only while the named domain module is not listed in
MODULES_DISABLED. Used upon an object or object extension, it applies to all
fields declared there.
"""
directive @module(
  "Domain directory name (e.g. Ledger) or manifest name (finaegis/ledger)."
  name: String!
) repeatable on FIELD_DEFINITION | OBJECT
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $module = $this->directiveArgValue('name');
        if (! is_string($module) || $module === '') {
            throw new DefinitionException("@module on {$fieldValue->getFieldName()} requires a non-empty name.");
        }

        $fieldValue->wrapResolver(fn (callable $resolver): Closure => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $module) {
            if ($this->isDisabled($module)) {
                throw new Error('This operation is not available.');
            }

            return $resolver($root, $args, $context, $resolveInfo);
        });
    }

    private function isDisabled(string $module): bool
    {
        if ((bool) config('modules.runtime_management_enabled', false)) {
            return $this->domainManager->isDisabled($module);
        }

        return DomainManager::isDisabledByConfig($module);
    }

    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        if ($typeDefinition instanceof ObjectTypeDefinitionNode) {
            ASTHelper::addDirectiveToFields($this->directiveNode, $typeDefinition);
        }
    }

    public function manipulateTypeExtension(DocumentAST &$documentAST, TypeExtensionNode &$typeExtension): void
    {
        if ($typeExtension instanceof ObjectTypeExtensionNode) {
            ASTHelper::addDirectiveToFields($this->directiveNode, $typeExtension);
        }
    }
}
