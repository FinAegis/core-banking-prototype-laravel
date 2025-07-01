<?php

namespace Tests\Behat\Contexts;

use App\Models\User;
use App\Models\Account;
use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Assert;

class AccountCreationContext extends MinkContext implements Context
{
    private $currentUser;

    /**
     * @Given I am logged in as a user
     */
    public function iAmLoggedInAsAUser()
    {
        $this->currentUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        $this->visit('/login');
        $this->fillField('email', 'test@example.com');
        $this->fillField('password', 'password');
        $this->pressButton('Log in');
        
        // Wait for redirect
        $this->getSession()->wait(2000);
        
        Assert::assertStringContainsString('/dashboard', $this->getSession()->getCurrentUrl());
    }

    /**
     * @Given I have an account named :accountName
     */
    public function iHaveAnAccountNamed($accountName)
    {
        if (!$this->currentUser) {
            $this->iAmLoggedInAsAUser();
        }

        Account::factory()->create([
            'user_uuid' => $this->currentUser->uuid,
            'name' => $accountName,
            'balance' => 0
        ]);
    }

    /**
     * @When I click :text
     */
    public function iClick($text)
    {
        $button = $this->getSession()->getPage()->findButton($text);
        if (!$button) {
            $link = $this->getSession()->getPage()->findLink($text);
            if (!$link) {
                throw new \Exception("Could not find button or link with text: $text");
            }
            $link->click();
        } else {
            $button->click();
        }
    }

    /**
     * @Then I should see :text in the modal
     */
    public function iShouldSeeInTheModal($text)
    {
        $modal = $this->getSession()->getPage()->find('css', '#accountModal');
        Assert::assertNotNull($modal, 'Modal not found');
        Assert::assertStringContainsString($text, $modal->getText());
    }

    /**
     * @When I press :button in the modal
     */
    public function iPressInTheModal($button)
    {
        $modal = $this->getSession()->getPage()->find('css', '#accountModal');
        $btn = $modal->findButton($button);
        Assert::assertNotNull($btn, "Button '$button' not found in modal");
        $btn->click();
        
        // Wait for AJAX request to complete
        $this->getSession()->wait(3000);
    }

    /**
     * @When I clear :field
     */
    public function iClear($field)
    {
        $this->getSession()->getPage()->fillField($field, '');
    }

    /**
     * @Then I should see an error message
     */
    public function iShouldSeeAnErrorMessage()
    {
        $errorDiv = $this->getSession()->getPage()->find('css', '#accountError');
        Assert::assertNotNull($errorDiv);
        Assert::assertFalse($errorDiv->hasClass('hidden'), 'Error message is hidden');
    }

    /**
     * @Then I should still see the modal
     */
    public function iShouldStillSeeTheModal()
    {
        $modal = $this->getSession()->getPage()->find('css', '#accountModal');
        Assert::assertNotNull($modal);
        Assert::assertFalse($modal->hasClass('hidden'), 'Modal is hidden');
    }

    /**
     * @AfterScenario
     */
    public function cleanup()
    {
        // Clean up test data
        if ($this->currentUser) {
            Account::where('user_uuid', $this->currentUser->uuid)->delete();
            $this->currentUser->delete();
            $this->currentUser = null;
        }
    }
}