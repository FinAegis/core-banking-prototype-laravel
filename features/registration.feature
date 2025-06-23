Feature: User Registration
  In order to use the FinAegis platform
  As a new user
  I need to be able to register for an account

  Background:
    Given I am on the homepage
    And registration is enabled

  Scenario: Viewing registration page
    When I click on "Register"
    Then I should see "Create Account"
    And I should see "Name" field
    And I should see "Email" field
    And I should see "Password" field
    And I should see "Confirm Password" field

  Scenario: Successful registration as individual user
    Given I am on the registration page
    When I fill in "Name" with "John Doe"
    And I fill in "Email" with "john@example.com"
    And I fill in "Password" with "Password123!"
    And I fill in "Confirm Password" with "Password123!"
    And I check "I agree to the Terms of Service"
    And I press "Register"
    Then I should be redirected to "/dashboard"
    And I should see "Welcome to FinAegis!"
    And I should see the onboarding wizard

  Scenario: Successful registration as business user
    Given I am on the registration page
    When I fill in "Name" with "Acme Corporation"
    And I fill in "Email" with "admin@acme.com"
    And I check "Business Account"
    And I fill in "Password" with "Password123!"
    And I fill in "Confirm Password" with "Password123!"
    And I check "I agree to the Terms of Service"
    And I press "Register"
    Then I should be redirected to "/dashboard"
    And I should see "Welcome to FinAegis!"
    And I should see the onboarding wizard

  Scenario: Registration with existing email
    Given I am on the registration page
    And a user exists with email "existing@example.com"
    When I fill in "Name" with "Jane Doe"
    And I fill in "Email" with "existing@example.com"
    And I fill in "Password" with "Password123!"
    And I fill in "Confirm Password" with "Password123!"
    And I check "I agree to the Terms of Service"
    And I press "Register"
    Then I should see "The email has already been taken"
    And I should remain on the registration page

  Scenario: Registration with password mismatch
    Given I am on the registration page
    When I fill in "Name" with "Jane Doe"
    And I fill in "Email" with "jane@example.com"
    And I fill in "Password" with "Password123!"
    And I fill in "Confirm Password" with "DifferentPassword123!"
    And I check "I agree to the Terms of Service"
    And I press "Register"
    Then I should see "The password field confirmation does not match"
    And I should remain on the registration page

  Scenario: Registration without accepting terms
    Given I am on the registration page
    When I fill in "Name" with "Jane Doe"
    And I fill in "Email" with "jane@example.com"
    And I fill in "Password" with "Password123!"
    And I fill in "Confirm Password" with "Password123!"
    And I press "Register"
    Then I should see "You must accept the Terms of Service"
    And I should remain on the registration page

  Scenario: Onboarding wizard after registration
    Given I have just registered as "john@example.com"
    When I am redirected to the dashboard
    Then I should see the welcome modal
    And the modal should show "Welcome to FinAegis!"
    And I should see the following onboarding steps:
      | Step | Title                       | Status    |
      | 1    | Account Created            | completed |
      | 2    | Complete KYC Verification  | pending   |
      | 3    | Fund Your Account          | pending   |
      | 4    | Explore Features           | pending   |
    And I should see "Skip for Now" button
    And I should see "Start Setup" button

  Scenario: Starting onboarding process
    Given I have just registered
    And I see the welcome modal
    When I click "Start Setup"
    Then I should be directed to the KYC verification page
    And I should see "Verify Your Identity"
    And I should see "This helps us keep your account secure"

  Scenario: Skipping onboarding
    Given I have just registered
    And I see the welcome modal
    When I click "Skip for Now"
    Then the modal should close
    And I should see the dashboard
    And I should not see the welcome modal again on refresh