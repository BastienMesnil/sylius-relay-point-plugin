<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\MinkExtension\Context\RawMinkContext;

final class RelayPointContext extends RawMinkContext implements Context
{
    /**
     * @When I request the relay point search endpoint without parameters
     */
    public function iRequestRelayPointSearchWithoutParameters(): void
    {
        $this->getSession()->visit('/fr/relay-points/search');
    }

    /**
     * @When I search relay points for shipping method :code with postcode :postcode and country :country
     */
    public function iSearchRelayPointsForMethod(string $code, string $postcode, string $country): void
    {
        $url = sprintf(
            '/fr/relay-points/search?shipping_method_code=%s&postcode=%s&country_code=%s',
            urlencode($code),
            urlencode($postcode),
            urlencode($country),
        );

        $this->getSession()->visit($url);
    }

    /**
     * @When I request the geocode endpoint without a query
     */
    public function iRequestGeocodeWithoutQuery(): void
    {
        $this->getSession()->visit('/fr/relay-points/geocode');
    }

    /**
     * @When I geocode the address :address
     */
    public function iGeocodeAddress(string $address): void
    {
        $this->getSession()->visit('/fr/relay-points/geocode?q=' . urlencode($address));
    }

    /**
     * @When I select a relay point with method :method and id :id
     */
    public function iSelectRelayPoint(string $method, string $id): void
    {
        $driver = $this->getSession()->getDriver();
        assert($driver instanceof BrowserKitDriver);
        $client = $driver->getClient();

        $body = json_encode([
            'shippingMethodCode' => $method,
            'cartToken' => 'test-token-123',
            'point' => [
                'id' => $id,
                'name' => 'Test Point',
                'street' => '10 Rue Test',
                'postcode' => '75001',
                'city' => 'Paris',
                'countryCode' => 'FR',
                'latitude' => 48.8698,
                'longitude' => 2.3309,
                'carrierCode' => 'stub',
            ],
        ], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/fr/relay-points/select',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            $body,
        );
    }

    /**
     * @When I clear the relay point for cart :cartToken
     */
    public function iClearRelayPoint(string $cartToken): void
    {
        $driver = $this->getSession()->getDriver();
        assert($driver instanceof BrowserKitDriver);
        $client = $driver->getClient();

        $body = json_encode(['cartToken' => $cartToken], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/fr/relay-points/clear',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $body,
        );
    }

    /**
     * @Then the response status code should be :code
     */
    public function theResponseStatusCodeShouldBe(int $code): void
    {
        $actual = $this->getSession()->getStatusCode();

        if ($actual !== $code) {
            throw new \RuntimeException(sprintf(
                'Expected status %d but got %d. Response body: %s',
                $code,
                $actual,
                $this->getSession()->getPage()->getContent(),
            ));
        }
    }

    /**
     * @Then the response should be valid JSON
     */
    public function theResponseShouldBeValidJson(): void
    {
        $content = $this->getSession()->getPage()->getContent();
        json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @Then the JSON response should contain :count relay points
     */
    public function theJsonResponseShouldContainRelayPoints(int $count): void
    {
        $content = $this->getSession()->getPage()->getContent();
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \RuntimeException('Expected a JSON array in the response');
        }

        if (count($data) !== $count) {
            throw new \RuntimeException(sprintf(
                'Expected %d relay points but got %d. Response: %s',
                $count,
                count($data),
                $content,
            ));
        }
    }

    /**
     * @Then the JSON response should contain an error about :field
     */
    public function theJsonResponseShouldContainErrorAbout(string $field): void
    {
        $content = $this->getSession()->getPage()->getContent();
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['error']) || !str_contains((string) $data['error'], $field)) {
            throw new \RuntimeException(sprintf(
                'Expected error mentioning "%s" but got: %s',
                $field,
                $content,
            ));
        }
    }

    /**
     * @Then the JSON response should confirm success
     */
    public function theJsonResponseShouldConfirmSuccess(): void
    {
        $content = $this->getSession()->getPage()->getContent();
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['success']) || $data['success'] !== true) {
            throw new \RuntimeException(sprintf('Expected success:true in response but got: %s', $content));
        }
    }

    /**
     * @Then the first relay point should have id :id
     */
    public function theFirstRelayPointShouldHaveId(string $id): void
    {
        $content = $this->getSession()->getPage()->getContent();
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data) || !isset($data[0]['id'])) {
            throw new \RuntimeException(sprintf('Expected array with id field but got: %s', $content));
        }

        if ($data[0]['id'] !== $id) {
            throw new \RuntimeException(sprintf('Expected id "%s" but got "%s"', $id, $data[0]['id']));
        }
    }

    /**
     * @Then the first relay point should have opening hours
     */
    public function theFirstRelayPointShouldHaveOpeningHours(): void
    {
        $content = $this->getSession()->getPage()->getContent();
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data) || !isset($data[0]['openingHours']) || empty($data[0]['openingHours'])) {
            throw new \RuntimeException(sprintf('Expected openingHours in first relay point but got: %s', $content));
        }
    }
}
