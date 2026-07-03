@relay_point
Feature: Relay point search API
    In order to let customers choose a pickup location
    As a developer using the plugin
    I need the relay point API endpoints to respond correctly

    @relay_point_search
    Scenario: Search without required parameter returns 400
        When I request the relay point search endpoint without parameters
        Then the response status code should be 400
        And the response should be valid JSON
        And the JSON response should contain an error about "shipping_method_code"

    @relay_point_search
    Scenario: Search with stub provider returns relay points
        When I search relay points for shipping method "stub_relay_standard" with postcode "75001" and country "FR"
        Then the response status code should be 200
        And the response should be valid JSON
        And the JSON response should contain 2 relay points
        And the first relay point should have id "STUB001"
        And the first relay point should have opening hours

    @relay_point_search
    Scenario: Search for unknown shipping method returns empty list
        When I search relay points for shipping method "unknown_method" with postcode "75001" and country "FR"
        Then the response status code should be 200
        And the response should be valid JSON
        And the JSON response should contain 0 relay points

    @relay_point_geocode
    Scenario: Geocode without query returns 400
        When I request the geocode endpoint without a query
        Then the response status code should be 400
        And the response should be valid JSON
        And the JSON response should contain an error about "q"

    @relay_point_select
    Scenario: Select a relay point persists it in session
        When I select a relay point with method "stub_relay_standard" and id "STUB001"
        Then the response status code should be 200
        And the response should be valid JSON
        And the JSON response should confirm success

    @relay_point_select
    Scenario: Select with missing body returns 400
        When I request the relay point search endpoint without parameters
        Then the response status code should be 400

    @relay_point_clear
    Scenario: Clear relay point returns success
        When I clear the relay point for cart "test-token-abc"
        Then the response status code should be 200
        And the response should be valid JSON
        And the JSON response should confirm success
