<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * API Context for testing REST endpoints
 */
class ApiContext implements Context
{
    private $headers = [];
    private $payload = [];
    private $response;
    private $baseUrl;
    private $currentUser;
    private $bearerToken;

    /**
     * @BeforeScenario
     */
    public function setUp()
    {
        $this->baseUrl = config('app.url', 'http://localhost:8000');
        $this->headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @Given I am authenticated as :email
     */
    public function iAmAuthenticatedAs($email)
    {
        $user = User::where('email', $email)->firstOrFail();
        $this->currentUser = $user;
        
        // Create a Sanctum token
        $token = $user->createToken('behat-test')->plainTextToken;
        $this->bearerToken = $token;
        $this->headers['Authorization'] = 'Bearer ' . $token;
    }

    /**
     * @Given I have the following headers:
     */
    public function iHaveTheFollowingHeaders(TableNode $table)
    {
        foreach ($table->getRowsHash() as $key => $value) {
            $this->headers[$key] = $value;
        }
    }

    /**
     * @Given I have the following payload:
     */
    public function iHaveTheFollowingPayload(PyStringNode $payload)
    {
        $this->payload = json_decode($payload->getRaw(), true);
    }

    /**
     * @When I send a :method request to :endpoint
     */
    public function iSendARequestTo($method, $endpoint)
    {
        $url = $this->baseUrl . $endpoint;
        
        switch (strtoupper($method)) {
            case 'GET':
                $this->response = $this->get($url);
                break;
            case 'POST':
                $this->response = $this->post($url, $this->payload);
                break;
            case 'PUT':
                $this->response = $this->put($url, $this->payload);
                break;
            case 'DELETE':
                $this->response = $this->delete($url);
                break;
            default:
                throw new \Exception("Unsupported HTTP method: $method");
        }
    }

    /**
     * @Then the response status code should be :code
     */
    public function theResponseStatusCodeShouldBe($code)
    {
        $actualCode = $this->response->getStatusCode();
        if ($actualCode != $code) {
            throw new \Exception(
                "Expected status code $code but got $actualCode. Response: " . 
                $this->response->getContent()
            );
        }
    }

    /**
     * @Then the response should contain:
     */
    public function theResponseShouldContain(PyStringNode $expectedJson)
    {
        $expected = json_decode($expectedJson->getRaw(), true);
        $actual = $this->response->json();
        
        $this->assertArrayContains($expected, $actual);
    }

    /**
     * @Then the response should have a :key field
     */
    public function theResponseShouldHaveAField($key)
    {
        $data = $this->response->json();
        
        if (!array_key_exists($key, $data)) {
            throw new \Exception("Response does not have field: $key");
        }
    }

    /**
     * @Then the response :key field should equal :value
     */
    public function theResponseFieldShouldEqual($key, $value)
    {
        $data = $this->response->json();
        
        if (!isset($data[$key])) {
            throw new \Exception("Response does not have field: $key");
        }
        
        if ($data[$key] != $value) {
            throw new \Exception(
                "Expected $key to be '$value' but got '{$data[$key]}'"
            );
        }
    }

    /**
     * @Then the response should be empty
     */
    public function theResponseShouldBeEmpty()
    {
        $content = $this->response->getContent();
        if (!empty($content) && $content !== '[]' && $content !== '{}') {
            throw new \Exception("Expected empty response but got: $content");
        }
    }

    /**
     * @Then I save the response :field as :name
     */
    public function iSaveTheResponseFieldAs($field, $name)
    {
        $data = $this->response->json();
        
        if (!isset($data[$field])) {
            throw new \Exception("Response does not have field: $field");
        }
        
        // Store in a static property for use across scenarios
        self::$savedData[$name] = $data[$field];
    }

    /**
     * @Transform :savedValue
     */
    public function transformSavedValue($savedValue)
    {
        if (preg_match('/^<(.+)>$/', $savedValue, $matches)) {
            $key = $matches[1];
            if (!isset(self::$savedData[$key])) {
                throw new \Exception("No saved value found for: $key");
            }
            return self::$savedData[$key];
        }
        
        return $savedValue;
    }

    private static $savedData = [];

    private function get($url)
    {
        return TestResponse::fromBaseResponse(
            app()->handle(
                \Illuminate\Http\Request::create($url, 'GET', [], [], [], $this->transformHeaders($this->headers))
            )
        );
    }

    private function post($url, $data)
    {
        return TestResponse::fromBaseResponse(
            app()->handle(
                \Illuminate\Http\Request::create(
                    $url, 
                    'POST', 
                    [], 
                    [], 
                    [], 
                    $this->transformHeaders($this->headers),
                    json_encode($data)
                )
            )
        );
    }

    private function put($url, $data)
    {
        return TestResponse::fromBaseResponse(
            app()->handle(
                \Illuminate\Http\Request::create(
                    $url, 
                    'PUT', 
                    [], 
                    [], 
                    [], 
                    $this->transformHeaders($this->headers),
                    json_encode($data)
                )
            )
        );
    }

    private function delete($url)
    {
        return TestResponse::fromBaseResponse(
            app()->handle(
                \Illuminate\Http\Request::create($url, 'DELETE', [], [], [], $this->transformHeaders($this->headers))
            )
        );
    }

    private function transformHeaders($headers)
    {
        $transformed = [];
        foreach ($headers as $key => $value) {
            $key = 'HTTP_' . str_replace('-', '_', strtoupper($key));
            $transformed[$key] = $value;
        }
        return $transformed;
    }

    private function assertArrayContains($expected, $actual, $path = '')
    {
        foreach ($expected as $key => $value) {
            $currentPath = $path ? "$path.$key" : $key;
            
            if (!array_key_exists($key, $actual)) {
                throw new \Exception("Key '$currentPath' not found in response");
            }
            
            if (is_array($value)) {
                $this->assertArrayContains($value, $actual[$key], $currentPath);
            } else {
                if ($actual[$key] != $value) {
                    throw new \Exception(
                        "Expected '$currentPath' to be '$value' but got '{$actual[$key]}'"
                    );
                }
            }
        }
    }
}