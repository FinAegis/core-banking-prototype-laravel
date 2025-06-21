Feature: Exchange Rate API
  In order to convert between currencies
  As an API consumer
  I need to be able to query exchange rates

  Background:
    Given I am authenticated as "api@example.com"
    And the following exchange rates exist:
      | from | to  | rate | provider |
      | USD  | EUR | 0.85 | ECB      |
      | EUR  | USD | 1.18 | ECB      |
      | USD  | GBP | 0.73 | ECB      |
      | GBP  | USD | 1.37 | ECB      |

  Scenario: Get exchange rate between currencies
    When I send a GET request to "/api/v1/exchange-rates/USD/EUR"
    Then the response status code should be 200
    And the response should contain:
      """
      {
        "from_asset_code": "USD",
        "to_asset_code": "EUR",
        "rate": 0.85,
        "provider": "ECB"
      }
      """

  Scenario: Convert amount between currencies
    When I send a GET request to "/api/v1/exchange-rates/USD/EUR/convert?amount=100"
    Then the response status code should be 200
    And the response should contain:
      """
      {
        "from_asset_code": "USD",
        "to_asset_code": "EUR",
        "from_amount": 100,
        "to_amount": 85,
        "rate": 0.85
      }
      """

  Scenario: List all exchange rates
    When I send a GET request to "/api/v1/exchange-rates"
    Then the response status code should be 200
    And the response should have a "data" field
    And the response data should contain 4 exchange rates

  Scenario: Exchange rate not found
    When I send a GET request to "/api/v1/exchange-rates/USD/JPY"
    Then the response status code should be 404
    And the response should contain:
      """
      {
        "message": "Exchange rate not found"
      }
      """