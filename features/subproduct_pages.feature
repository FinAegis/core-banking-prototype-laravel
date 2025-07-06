Feature: Subproduct Pages Navigation
  In order to explore different financial products
  As a visitor or user
  I need to be able to access all subproduct pages without errors

  Background:
    Given the application is configured properly

  Scenario: Access Exchange subproduct page as a visitor
    When I visit "/subproducts/exchange"
    Then the response status code should be 200
    And I should see "FinAegis Exchange"
    And I should see "Professional trading platform"
    And I should see "Now Live"
    And I should not see "Route [exchange.index] not defined"

  Scenario: Access Lending subproduct page as a visitor
    When I visit "/subproducts/lending"
    Then the response status code should be 200
    And I should see "FinAegis Lending"
    And I should see "P2P lending marketplace"
    And I should see "Now Live"
    And I should not see "Route [lending.index] not defined"
    And I should not see "Route [loans.index] not defined"

  Scenario: Access Stablecoins subproduct page as a visitor
    When I visit "/subproducts/stablecoins"
    Then the response status code should be 200
    And I should see "FinAegis Stablecoins"
    And I should see "EUR-pegged digital currency"
    And I should see "Now Live"
    And I should not see "Route [stablecoins.index] not defined"

  Scenario: Access Treasury subproduct page as a visitor
    When I visit "/subproducts/treasury"
    Then the response status code should be 200
    And I should see "FinAegis Treasury"
    And I should see "Multi-bank cash management"
    And I should see "Coming Soon"
    And I should not see "Route [treasury.index] not defined"

  @authenticated
  Scenario: Exchange CTA button works for authenticated users
    Given I am logged in as a user
    When I visit "/subproducts/exchange"
    And I click on "Start Trading"
    Then I should be on "/exchange"
    And the response status code should be 200

  @authenticated
  Scenario: Lending CTA button works for authenticated users
    Given I am logged in as a user
    When I visit "/subproducts/lending"
    And I click on "Start Lending or Borrowing"
    Then I should be on "/lending"
    And the response status code should be 200

  @authenticated
  Scenario: Stablecoins CTA button works for authenticated users
    Given I am logged in as a user
    When I visit "/subproducts/stablecoins"
    And I click on "Get Started with EURS"
    Then I should be on "/dashboard"
    And the response status code should be 200

  Scenario: All subproduct pages have working GCU links
    When I visit "/subproducts/exchange"
    Then I should see a link to "Global Currency Unit" pointing to "/gcu"
    When I visit "/subproducts/lending"
    Then I should see a link to "Global Currency Unit" pointing to "/gcu"
    When I visit "/subproducts/stablecoins"
    Then I should see a link to "Global Currency Unit" pointing to "/gcu"
    When I visit "/subproducts/treasury"
    Then I should see a link to "Global Currency Unit" pointing to "/gcu"

  Scenario: All subproduct pages have consistent navigation
    When I visit "/subproducts/exchange"
    Then I should see the main navigation
    And I should see the footer
    When I visit "/subproducts/lending"
    Then I should see the main navigation
    And I should see the footer
    When I visit "/subproducts/stablecoins"
    Then I should see the main navigation
    And I should see the footer
    When I visit "/subproducts/treasury"
    Then I should see the main navigation
    And I should see the footer

  Scenario: Subproduct pages are accessible from the homepage
    When I visit "/"
    Then I should see links to all subproducts
    And the "FinAegis Exchange" link should point to "/subproducts/exchange"
    And the "FinAegis Lending" link should point to "/subproducts/lending"
    And the "FinAegis Stablecoins" link should point to "/subproducts/stablecoins"
    And the "FinAegis Treasury" link should point to "/subproducts/treasury"