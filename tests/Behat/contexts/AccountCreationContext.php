<?php

namespace Tests\Behat\Contexts;

use App\Domain\Account\Models\Account;
use App\Models\User;
use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;
use Exception;
use PHPUnit\Framework\Assert;

class AccountCreationContext extends MinkContext implements Context
{
    private $currentUser;

    /**
     * Get wait time in milliseconds - reduced in CI environment for faster execution.
     *
     * @param  int $defaultMs Default wait time in milliseconds for local development
     * @return int Wait time to use (500ms in CI, default locally)
     */
    private function getWaitTime(int $defaultMs = 2000): int
    {
        // In CI environment, use shorter waits (500ms) since there's no real browser rendering
        // Locally, use longer waits (2-3 seconds) for actual browser interactions
        return getenv('CI') ? 500 : $defaultMs;
    }

    /**
     * @Given I am logged in as a user
     */
    public function iAmLoggedInAsAUser(): void
    {
        $this->currentUser = User::factory()->create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->visit('/login');
        $this->fillField('email', 'test@example.com');
        $this->fillField('password', 'password');
        $this->pressButton('Log in');

        // Wait for redirect (reduced in CI for faster execution)
        $this->getSession()->wait($this->getWaitTime(2000));

        Assert::assertStringContainsString('/dashboard', $this->getSession()->getCurrentUrl());
    }

    /**
     * @Given I have an account named :accountName
     */
    public function iHaveAnAccountNamed($accountName): void
    {
        if (! $this->currentUser) {
            $this->iAmLoggedInAsAUser();
        }

        Account::factory()->create([
            'user_uuid' => $this->currentUser->uuid,
            'name'      => $accountName,
            'balance'   => 0,
        ]);
    }

    /**
     * @When I click :text
     */
    public function iClick($text): void
    {
        $button = $this->getSession()->getPage()->findButton($text);
        if (! $button) {
            $link = $this->getSession()->getPage()->findLink($text);
            if (! $link) {
                throw new Exception("Could not find button or link with text: $text");
            }
            $link->click();
        } else {
            $button->click();
        }
    }

    /**
     * @Then I should see :text in the modal
     */
    public function iShouldSeeInTheModal($text): void
    {
        $modal = $this->getSession()->getPage()->find('css', '#accountModal');
        Assert::assertNotNull($modal, 'Modal not found');
        Assert::assertStringContainsString($text, $modal->getText());
    }

    /**
     * @When I press :button in the modal
     */
    public function iPressInTheModal($button): void
    {
        $modal = $this->getSession()->getPage()->find('css', '#accountModal');
        $btn = $modal->findButton($button);
        Assert::assertNotNull($btn, "Button '$button' not found in modal");
        $btn->click();

        // Wait for AJAX request to complete (reduced in CI for faster execution)
        $this->getSession()->wait($this->getWaitTime(3000));
    }

    /**
     * @When I clear :field
     */
    public function iClear($field): void
    {
        $this->getSession()->getPage()->fillField($field, '');
    }

    /**
     * @Then I should see an error message
     */
    public function iShouldSeeAnErrorMessage(): void
    {
        $errorDiv = $this->getSession()->getPage()->find('css', '#accountError');
        Assert::assertNotNull($errorDiv);
        Assert::assertFalse($errorDiv->hasClass('hidden'), 'Error message is hidden');
    }

    /**
     * @Then I should still see the modal
     */
    public function iShouldStillSeeTheModal(): void
    {
        $modal = $this->getSession()->getPage()->find('css', '#accountModal');
        Assert::assertNotNull($modal);
        Assert::assertFalse($modal->hasClass('hidden'), 'Modal is hidden');
    }

    /**
     * @AfterScenario
     */
    public function cleanup(): void
    {
        // Clean up test data
        if ($this->currentUser) {
            Account::where('user_uuid', $this->currentUser->uuid)->delete();
            $this->currentUser->delete();
            $this->currentUser = null;
        }
    }
}
