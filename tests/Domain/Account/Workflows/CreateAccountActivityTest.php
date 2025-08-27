<?php

namespace Tests\Domain\Account\Workflows;

use App\Domain\Account\Aggregates\LedgerAggregate;
use App\Domain\Account\DataObjects\Account;
use App\Domain\Account\Workflows\CreateAccountActivity;
use App\Domain\Account\Workflows\CreateAccountWorkflow;
use App\Models\User;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\WorkflowStub;

class CreateAccountActivityTest extends DomainTestCase
{
    private const string ACCOUNT_UUID = 'account-uuid';

    private const string ACCOUNT_NAME = 'fake-account';

    #[Test]
    public function it_creates_account_using_ledger(): void
    {
        $ledgerMock = Mockery::mock(LedgerAggregate::class);
        $ledgerMock->expects('retrieve')
            ->with(Mockery::type('string'))
            ->andReturnSelf();

        $ledgerMock->expects('createAccount')
            ->with(Mockery::type(Account::class))
            ->andReturnSelf();

        $ledgerMock->expects('persist')
            ->andReturnSelf();

        // Bind the mock to the container so the activity can retrieve it
        $this->app->instance(LedgerAggregate::class, $ledgerMock);

        $workflow = WorkflowStub::make(CreateAccountWorkflow::class);
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());

        $activity = new CreateAccountActivity(
            0,
            now()->toDateTimeString(),
            $storedWorkflow,
            $this->fakeAccount()
        );

        $activity->handle();
    }

    protected function fakeAccount(): Account
    {
        // Create a user since DomainTestCase doesn't create one automatically
        $user = User::factory()->create();

        return hydrate(
            Account::class,
            [
                'name'      => self::ACCOUNT_NAME,
                'user_uuid' => $user->uuid,
            ]
        );
    }
}
