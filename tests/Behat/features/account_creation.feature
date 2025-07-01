Feature: Account Creation
  In order to use the wallet features
  As a registered user
  I need to be able to create an account

  Background:
    Given I am logged in as a user

  @javascript
  Scenario: User creates account from wallet page
    Given I am on "/wallet"
    Then I should see "Account Setup Required"
    And I should see "You need to create an account before you can deposit funds"
    When I click "Create Account"
    Then I should see "Create Your Account" in the modal
    And I should see "This will create a multi-currency account"
    When I fill in "accountName" with "My Test Account"
    And I press "Create Account" in the modal
    Then I should not see "Account Setup Required"
    And I should see "My Test Account"
    And I should see "Account Balance"

  @javascript
  Scenario: Account creation with default name
    Given I am on "/wallet"
    When I click "Create Account"
    Then the "accountName" field should contain "Personal Account"
    When I press "Create Account" in the modal
    Then I should see "Personal Account"
    And I should see "$0.00"

  @javascript
  Scenario: Account creation error handling
    Given I am on "/wallet"
    When I click "Create Account"
    And I clear "accountName"
    And I press "Create Account" in the modal
    Then I should see an error message
    And I should still see the modal

  Scenario: User with existing account sees wallet dashboard
    Given I have an account named "Existing Account"
    When I go to "/wallet"
    Then I should not see "Account Setup Required"
    And I should see "Existing Account"
    And I should see "Total Balance"
    And I should see "Quick Actions"