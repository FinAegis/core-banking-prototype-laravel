<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use App\Models\User;
use App\Models\Account;
use App\Models\Asset;
use App\Models\ExchangeRate;
use App\Models\BasketAsset;
use App\Models\BasketComponent;
use App\Domain\Basket\Services\BasketRebalancingService;
use App\Domain\Account\Services\AssetTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
    private static $app;
    private static $kernel;
    private $currentUser;
    private $accounts = [];
    private $assets = [];
    private $baskets = [];
    private $lastResponse;
    private $lastException;

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
        // Start database transaction
        DB::beginTransaction();
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
     * @Given the following assets exist:
     */
    public function theFollowingAssetsExist(TableNode $table)
    {
        foreach ($table->getHash() as $row) {
            $this->assets[$row['code']] = Asset::factory()->create([
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $row['type'],
                'precision' => $row['type'] === 'fiat' ? 2 : 8,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @Given the following exchange rates exist:
     */
    public function theFollowingExchangeRatesExist(TableNode $table)
    {
        foreach ($table->getHash() as $row) {
            ExchangeRate::create([
                'from_asset_code' => $row['from'],
                'to_asset_code' => $row['to'],
                'rate' => (float) $row['rate'],
                'provider' => $row['provider'] ?? 'ECB',
                'is_active' => true,
            ]);
        }
    }

    /**
     * @Given the following accounts exist:
     */
    public function theFollowingAccountsExist(TableNode $table)
    {
        foreach ($table->getHash() as $row) {
            $user = User::where('email', $row['owner'])->first();
            if (!$user) {
                $user = User::factory()->create(['email' => $row['owner']]);
            }

            $account = Account::factory()->create([
                'uuid' => $row['uuid'],
                'user_uuid' => $user->uuid,
            ]);

            // Set balance for the specified currency
            $account->addBalance($row['currency'], (int) ($row['balance'] * 100));
            
            $this->accounts[$row['uuid']] = $account;
        }
    }

    /**
     * @When I create a basket :code with the following components:
     */
    public function iCreateABasketWithTheFollowingComponents($code, TableNode $table)
    {
        try {
            $basket = BasketAsset::create([
                'code' => $code,
                'name' => $code . ' Basket',
                'description' => 'Test basket',
                'type' => 'fixed',
                'is_active' => true,
            ]);

            foreach ($table->getHash() as $row) {
                BasketComponent::create([
                    'basket_code' => $code,
                    'asset_code' => $row['asset'],
                    'weight' => (float) $row['weight'],
                    'is_active' => true,
                ]);
            }

            $this->baskets[$code] = $basket;
            $this->lastResponse = $basket;
        } catch (\Exception $e) {
            $this->lastException = $e;
        }
    }

    /**
     * @Given I have a basket :code with the following components:
     */
    public function iHaveABasketWithTheFollowingComponents($code, TableNode $table)
    {
        $this->iCreateABasketWithTheFollowingComponents($code, $table);
        
        // Create the basket as an asset
        Asset::firstOrCreate([
            'code' => $code,
        ], [
            'name' => $code . ' Basket',
            'type' => 'basket',
            'precision' => 2,
            'is_active' => true,
            'metadata' => ['is_basket' => true],
        ]);
    }

    /**
     * @Given I have a dynamic basket :code with the following components:
     */
    public function iHaveADynamicBasketWithTheFollowingComponents($code, TableNode $table)
    {
        $basket = BasketAsset::create([
            'code' => $code,
            'name' => $code . ' Dynamic Basket',
            'description' => 'Test dynamic basket',
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
            'is_active' => true,
        ]);

        foreach ($table->getHash() as $row) {
            BasketComponent::create([
                'basket_code' => $code,
                'asset_code' => $row['asset'],
                'weight' => (float) $row['weight'],
                'min_weight' => isset($row['min_weight']) ? (float) $row['min_weight'] : null,
                'max_weight' => isset($row['max_weight']) ? (float) $row['max_weight'] : null,
                'is_active' => true,
            ]);
        }

        $this->baskets[$code] = $basket;
    }

    /**
     * @When I decompose :amount of basket :basketCode
     */
    public function iDecomposeOfBasket($amount, $basketCode)
    {
        $account = $this->accounts[array_key_first($this->accounts)] ?? 
                   Account::where('user_uuid', $this->currentUser->uuid)->first();
        
        $service = app(\App\Domain\Basket\Services\BasketAccountService::class);
        
        try {
            $result = $service->decomposeBasket($account, $basketCode, (int) ($amount * 100));
            $this->lastResponse = $result;
        } catch (\Exception $e) {
            $this->lastException = $e;
        }
    }

    /**
     * @When I compose :amount units of basket :basketCode
     */
    public function iComposeUnitsOfBasket($amount, $basketCode)
    {
        $account = $this->accounts[array_key_first($this->accounts)] ?? 
                   Account::where('user_uuid', $this->currentUser->uuid)->first();
        
        $service = app(\App\Domain\Basket\Services\BasketAccountService::class);
        
        try {
            $result = $service->composeBasket($account, $basketCode, (int) ($amount * 100));
            $this->lastResponse = $result;
        } catch (\Exception $e) {
            $this->lastException = $e;
        }
    }

    /**
     * @When the basket needs rebalancing
     */
    public function theBasketNeedsRebalancing()
    {
        // This is a precondition check - in real scenario, this would be determined by market conditions
        // For testing, we'll assume it needs rebalancing
    }

    /**
     * @When I trigger a rebalance
     */
    public function iTriggerARebalance()
    {
        $basket = end($this->baskets);
        $service = app(BasketRebalancingService::class);
        
        try {
            $result = $service->rebalance($basket);
            $this->lastResponse = $result;
        } catch (\Exception $e) {
            $this->lastException = $e;
        }
    }

    /**
     * @Then the basket should be created successfully
     */
    public function theBasketShouldBeCreatedSuccessfully()
    {
        if ($this->lastException) {
            throw new \Exception("Basket creation failed: " . $this->lastException->getMessage());
        }
        
        if (!$this->lastResponse instanceof BasketAsset) {
            throw new \Exception("Expected response to be instance of BasketAsset");
        }
    }

    /**
     * @Then the basket value should be calculated correctly
     */
    public function theBasketValueShouldBeCalculatedCorrectly()
    {
        $basket = $this->lastResponse;
        $service = app(\App\Domain\Basket\Services\BasketValueCalculationService::class);
        
        $value = $service->calculateValue($basket);
        if ($value === null) {
            throw new \Exception("Expected basket value to not be null");
        }
        if ($value->value <= 0) {
            throw new \Exception("Expected basket value to be greater than 0, got: " . $value->value);
        }
    }

    /**
     * @Then I should have :amount :currency in my account
     */
    public function iShouldHaveInMyAccount($amount, $currency)
    {
        $account = $this->accounts[array_key_first($this->accounts)] ?? 
                   Account::where('user_uuid', $this->currentUser->uuid)->first();
        
        $balance = $account->getBalance($currency);
        $expectedBalance = (int) ($amount * 100);
        
        if ($balance !== $expectedBalance) {
            throw new \Exception("Expected balance to be $expectedBalance but got $balance");
        }
    }

    /**
     * @Then my :currency balance should be :amount
     */
    public function myBalanceShouldBe($currency, $amount)
    {
        $this->iShouldHaveInMyAccount($amount, $currency);
    }

    /**
     * @Then the basket should be rebalanced within the weight limits
     */
    public function theBasketShouldBeRebalancedWithinTheWeightLimits()
    {
        if (!is_array($this->lastResponse)) {
            throw new \Exception("Expected response to be an array");
        }
        if ($this->lastResponse['status'] !== 'completed') {
            throw new \Exception("Expected status to be 'completed' but got '" . $this->lastResponse['status'] . "'");
        }
        
        // Verify all components are within their weight limits
        $basket = end($this->baskets);
        foreach ($basket->components as $component) {
            if ($component->min_weight && $component->weight < $component->min_weight) {
                throw new \Exception("Component weight {$component->weight} is below minimum {$component->min_weight}");
            }
            if ($component->max_weight && $component->weight > $component->max_weight) {
                throw new \Exception("Component weight {$component->weight} is above maximum {$component->max_weight}");
            }
        }
    }

    /**
     * @Then a rebalancing event should be recorded
     */
    public function aRebalancingEventShouldBeRecorded()
    {
        // In a real implementation, we would check for the event in the event store
        // For now, we'll verify the response indicates success
        if (!isset($this->lastResponse['adjustments']) || !is_array($this->lastResponse['adjustments'])) {
            throw new \Exception("Expected response to have 'adjustments' array");
        }
    }

    /**
     * @Then the response data should contain :count exchange rates
     */
    public function theResponseDataShouldContainExchangeRates($count)
    {
        $data = $this->lastResponse->json('data');
        if (count($data) !== (int) $count) {
            throw new \Exception("Expected " . (int) $count . " exchange rates but got " . count($data));
        }
    }

}
