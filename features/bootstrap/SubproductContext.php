<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\MinkExtension\Context\MinkContext;
use PHPUnit\Framework\Assert;

/**
 * Defines application features from the subproduct pages context.
 */
class SubproductContext extends MinkContext implements Context
{
    /**
     * @Given the application is configured properly
     */
    public function theApplicationIsConfiguredProperly()
    {
        // Clear any caches that might cause route issues
        exec('php artisan route:clear');
        exec('php artisan config:clear');
        exec('php artisan view:clear');
    }

    /**
     * @Then I should see a link to :text pointing to :url
     */
    public function iShouldSeeALinkToPointingTo($text, $url)
    {
        $page = $this->getSession()->getPage();
        $link = $page->findLink($text);
        
        Assert::assertNotNull($link, "Link with text '$text' not found");
        
        $href = $link->getAttribute('href');
        Assert::assertStringContainsString($url, $href, "Link does not point to expected URL");
    }

    /**
     * @Then I should see the main navigation
     */
    public function iShouldSeeTheMainNavigation()
    {
        $this->assertElementOnPage('nav');
        // Check for common navigation elements
        $this->assertPageContainsText('Features');
        $this->assertPageContainsText('Platform');
    }

    /**
     * @Then I should see the footer
     */
    public function iShouldSeeTheFooter()
    {
        $this->assertElementOnPage('footer');
    }

    /**
     * @Then I should see links to all subproducts
     */
    public function iShouldSeeLinksToAllSubproducts()
    {
        $this->assertPageContainsText('FinAegis Exchange');
        $this->assertPageContainsText('FinAegis Lending');
        $this->assertPageContainsText('FinAegis Stablecoins');
        $this->assertPageContainsText('FinAegis Treasury');
    }

    /**
     * @Then the :text link should point to :url
     */
    public function theLinkShouldPointTo($text, $url)
    {
        $page = $this->getSession()->getPage();
        
        // Find all links that contain the text
        $links = $page->findAll('xpath', "//a[contains(., '$text')]");
        
        $found = false;
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (strpos($href, $url) !== false) {
                $found = true;
                break;
            }
        }
        
        Assert::assertTrue($found, "No link containing '$text' points to '$url'");
    }

    /**
     * @Given I am logged in as a user
     */
    public function iAmLoggedInAsAUser()
    {
        // Create a test user if needed
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Visit login page
        $this->visit('/login');
        $this->fillField('email', 'test@example.com');
        $this->fillField('password', 'password');
        $this->pressButton('Log in');
        
        // Wait for redirect
        $this->getSession()->wait(2000);
    }

    /**
     * @When I click on :text
     */
    public function iClickOn($text)
    {
        $this->clickLink($text);
    }

    /**
     * @Then I should be on :path
     */
    public function iShouldBeOn($path)
    {
        $currentPath = parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH);
        Assert::assertEquals($path, $currentPath, "Current path is $currentPath, expected $path");
    }
}