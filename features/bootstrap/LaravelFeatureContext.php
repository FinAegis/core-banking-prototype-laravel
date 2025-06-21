<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Account;
use Laravel\Sanctum\Sanctum;

/**
 * Laravel Feature Context for Behat
 */
class LaravelFeatureContext implements Context
{
    protected static $app;
    protected static $kernel;
    protected $currentUser;
    protected $lastResponse;
    protected $baseUrl;

    /**
     * @BeforeSuite
     */
    public static function prepare()
    {
        putenv('APP_ENV=testing');
        
        if (!static::$app) {
            static::$app = require __DIR__ . '/../../bootstrap/app.php';
            static::$kernel = static::$app->make(\Illuminate\Contracts\Console\Kernel::class);
            static::$kernel->bootstrap();
        }
    }

    /**
     * @BeforeScenario
     */
    public function setUp()
    {
        $this->baseUrl = config('app.url');
        
        // Start database transaction
        DB::beginTransaction();
        
        // Run migrations if needed
        if (!$this->tablesExist()) {
            Artisan::call('migrate:fresh');
            Artisan::call('db:seed', ['--class' => 'DatabaseSeeder']);
        }
    }

    /**
     * @AfterScenario
     */
    public function tearDown()
    {
        // Rollback transaction
        DB::rollBack();
    }

    /**
     * Check if database tables exist
     */
    private function tablesExist(): bool
    {
        try {
            DB::table('users')->exists();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @Given I am logged in as :email
     */
    public function iAmLoggedInAs($email)
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $user = User::factory()->create([
                'email' => $email,
                'password' => Hash::make('password'),
            ]);
        }
        
        $this->currentUser = $user;
        Sanctum::actingAs($user);
    }

    /**
     * @Given I have an account with balance :amount :currency
     */
    public function iHaveAnAccountWithBalance($amount, $currency)
    {
        $account = Account::factory()->create([
            'user_uuid' => $this->currentUser->uuid,
        ]);
        
        // Convert amount to cents
        $amountInCents = (int) ($amount * 100);
        
        if ($currency === 'USD') {
            $account->balance = $amountInCents;
            $account->save();
        } else {
            $account->addBalance($currency, $amountInCents);
        }
        
        $this->currentUser->setAttribute('current_account', $account);
    }

    /**
     * @When I send a POST request to :endpoint
     */
    public function iSendAPostRequestTo($endpoint)
    {
        $headers = ['Accept' => 'application/json'];
        
        if ($this->currentUser) {
            $token = $this->currentUser->createToken('behat-test')->plainTextToken;
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        
        $request = \Illuminate\Http\Request::create(
            $this->baseUrl . $endpoint,
            'POST',
            [],
            [],
            [],
            $this->transformHeaders($headers),
            null
        );
        
        $this->lastResponse = app()->handle($request);
    }

    /**
     * @When I deposit :amount :currency into my account
     */
    public function iDepositIntoMyAccount($amount, $currency)
    {
        $account = $this->currentUser->getAttribute('current_account') ??
                   Account::where('user_uuid', $this->currentUser->uuid)->first();
        
        $amountInCents = (int) ($amount * 100);
        
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        $token = $this->currentUser->createToken('behat-test')->plainTextToken;
        $headers['Authorization'] = 'Bearer ' . $token;
        
        $request = \Illuminate\Http\Request::create(
            $this->baseUrl . "/api/accounts/{$account->uuid}/deposit",
            'POST',
            [],
            [],
            [],
            $this->transformHeaders($headers),
            json_encode(['amount' => $amountInCents])
        );
        
        $this->lastResponse = app()->handle($request);
    }

    /**
     * @When I withdraw :amount :currency from my account
     */
    public function iWithdrawFromMyAccount($amount, $currency)
    {
        $account = $this->currentUser->getAttribute('current_account') ??
                   Account::where('user_uuid', $this->currentUser->uuid)->first();
        
        $amountInCents = (int) ($amount * 100);
        
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        $token = $this->currentUser->createToken('behat-test')->plainTextToken;
        $headers['Authorization'] = 'Bearer ' . $token;
        
        $request = \Illuminate\Http\Request::create(
            $this->baseUrl . "/api/accounts/{$account->uuid}/withdraw",
            'POST',
            [],
            [],
            [],
            $this->transformHeaders($headers),
            json_encode(['amount' => $amountInCents])
        );
        
        $this->lastResponse = app()->handle($request);
    }

    /**
     * @When I try to withdraw :amount :currency from my account
     */
    public function iTryToWithdrawFromMyAccount($amount, $currency)
    {
        $this->iWithdrawFromMyAccount($amount, $currency);
    }

    /**
     * @When I transfer :amount :currency to account :accountUuid
     */
    public function iTransferToAccount($amount, $currency, $accountUuid)
    {
        $fromAccount = $this->currentUser->getAttribute('current_account') ??
                       Account::where('user_uuid', $this->currentUser->uuid)->first();
        
        $amountInCents = (int) ($amount * 100);
        
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        $token = $this->currentUser->createToken('behat-test')->plainTextToken;
        $headers['Authorization'] = 'Bearer ' . $token;
        
        $request = \Illuminate\Http\Request::create(
            $this->baseUrl . '/api/transfers',
            'POST',
            [],
            [],
            [],
            $this->transformHeaders($headers),
            json_encode([
                'from_account_uuid' => $fromAccount->uuid,
                'to_account_uuid' => $accountUuid,
                'amount' => $amountInCents,
                'asset_code' => $currency,
            ])
        );
        
        $this->lastResponse = app()->handle($request);
    }

    /**
     * @When I try to transfer :amount :currency to account :accountUuid
     */
    public function iTryToTransferToAccount($amount, $currency, $accountUuid)
    {
        $this->iTransferToAccount($amount, $currency, $accountUuid);
    }

    /**
     * @Then the response status code should be :code
     */
    public function theResponseStatusCodeShouldBe($code)
    {
        $actualCode = $this->lastResponse->getStatusCode();
        if ($actualCode != $code) {
            throw new \Exception(
                "Expected status code $code but got $actualCode. Response: " . 
                $this->lastResponse->getContent()
            );
        }
    }

    /**
     * @Then the response should have a :field field
     */
    public function theResponseShouldHaveAField($field)
    {
        $content = json_decode($this->lastResponse->getContent(), true);
        
        if (!array_key_exists($field, $content)) {
            throw new \Exception("Response does not have field: $field");
        }
    }

    /**
     * @Then the response :field field should equal :value
     */
    public function theResponseFieldShouldEqual($field, $value)
    {
        $content = json_decode($this->lastResponse->getContent(), true);
        
        if (!isset($content[$field])) {
            throw new \Exception("Response does not have field: $field");
        }
        
        if ($content[$field] != $value) {
            throw new \Exception(
                "Expected $field to be '$value' but got '{$content[$field]}'"
            );
        }
    }

    /**
     * @Then the deposit should be successful
     */
    public function theDepositShouldBeSuccessful()
    {
        $this->theResponseStatusCodeShouldBe(200);
    }

    /**
     * @Then the withdrawal should be successful
     */
    public function theWithdrawalShouldBeSuccessful()
    {
        $this->theResponseStatusCodeShouldBe(200);
    }

    /**
     * @Then the withdrawal should fail with error :message
     */
    public function theWithdrawalShouldFailWithError($message)
    {
        $this->theResponseStatusCodeShouldBe(422);
        
        $content = json_decode($this->lastResponse->getContent(), true);
        if (!isset($content['message']) || !str_contains($content['message'], $message)) {
            throw new \Exception("Expected error message containing '$message' but got: " . json_encode($content));
        }
    }

    /**
     * @Then the transfer should be successful
     */
    public function theTransferShouldBeSuccessful()
    {
        $this->theResponseStatusCodeShouldBe(200);
    }

    /**
     * @Then the transfer should fail with error :message
     */
    public function theTransferShouldFailWithError($message)
    {
        $this->theResponseStatusCodeShouldBe(422);
        
        $content = json_decode($this->lastResponse->getContent(), true);
        if (!isset($content['message']) || !str_contains($content['message'], $message)) {
            throw new \Exception("Expected error message containing '$message' but got: " . json_encode($content));
        }
    }

    /**
     * @Then my account balance should be :amount :currency
     */
    public function myAccountBalanceShouldBe($amount, $currency)
    {
        $account = $this->currentUser->getAttribute('current_account') ??
                   Account::where('user_uuid', $this->currentUser->uuid)->first();
        
        $account->refresh();
        
        $expectedBalance = (int) ($amount * 100);
        $actualBalance = $currency === 'USD' ? $account->balance : $account->getBalance($currency);
        
        if ($actualBalance != $expectedBalance) {
            throw new \Exception(
                "Expected balance to be $expectedBalance but got $actualBalance"
            );
        }
    }

    /**
     * @Then account :accountUuid should have balance :amount :currency
     */
    public function accountShouldHaveBalance($accountUuid, $amount, $currency)
    {
        $account = Account::where('uuid', $accountUuid)->firstOrFail();
        
        $expectedBalance = (int) ($amount * 100);
        $actualBalance = $currency === 'USD' ? $account->balance : $account->getBalance($currency);
        
        if ($actualBalance != $expectedBalance) {
            throw new \Exception(
                "Expected account $accountUuid to have balance $expectedBalance but got $actualBalance"
            );
        }
    }

    /**
     * @When I check my total balance
     */
    public function iCheckMyTotalBalance()
    {
        $account = $this->currentUser->getAttribute('current_account') ??
                   Account::where('user_uuid', $this->currentUser->uuid)->first();
        
        $headers = ['Accept' => 'application/json'];
        $token = $this->currentUser->createToken('behat-test')->plainTextToken;
        $headers['Authorization'] = 'Bearer ' . $token;
        
        $request = \Illuminate\Http\Request::create(
            $this->baseUrl . "/api/accounts/{$account->uuid}/balances",
            'GET',
            [],
            [],
            [],
            $this->transformHeaders($headers)
        );
        
        $this->lastResponse = app()->handle($request);
    }

    /**
     * @Then I should see:
     */
    public function iShouldSee(TableNode $table)
    {
        $content = json_decode($this->lastResponse->getContent(), true);
        
        foreach ($table->getHash() as $row) {
            $currency = $row['Currency'];
            $expectedBalance = $row['Balance'];
            
            $found = false;
            foreach ($content['data'] ?? [] as $balance) {
                if ($balance['asset_code'] === $currency) {
                    $actualBalance = number_format($balance['balance'] / 100, 2, '.', '');
                    if ($actualBalance == $expectedBalance) {
                        $found = true;
                        break;
                    } else {
                        throw new \Exception(
                            "Expected $currency balance to be $expectedBalance but got $actualBalance"
                        );
                    }
                }
            }
            
            if (!$found) {
                throw new \Exception("Currency $currency not found in response");
            }
        }
    }

    /**
     * @When I submit the following bulk transfers:
     */
    public function iSubmitTheFollowingBulkTransfers(TableNode $table)
    {
        $fromAccount = $this->currentUser->getAttribute('current_account') ??
                       Account::where('user_uuid', $this->currentUser->uuid)->first();
        
        $transfers = [];
        foreach ($table->getHash() as $row) {
            $transfers[] = [
                'to_account_uuid' => $row['to_account'],
                'amount' => (int) ($row['amount'] * 100),
                'asset_code' => $row['currency'],
            ];
        }
        
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        $token = $this->currentUser->createToken('behat-test')->plainTextToken;
        $headers['Authorization'] = 'Bearer ' . $token;
        
        $request = \Illuminate\Http\Request::create(
            $this->baseUrl . '/api/bulk-transfers',
            'POST',
            [],
            [],
            [],
            $this->transformHeaders($headers),
            json_encode([
                'from_account_uuid' => $fromAccount->uuid,
                'transfers' => $transfers,
            ])
        );
        
        $this->lastResponse = app()->handle($request);
    }

    /**
     * @Then all transfers should be successful
     */
    public function allTransfersShouldBeSuccessful()
    {
        $this->theResponseStatusCodeShouldBe(200);
        
        $content = json_decode($this->lastResponse->getContent(), true);
        if (!isset($content['data']) || !is_array($content['data'])) {
            throw new \Exception("Expected response to have 'data' array");
        }
        
        foreach ($content['data'] as $transfer) {
            if ($transfer['status'] !== 'completed') {
                throw new \Exception("Transfer {$transfer['id']} failed with status: {$transfer['status']}");
            }
        }
    }

    /**
     * Transform headers for request
     */
    private function transformHeaders($headers)
    {
        $transformed = [];
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), ['accept', 'content-type', 'authorization'])) {
                $transformed['HTTP_' . str_replace('-', '_', strtoupper($key))] = $value;
            } else {
                $transformed[$key] = $value;
            }
        }
        return $transformed;
    }
}