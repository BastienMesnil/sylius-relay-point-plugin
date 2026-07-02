<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin\Unit\RelayPoint;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\SelectedRelayPoint;
use PHPUnit\Framework\TestCase;

final class SelectedRelayPointTest extends TestCase
{
    public function testRoundTripArraySerialization(): void
    {
        $original = new SelectedRelayPoint(
            id: 'P12345',
            name: 'Tabac du coin',
            street: '10 rue de la Paix',
            postcode: '75001',
            city: 'Paris',
            countryCode: 'FR',
            latitude: 48.869,
            longitude: 2.331,
            carrierCode: 'mondial_relay',
            shippingMethodCode: 'mondial_relay_fr',
            distanceInMeters: 450,
            openingHours: [['day' => 'Lundi', 'hours' => '09:00-19:00']],
        );

        $restored = SelectedRelayPoint::fromArray($original->toArray());

        self::assertSame($original->id, $restored->id);
        self::assertSame($original->name, $restored->name);
        self::assertSame($original->street, $restored->street);
        self::assertSame($original->postcode, $restored->postcode);
        self::assertSame($original->city, $restored->city);
        self::assertSame($original->countryCode, $restored->countryCode);
        self::assertSame($original->latitude, $restored->latitude);
        self::assertSame($original->longitude, $restored->longitude);
        self::assertSame($original->carrierCode, $restored->carrierCode);
        self::assertSame($original->shippingMethodCode, $restored->shippingMethodCode);
        self::assertSame($original->distanceInMeters, $restored->distanceInMeters);
        self::assertSame($original->openingHours, $restored->openingHours);
    }

    public function testFromArrayWithMissingOptionalFields(): void
    {
        $point = SelectedRelayPoint::fromArray([
            'id' => 'X1',
            'name' => 'Relay',
            'street' => '1 rue',
            'postcode' => '69001',
            'city' => 'Lyon',
            'countryCode' => 'FR',
            'latitude' => 45.75,
            'longitude' => 4.83,
            'carrierCode' => 'colissimo',
            'shippingMethodCode' => 'colissimo_fr',
        ]);

        self::assertNull($point->distanceInMeters);
        self::assertSame([], $point->openingHours);
    }

    public function testToArrayContainsAllKeys(): void
    {
        $point = new SelectedRelayPoint(
            id: 'A1', name: 'N', street: 'S', postcode: '13001',
            city: 'Marseille', countryCode: 'FR',
            latitude: 43.3, longitude: 5.37,
            carrierCode: 'inpost', shippingMethodCode: 'inpost_fr',
        );

        $array = $point->toArray();

        foreach (['id', 'name', 'street', 'postcode', 'city', 'countryCode',
                  'latitude', 'longitude', 'carrierCode', 'shippingMethodCode',
                  'distanceInMeters', 'openingHours'] as $key) {
            self::assertArrayHasKey($key, $array);
        }
    }
}
