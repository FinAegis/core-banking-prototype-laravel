<?php

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;

/**
 * Web feature context for browser-based testing
 */
class WebContext extends MinkContext implements Context
{
    /**
     * @Given I am on the homepage
     */
    public function iAmOnTheHomepage()
    {
        $this->visit('/');
    }

    /**
     * @Then I should see the following requirements:
     */
    public function iShouldSeeTheFollowingRequirements(TableNode $table)
    {
        $page = $this->getSession()->getPage();
        
        foreach ($table->getHash() as $row) {
            $requirementType = $row['requirement_type'];
            $itemsCount = (int) $row['items_count'];
            
            // Find the section with this requirement type
            $section = $page->find('xpath', "//h3[contains(text(), '{$requirementType} Requirements')]");
            Assert::assertNotNull($section, "Could not find {$requirementType} Requirements section");
            
            // Count the list items in this section
            $parentDiv = $section->getParent();
            $listItems = $parentDiv->findAll('css', 'li');
            Assert::assertCount($itemsCount, $listItems, "{$requirementType} Requirements should have {$itemsCount} items");
        }
    }

    /**
     * @When I check :checkbox
     */
    public function iCheck($checkbox)
    {
        $this->checkOption($checkbox);
    }

    /**
     * @Then I should see :text
     */
    public function iShouldSee($text)
    {
        $this->assertPageContainsText($text);
    }

    /**
     * @Then I should not see :text
     */
    public function iShouldNotSee($text)
    {
        $this->assertPageNotContainsText($text);
    }

    /**
     * @Then I should be on :path
     */
    public function iShouldBeOn($path)
    {
        $this->assertSession()->addressEquals($path);
    }

    /**
     * @Given I am on :path
     */
    public function iAmOn($path)
    {
        $this->visit($path);
    }

    /**
     * @When I fill in :field with :value
     */
    public function iFillIn($field, $value)
    {
        $this->fillField($field, $value);
    }

    /**
     * @When I press :button
     */
    public function iPress($button)
    {
        $this->pressButton($button);
    }

    /**
     * @When I visit :path
     */
    public function iVisit($path)
    {
        $this->visit($path);
    }
}