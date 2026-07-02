<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\Provider\MondialRelay;

use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\OpeningHours;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPoint;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\Model\RelayPointSearchCriteria;
use Keirontw\SyliusRelayPointPlugin\RelayPoint\RelayPointProviderInterface;
use Psr\Log\LoggerInterface;
use SoapClient;
use Throwable;
use function count;
use function implode;
use function in_array;
use function is_array;
use function md5;
use function sprintf;
use function str_replace;
use function strtoupper;
use function substr;

final class MondialRelayProvider implements RelayPointProviderInterface
{
    private const WSDL_URL = 'https://api.mondialrelay.com/Web_Services.asmx?wsdl';

    private const CARRIER_CODE = 'mondial_relay';

    private ?SoapClient $soapClient = null;

    /**
     * @param string[] $shippingMethodCodes Sylius shipping method codes handled by this provider.
     *                                      Configured via keirontw_sylius_relay_point.providers.mondial_relay.shipping_method_codes
     */
    public function __construct(
        private readonly string $account,
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
        if (
            empty($criteria->postcode) &&
            empty($criteria->city) &&
            (null === $criteria->latitude || null === $criteria->longitude)
        ) {
            return [];
        }

        $params = [
            'Enseigne' => $this->account,
            'Pays' => $criteria->countryCode ?? 'FR',
            'NumPointRelais' => '',
            'Ville' => $this->normalize($criteria->city),
            'CP' => $criteria->postcode ?? '',
            'Latitude' => null !== $criteria->latitude ? str_replace('.', ',', (string) $criteria->latitude) : '',
            'Longitude' => null !== $criteria->longitude ? str_replace('.', ',', (string) $criteria->longitude) : '',
            'Taille' => '',
            'Poids' => '',
            'Action' => '',
            'DelaiEnvoi' => '',
            'RayonRecherche' => null !== $criteria->latitude ? $this->radiusInKm($criteria->radiusInMeters) : '',
            'TypeActivite' => '',
            'NACE' => '',
            'NombreResultats' => $criteria->limit,
        ];

        $securityKey = implode('', $params) . $this->password;
        $params['Security'] = strtoupper(md5($securityKey));

        try {
            $this->soapClient ??= new SoapClient(self::WSDL_URL);
            $result = $this->soapClient->WSI4_PointRelais_Recherche($params);
        } catch (Throwable $e) {
            $this->logger->error('MondialRelay search error: ' . $e->getMessage());

            return [];
        }

        $rawPoints = $result->WSI4_PointRelais_RechercheResult->PointsRelais->PointRelais_Details ?? null;
        if (null === $rawPoints) {
            return [];
        }

        if (!is_array($rawPoints)) {
            $rawPoints = [$rawPoints];
        }

        $relayPoints = [];
        foreach ($rawPoints as $point) {
            $relayPoints[] = new RelayPoint(
                id: (string) $point->Num,
                name: (string) $point->LgAdr1,
                street: trim(((string) $point->LgAdr2) . ' ' . ((string) $point->LgAdr3)),
                postcode: (string) $point->CP,
                city: (string) $point->Ville,
                countryCode: $criteria->countryCode ?? 'FR',
                latitude: (float) str_replace(',', '.', (string) $point->Latitude),
                longitude: (float) str_replace(',', '.', (string) $point->Longitude),
                distanceInMeters: isset($point->Distance) ? (int) $point->Distance * 10 : null,
                openingHours: $this->parseOpeningHours($point),
                carrierCode: self::CARRIER_CODE,
            );
        }

        return $relayPoints;
    }

    private function parseOpeningHours(object $point): array
    {
        $days = [
            'Lundi' => $point->Horaires_Lundi,
            'Mardi' => $point->Horaires_Mardi,
            'Mercredi' => $point->Horaires_Mercredi,
            'Jeudi' => $point->Horaires_Jeudi,
            'Vendredi' => $point->Horaires_Vendredi,
            'Samedi' => $point->Horaires_Samedi,
            'Dimanche' => $point->Horaires_Dimanche,
        ];

        $openingHours = [];
        foreach ($days as $day => $hours) {
            if (!isset($hours->string)) {
                continue;
            }

            $slots = is_array($hours->string) ? $hours->string : [$hours->string];
            $formatted = [];

            for ($i = 0; $i < count($slots); $i += 2) {
                if (!isset($slots[$i + 1])) {
                    continue;
                }
                $formatted[] = sprintf(
                    '%s:%s-%s:%s',
                    substr($slots[$i], 0, 2),
                    substr($slots[$i], 2, 2),
                    substr($slots[$i + 1], 0, 2),
                    substr($slots[$i + 1], 2, 2),
                );
            }

            $openingHours[] = new OpeningHours(
                day: $day,
                hours: !empty($formatted) ? implode(', ', $formatted) : 'Fermé',
            );
        }

        return $openingHours;
    }

    private function radiusInKm(?int $radiusInMeters): string
    {
        if (null === $radiusInMeters) {
            return '20';
        }

        return (string) max(1, (int) round($radiusInMeters / 1000));
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
