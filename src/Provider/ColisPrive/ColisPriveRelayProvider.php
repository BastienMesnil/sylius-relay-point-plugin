<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Provider\ColisPrive;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\OpeningHours;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;
use Psr\Log\LoggerInterface;
use SoapClient;
use SoapHeader;
use Throwable;
use function in_array;
use function is_array;
use function max;
use function round;
use function strtr;
use function trim;

/**
 * Searches Colis Privé / Relais Colis pickup points via their SOAP API.
 *
 * Colis Privé uses a SoapHeader for authentication (login + password),
 * the same credentials as the label generation SOAP (WSCP.asmx).
 * The relay point search endpoint is separate from the label generation WSDL.
 *
 * @see https://www.colisprive.com — contact your account manager for WSDL access and credentials
 *
 * TODO: confirm the relay point search WSDL URL and method name with Colis Privé technical support.
 *       The placeholder below (RelaisServiceWS) must be replaced with the actual endpoint
 *       before this provider can be used in production.
 */
final class ColisPriveRelayProvider implements RelayPointProviderInterface
{
    /** @todo replace with the real Colis Privé relay point search WSDL URL */
    private const SEARCH_WSDL_URL = 'https://www.colisprive.com/Externe/RelaisServiceWS.asmx?wsdl';

    private const CARRIER_CODE = 'colis_prive';

    /**
     * @param string[] $shippingMethodCodes
     */
    public function __construct(
        private readonly string $login,
        private readonly string $password,
        private readonly array $shippingMethodCodes,
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
            $client = new SoapClient(self::SEARCH_WSDL_URL, ['trace' => false, 'exception' => true]);

            $header = new SoapHeader(
                'http://colisprive.com/externe/1.0/',
                'AuthenticationHeader',
                ['UserName' => $this->login, 'Password' => $this->password],
            );
            $client->__setSoapHeaders($header);

            /** @todo replace method name and params with the actual Colis Privé relay search operation */
            $params = [
                'ZipCode' => $criteria->postcode ?? '',
                'City' => $this->normalize($criteria->city) ?? '',
                'CountryCode' => $criteria->countryCode ?? 'FR',
                'MaxDistance' => $this->radiusInKm($criteria->radiusInMeters),
                'MaxResults' => $criteria->limit,
            ];

            $result = $client->RecherchePointRelais($params);
        } catch (Throwable $e) {
            $this->logger->error('Colis Privé relay search error: ' . $e->getMessage());

            return [];
        }

        if (!isset($result->RecherchePointRelaisResult->listePointRelais)) {
            return [];
        }

        $list = $result->RecherchePointRelaisResult->listePointRelais;
        if (!is_array($list)) {
            $list = [$list];
        }

        $points = [];
        foreach ($list as $point) {
            /** @todo map fields to match the actual SOAP response structure */
            $points[] = new RelayPoint(
                id: (string) ($point->identifiant ?? ''),
                name: (string) ($point->nom ?? ''),
                street: trim(((string) ($point->adresse1 ?? '')) . ' ' . ((string) ($point->adresse2 ?? ''))),
                postcode: (string) ($point->codePostal ?? ''),
                city: (string) ($point->ville ?? ''),
                countryCode: $criteria->countryCode ?? 'FR',
                latitude: (float) ($point->latitude ?? 0),
                longitude: (float) ($point->longitude ?? 0),
                distanceInMeters: isset($point->distanceEnMetre) ? (int) $point->distanceEnMetre : null,
                openingHours: $this->parseOpeningHours($point),
                carrierCode: self::CARRIER_CODE,
            );
        }

        return $points;
    }

    /** @return OpeningHours[] */
    private function parseOpeningHours(object $point): array
    {
        /** @todo adapt to the actual Colis Privé opening hours structure */
        if (!isset($point->listeHoraires)) {
            return [];
        }

        $horaires = is_array($point->listeHoraires) ? $point->listeHoraires : [$point->listeHoraires];

        $days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
        $openingHours = [];

        foreach ($horaires as $h) {
            $dayNum = (int) ($h->jour ?? 0);
            if (!isset($days[$dayNum])) {
                continue;
            }

            $openingHours[] = new OpeningHours(
                day: $days[$dayNum],
                hours: (string) ($h->horaires ?? ''),
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
