<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Asset\Workflows\AssetTransferWorkflow;
use App\Domain\Asset\Workflows\Activities\InitiateAssetTransferActivity;
use App\Domain\Asset\Workflows\Activities\ValidateExchangeRateActivity;
use App\Domain\Asset\Workflows\Activities\CompleteAssetTransferActivity;
use App\Domain\Asset\Workflows\Activities\FailAssetTransferActivity;
use App\Models\Account;
use App\Models\AccountBalance;
use Workflow\WorkflowStub;

beforeEach(function () {
    // Assets are already seeded in migrations, no need to create duplicates
});

it('can create asset transfer workflow class', function () {
    // Test that the class exists and is properly structured
    expect(class_exists(AssetTransferWorkflow::class))->toBeTrue();
    expect(is_subclass_of(AssetTransferWorkflow::class, \Workflow\Workflow::class))->toBeTrue();
});

it('has execute method with correct signature', function () {
    $reflection = new ReflectionClass(AssetTransferWorkflow::class);
    $method = $reflection->getMethod('execute');
    
    expect($method->isPublic())->toBeTrue();
    expect($method->getNumberOfParameters())->toBe(5); // 4 required + 1 optional
    expect($method->getReturnType()->getName())->toBe('Generator');
});

it('validates workflow activities exist', function () {
    // Test that all required activities exist
    expect(class_exists(InitiateAssetTransferActivity::class))->toBeTrue();
    expect(class_exists(ValidateExchangeRateActivity::class))->toBeTrue();
    expect(class_exists(CompleteAssetTransferActivity::class))->toBeTrue();
    expect(class_exists(FailAssetTransferActivity::class))->toBeTrue();
});