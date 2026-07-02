<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Provider\Chronopost;

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
 * Handles carriers whose pickup point search is backed by the Chronopost
 * SOAP API (recherchePointChronopostInter). This covers both Chronopost
 * Pickup and Shop2Shop, which share the same endpoint but use separate
 * credentials.
 *
 * Register this class twice in the container — once per carrier sub-type —
 * via the keirontw_sylius_relay_point.providers config block. The same
 * pattern applies to any other carrier using a compatible SOAP API.
 */
final class ChronopostProvider implements RelayPointProviderInterface
{
    private const SEARCH_WSDL_URL = 'https://www.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl';

    /**
     * @param string[] $shippingMethodCodes Sylius shipping method codes handled by this instance.
     */
    public function __construct(
        private readonly string $account,
        private readonly string $password,
        private readonly array $shippingMethodCodes,
        private readonly string $carrierCode,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supports(string $shippingMethodCode): bool
    {
        return in_array($shippingMethodCode, $this->shippingMethodCodes, true);
    }

    public function search(RelayPointSearchCriteria $criteria): array
    {
        try {
            $client = new SoapClient(self::SEARCH_WSDL_URL);

            $params = [
                'accountNumber' => $this->account,
                'password' => $this->password,
                'zipCode' => $criteria->postcode ?? '',
                'city' => $this->normalize($criteria->city),
                'countryCode' => $criteria->countryCode ?? 'FR',
                'type' => 'T',
                'productCode' => '',
                'service' => 'L',
                'weight' => 1000,
                'shippingDate' => date('d/m/Y'),
                'maxPointChronopost' => $criteria->limit,
                'maxDistanceSearch' => $this->radiusInKm($criteria->radiusInMeters),
                'holidayTolerant' => 1,
                'language' => 'FR',
            ];

            $result = $client->recherchePointChronopostInter($params);
        } catch (Throwable $e) {
            $this->logger->error('Chronopost search error: ' . $e->getMessage());

            return [];
        }

        if (isset($result->return->errorCode) && $result->return->errorCode != 0) {
            $this->logger->error(sprintf(
                'Chronopost API error %s: %s',
                $result->return->errorCode,
                $result->return->errorMessage ?? '',
            ));

            return [];
        }

        if (!isset($result->return->listePointRelais)) {
            return [];
        }

        $list = $result->return->listePointRelais;
        if (!is_array($list)) {
            $list = [$list];
        }

        $points = [];
        foreach ($list as $point) {
            if (!$point->actif) {
                continue;
            }

            $points[] = new RelayPoint(
                id: (string) $point->identifiant,
                name: (string) ($point->nom ?? ''),
                street: trim(((string) ($point->adresse1 ?? '')) . ' ' . ((string) ($point->adresse2 ?? ''))),
                postcode: (string) ($point->codePostal ?? ''),
                city: (string) ($point->localite ?? ''),
                countryCode: $criteria->countryCode ?? 'FR',
                latitude: (float) ($point->coordGeolocalisationLatitude ?? 0),
                longitude: (float) ($point->coordGeolocalisationLongitude ?? 0),
                distanceInMeters: isset($point->distanceEnMetre) ? (int) $point->distanceEnMetre : null,
                openingHours: $this->parseOpeningHours($point),
                carrierCode: $this->carrierCode,
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
            return 30;
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
