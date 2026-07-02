<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Provider\Colissimo;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\OpeningHours;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;
use Psr\Log\LoggerInterface;
use SoapClient;
use Throwable;
use function date;
use function in_array;
use function is_array;
use function max;
use function round;
use function strtr;
use function trim;

/**
 * Searches Colissimo (La Poste) pickup points via the SOAP PointRetraitServiceWS.
 *
 * Credentials: accountNumber + password provided by Colissimo.
 * The filterRelay param accepts: 'A' (all), 'P' (relay points), 'C' (lockers/consignes).
 */
final class ColissimoProvider implements RelayPointProviderInterface
{
    private const WSDL_URL = 'https://ws.colissimo.fr/pointretrait-ws-cxf/PointRetraitServiceWS?wsdl';

    private const CARRIER_CODE = 'colissimo';

    /**
     * @param string[] $shippingMethodCodes Sylius shipping method codes routed to this provider.
     */
    public function __construct(
        private readonly string $accountNumber,
        private readonly string $password,
        private readonly array $shippingMethodCodes,
        private readonly LoggerInterface $logger,
        private readonly string $filterRelay = 'A',
    ) {
    }

    public function supports(string $shippingMethodCode): bool
    {
        return in_array($shippingMethodCode, $this->shippingMethodCodes, true);
    }

    public function search(RelayPointSearchCriteria $criteria): array
    {
        try {
            $client = new SoapClient(self::WSDL_URL, ['trace' => false, 'exception' => true]);

            $params = [
                'accountNumber' => $this->accountNumber,
                'password' => $this->password,
                'address' => '',
                'zipCode' => $criteria->postcode ?? '',
                'city' => $this->normalize($criteria->city) ?? '',
                'countryCode' => $criteria->countryCode ?? 'FR',
                'optionInter' => 0,
                'reseau' => '',
                'filterRelay' => $this->filterRelay,
                'lang' => 'FR',
                'weight' => 1000,
                'shippingDate' => date('d/m/Y'),
                'maxPointChronopost' => 0,
                'maxDistanceSearch' => $this->radiusInKm($criteria->radiusInMeters),
                'latitude' => $criteria->latitude ?? '',
                'longitude' => $criteria->longitude ?? '',
                'requestId' => '',
            ];

            $result = $client->findRDVPointRetraitAcheminement($params);
        } catch (Throwable $e) {
            $this->logger->error('Colissimo search error: ' . $e->getMessage());

            return [];
        }

        $errorCode = (int) ($result->return->errorCode ?? -1);
        if (0 !== $errorCode) {
            $this->logger->error(sprintf(
                'Colissimo API error %d: %s',
                $errorCode,
                $result->return->errorMessage ?? '',
            ));

            return [];
        }

        if (!isset($result->return->listePointRetraitAcheminement)) {
            return [];
        }

        $list = $result->return->listePointRetraitAcheminement;
        if (!is_array($list)) {
            $list = [$list];
        }

        $points = [];
        foreach ($list as $point) {
            $points[] = new RelayPoint(
                id: (string) ($point->identifiant ?? ''),
                name: (string) ($point->nom ?? ''),
                street: trim(((string) ($point->adresse1 ?? '')) . ' ' . ((string) ($point->adresse2 ?? ''))),
                postcode: (string) ($point->codePostal ?? ''),
                city: (string) ($point->localite ?? ''),
                countryCode: $criteria->countryCode ?? 'FR',
                latitude: (float) ($point->coordGeolocalisationLatitude ?? 0),
                longitude: (float) ($point->coordGeolocalisationLongitude ?? 0),
                distanceInMeters: isset($point->distanceEnMetre) ? (int) $point->distanceEnMetre : null,
                openingHours: $this->parseOpeningHours($point),
                carrierCode: self::CARRIER_CODE,
            );
        }

        return $points;
    }

    private function parseOpeningHours(object $point): array
    {
        if (!isset($point->listeHoraireOuverture)) {
            return [];
        }

        $horaires = is_array($point->listeHoraireOuverture)
            ? $point->listeHoraireOuverture
            : [$point->listeHoraireOuverture];

        $days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

        $openingHours = [];
        foreach ($horaires as $h) {
            $dayNum = (int) ($h->jour ?? 0);
            if (!isset($days[$dayNum])) {
                continue;
            }

            $openingHours[] = new OpeningHours(
                day: $days[$dayNum],
                hours: (string) ($h->horairesAsString ?? ''),
            );
        }

        return $openingHours;
    }

    private function radiusInKm(?int $radiusInMeters): int
    {
        if (null === $radiusInMeters) {
            return 20;
        }

        return max(1, (int) round($radiusInMeters / 1000));
    }

    private function normalize(?string $string): ?string
    {
        if (null === $string) {
            return null;
        }

        return strtr($string, [
            'Š' => 'S', 'š' => 's', 'Đ' => 'Dj', 'đ' => 'dj', 'Ž' => 'Z', 'ž' => 'z', 'Č' => 'C', 'č' => 'c', 'Ć' => 'C', 'ć' => 'c',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
            'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O',
            'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e',
            'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o',
            'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'b',
            'ÿ' => 'y', 'Ŕ' => 'R', 'ŕ' => 'r', '&' => '',
        ]);
    }
}
