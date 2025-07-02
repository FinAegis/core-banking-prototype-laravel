@web
Feature: Financial Institutions Application
  In order to partner with FinAegis
  As a financial institution
  I need to be able to submit a partnership application

  Background:
    Given I am on the homepage

  Scenario: View partnership requirements
    When I visit "/financial-institutions/apply"
    Then I should see "Partner Institution Application"
    And I should see "Partnership Requirements"
    And I should see "Technical Requirements"
    And I should see "Juridical Requirements"
    And I should see "Financial Requirements"
    And I should see "Insurance Requirements"

  Scenario: Submit application form with valid data
    Given I am on "/financial-institutions/apply"
    When I fill in "institution_name" with "Test Bank Ltd"
    And I fill in "country" with "Germany"
    And I fill in "contact_name" with "John Doe"
    And I fill in "contact_email" with "john.doe@testbank.com"
    And I fill in "technical_capabilities" with "We have modern REST APIs with OAuth 2.0"
    And I fill in "regulatory_compliance" with "Fully licensed by BaFin with EU passporting"
    And I fill in "financial_strength" with "€500M AUM, A+ rating"
    And I fill in "insurance_coverage" with "Complete deposit insurance coverage"
    And I check "terms"
    And I press "Submit Application"
    Then I should see "Thank you for your application"
    And I should be on "/financial-institutions/apply"

  Scenario: Submit application form without required fields
    Given I am on "/financial-institutions/apply"
    When I press "Submit Application"
    Then I should see "This field is required"
    And I should be on "/financial-institutions/apply"

  Scenario: Submit application without accepting terms
    Given I am on "/financial-institutions/apply"
    When I fill in "institution_name" with "Test Bank Ltd"
    And I fill in "country" with "Germany"
    And I fill in "contact_name" with "John Doe"
    And I fill in "contact_email" with "john.doe@testbank.com"
    And I fill in "technical_capabilities" with "We have modern REST APIs"
    And I fill in "regulatory_compliance" with "Fully licensed"
    And I fill in "financial_strength" with "€500M AUM"
    And I fill in "insurance_coverage" with "Complete coverage"
    And I press "Submit Application"
    Then I should see "Please accept the terms"
    And I should be on "/financial-institutions/apply"

  Scenario: Check all requirement sections are displayed
    Given I am on "/financial-institutions/apply"
    Then I should see the following requirements:
      | requirement_type | items_count |
      | Technical        | 5           |
      | Juridical        | 5           |
      | Financial        | 5           |
      | Insurance        | 5           |
    And I should see "Modern API infrastructure"
    And I should see "Valid banking license"
    And I should see "Minimum €100M in assets"
    And I should see "Deposit insurance scheme"