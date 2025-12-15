<?php

declare(strict_types=1);

namespace Tests\Domain\AgentProtocol\Contracts;

use App\Domain\AgentProtocol\Contracts\RiskScoringInterface;
use App\Domain\AgentProtocol\Contracts\TransactionVerifierInterface;
use App\Domain\AgentProtocol\Contracts\WalletOperationInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Tests\TestCase;

/**
 * Tests for Agent Protocol interface contracts.
 *
 * These tests verify that the interfaces have the expected
 * method signatures and follow the contract specifications.
 */
class InterfaceContractTest extends TestCase
{
    /**
     * Get the type name from a ReflectionParameter or ReflectionMethod return type.
     */
    private function getTypeName(ReflectionParameter|ReflectionMethod $reflection): ?string
    {
        $type = $reflection instanceof ReflectionParameter
            ? $reflection->getType()
            : $reflection->getReturnType();

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        return $type !== null ? (string) $type : null;
    }

    public function test_transaction_verifier_interface_has_required_methods(): void
    {
        $reflection = new ReflectionClass(TransactionVerifierInterface::class);

        $this->assertTrue($reflection->hasMethod('verify'));
        $this->assertTrue($reflection->hasMethod('getVerificationLevel'));
        $this->assertTrue($reflection->hasMethod('calculateRiskScore'));
        $this->assertTrue($reflection->hasMethod('checkVelocityLimits'));
    }

    public function test_transaction_verifier_verify_method_signature(): void
    {
        $method = new ReflectionMethod(TransactionVerifierInterface::class, 'verify');

        $parameters = $method->getParameters();
        $this->assertCount(2, $parameters);
        $this->assertEquals('transactionId', $parameters[0]->getName());
        $this->assertEquals('string', $this->getTypeName($parameters[0]));
        $this->assertEquals('transactionData', $parameters[1]->getName());
        $this->assertEquals('array', $this->getTypeName($parameters[1]));

        $this->assertEquals('array', $this->getTypeName($method));
    }

    public function test_transaction_verifier_get_verification_level_signature(): void
    {
        $method = new ReflectionMethod(TransactionVerifierInterface::class, 'getVerificationLevel');

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('riskScore', $parameters[0]->getName());
        $this->assertEquals('int', $this->getTypeName($parameters[0]));

        $this->assertEquals('string', $this->getTypeName($method));
    }

    public function test_transaction_verifier_calculate_risk_score_signature(): void
    {
        $method = new ReflectionMethod(TransactionVerifierInterface::class, 'calculateRiskScore');

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('transactionData', $parameters[0]->getName());
        $this->assertEquals('array', $this->getTypeName($parameters[0]));

        $this->assertEquals('int', $this->getTypeName($method));
    }

    public function test_transaction_verifier_check_velocity_limits_signature(): void
    {
        $method = new ReflectionMethod(TransactionVerifierInterface::class, 'checkVelocityLimits');

        $parameters = $method->getParameters();
        $this->assertCount(2, $parameters);
        $this->assertEquals('agentId', $parameters[0]->getName());
        $this->assertEquals('string', $this->getTypeName($parameters[0]));
        $this->assertEquals('amount', $parameters[1]->getName());
        $this->assertEquals('float', $this->getTypeName($parameters[1]));

        $this->assertEquals('array', $this->getTypeName($method));
    }

    public function test_wallet_operation_interface_has_required_methods(): void
    {
        $reflection = new ReflectionClass(WalletOperationInterface::class);

        $this->assertTrue($reflection->hasMethod('getBalance'));
        $this->assertTrue($reflection->hasMethod('transfer'));
        $this->assertTrue($reflection->hasMethod('holdFunds'));
        $this->assertTrue($reflection->hasMethod('releaseFunds'));
        $this->assertTrue($reflection->hasMethod('hasSufficientBalance'));
    }

    public function test_wallet_operation_get_balance_signature(): void
    {
        $method = new ReflectionMethod(WalletOperationInterface::class, 'getBalance');

        $parameters = $method->getParameters();
        $this->assertCount(2, $parameters);
        $this->assertEquals('walletId', $parameters[0]->getName());
        $this->assertEquals('string', $this->getTypeName($parameters[0]));
        $this->assertEquals('currency', $parameters[1]->getName());
        $this->assertTrue($parameters[1]->allowsNull());

        $this->assertEquals('array', $this->getTypeName($method));
    }

    public function test_wallet_operation_transfer_signature(): void
    {
        $method = new ReflectionMethod(WalletOperationInterface::class, 'transfer');

        $parameters = $method->getParameters();
        $this->assertCount(5, $parameters);
        $this->assertEquals('fromWalletId', $parameters[0]->getName());
        $this->assertEquals('toWalletId', $parameters[1]->getName());
        $this->assertEquals('amount', $parameters[2]->getName());
        $this->assertEquals('float', $this->getTypeName($parameters[2]));
        $this->assertEquals('currency', $parameters[3]->getName());
        $this->assertEquals('metadata', $parameters[4]->getName());

        $this->assertEquals('array', $this->getTypeName($method));
    }

    public function test_wallet_operation_hold_funds_signature(): void
    {
        $method = new ReflectionMethod(WalletOperationInterface::class, 'holdFunds');

        $parameters = $method->getParameters();
        $this->assertCount(5, $parameters);
        $this->assertEquals('walletId', $parameters[0]->getName());
        $this->assertEquals('amount', $parameters[1]->getName());
        $this->assertEquals('float', $this->getTypeName($parameters[1]));
        $this->assertEquals('currency', $parameters[2]->getName());
        $this->assertEquals('reason', $parameters[3]->getName());
        $this->assertEquals('expiresInSeconds', $parameters[4]->getName());
        $this->assertTrue($parameters[4]->allowsNull());

        $this->assertEquals('array', $this->getTypeName($method));
    }

    public function test_wallet_operation_release_funds_signature(): void
    {
        $method = new ReflectionMethod(WalletOperationInterface::class, 'releaseFunds');

        $parameters = $method->getParameters();
        $this->assertCount(2, $parameters);
        $this->assertEquals('holdId', $parameters[0]->getName());
        $this->assertEquals('releaseToWalletId', $parameters[1]->getName());
        $this->assertTrue($parameters[1]->allowsNull());

        $this->assertEquals('array', $this->getTypeName($method));
    }

    public function test_wallet_operation_has_sufficient_balance_signature(): void
    {
        $method = new ReflectionMethod(WalletOperationInterface::class, 'hasSufficientBalance');

        $parameters = $method->getParameters();
        $this->assertCount(4, $parameters);
        $this->assertEquals('walletId', $parameters[0]->getName());
        $this->assertEquals('amount', $parameters[1]->getName());
        $this->assertEquals('float', $this->getTypeName($parameters[1]));
        $this->assertEquals('currency', $parameters[2]->getName());
        $this->assertEquals('includeHeld', $parameters[3]->getName());
        $this->assertEquals('bool', $this->getTypeName($parameters[3]));

        $this->assertEquals('bool', $this->getTypeName($method));
    }

    public function test_risk_scoring_interface_has_required_methods(): void
    {
        $reflection = new ReflectionClass(RiskScoringInterface::class);

        $this->assertTrue($reflection->hasMethod('calculateRisk'));
        $this->assertTrue($reflection->hasMethod('getRiskLevel'));
        $this->assertTrue($reflection->hasMethod('isAcceptableRisk'));
        $this->assertTrue($reflection->hasMethod('getRiskWeights'));
    }

    public function test_risk_scoring_calculate_risk_signature(): void
    {
        $method = new ReflectionMethod(RiskScoringInterface::class, 'calculateRisk');

        $parameters = $method->getParameters();
        $this->assertCount(3, $parameters);
        $this->assertEquals('agentId', $parameters[0]->getName());
        $this->assertEquals('string', $this->getTypeName($parameters[0]));
        $this->assertEquals('amount', $parameters[1]->getName());
        $this->assertEquals('float', $this->getTypeName($parameters[1]));
        $this->assertEquals('context', $parameters[2]->getName());
        $this->assertEquals('array', $this->getTypeName($parameters[2]));

        $this->assertEquals('array', $this->getTypeName($method));
    }

    public function test_risk_scoring_get_risk_level_signature(): void
    {
        $method = new ReflectionMethod(RiskScoringInterface::class, 'getRiskLevel');

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('score', $parameters[0]->getName());
        $this->assertEquals('int', $this->getTypeName($parameters[0]));

        $this->assertEquals('string', $this->getTypeName($method));
    }

    public function test_risk_scoring_is_acceptable_risk_signature(): void
    {
        $method = new ReflectionMethod(RiskScoringInterface::class, 'isAcceptableRisk');

        $parameters = $method->getParameters();
        $this->assertCount(2, $parameters);
        $this->assertEquals('score', $parameters[0]->getName());
        $this->assertEquals('int', $this->getTypeName($parameters[0]));
        $this->assertEquals('operationType', $parameters[1]->getName());
        $this->assertEquals('string', $this->getTypeName($parameters[1]));

        $this->assertEquals('bool', $this->getTypeName($method));
    }

    public function test_risk_scoring_get_risk_weights_signature(): void
    {
        $method = new ReflectionMethod(RiskScoringInterface::class, 'getRiskWeights');

        $parameters = $method->getParameters();
        $this->assertCount(0, $parameters);

        $this->assertEquals('array', $this->getTypeName($method));
    }

    public function test_all_interfaces_are_interfaces(): void
    {
        $interfaces = [
            TransactionVerifierInterface::class,
            WalletOperationInterface::class,
            RiskScoringInterface::class,
        ];

        foreach ($interfaces as $interface) {
            $reflection = new ReflectionClass($interface);
            $this->assertTrue(
                $reflection->isInterface(),
                "{$interface} should be an interface"
            );
        }
    }

    public function test_all_interface_methods_are_public(): void
    {
        $interfaces = [
            TransactionVerifierInterface::class,
            WalletOperationInterface::class,
            RiskScoringInterface::class,
        ];

        foreach ($interfaces as $interface) {
            $reflection = new ReflectionClass($interface);
            foreach ($reflection->getMethods() as $method) {
                $this->assertTrue(
                    $method->isPublic(),
                    "{$interface}::{$method->getName()} should be public"
                );
            }
        }
    }
}
