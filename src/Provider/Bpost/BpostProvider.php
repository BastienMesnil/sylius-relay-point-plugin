<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Provider\Bpost;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * bpost parcel points provider.
 *
 * NOTE: bpost does not expose a public REST API for parcel point search.
 * Access is granted only to verified business partners via the OSP portal
 * (https://osp.bpost.be). Contact bpost at https://www.bpost.be/en/business
 * to request API credentials and endpoint documentation.
 *
 * This skeleton is ready to implement once you have:
 *   - The base URL provided by bpost
 *   - Your API key / OAuth credentials
 *   - The response field mapping
 */
final class BpostProvider implements RelayPointProviderInterface
{
    /**
     * @param list<string> $shippingMethodCodes
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly array $shippingMethodCodes,
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {}

    public function supports(string $shippingMethodCode): bool
    {
        return in_array($shippingMethodCode, $this->shippingMethodCodes, true);
    }

    /** @return RelayPoint[] */
    public function search(RelayPointSearchCriteria $criteria): array
    {
        // TODO: wire once bpost provides endpoint documentation.
        // Adapt field names below to the actual bpost response structure:
        //
        // $params = ['zip' => $criteria->postcode, 'language' => 'FR'];
        // if ($criteria->latitude !== null) {
        //     $params['lat'] = $criteria->latitude;
        //     $params['lng'] = $criteria->longitude;
        // }
        // $response = $this->client->request('GET', $this->baseUrl . '/parcelPoints', [
        //     'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
        //     'query'   => $params,
        // ]);
        // return array_map($this->mapPoint(...), $response->toArray()['parcelPoints'] ?? []);

        $this->logger->warning(
            'BpostProvider: not yet implemented — API access requires a bpost business agreement.',
            ['base_url' => $this->baseUrl, 'api_key_set' => $this->apiKey !== '', 'client' => get_class($this->client)],
        );

        return [];
    }

}
