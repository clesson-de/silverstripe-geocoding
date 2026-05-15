<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Models;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use Clesson\Silverstripe\Geocoding\ORM\DBGeoCoordinate;
use GuzzleHttp\Client;
use Throwable;

/**
 * Represents an OpenStreetMap geocoding service configuration.
 *
 * Uses Nominatim for geocoding and reverse geocoding, and OSRM for routing.
 * Both endpoints are configurable to support self-hosted instances.
 *
 * @property string $BaseUrl
 * @property string $OsrmBaseUrl
 * @property int    $RateLimitMs
 * @property-read string $Title
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Models
 */
class OpenStreetMapService extends GeocodingService
{

    /** Default Nominatim base URL. */
    private const DEFAULT_NOMINATIM_BASE_URL = 'https://nominatim.openstreetmap.org';

    /** Default OSRM base URL. */
    private const DEFAULT_OSRM_BASE_URL = 'https://router.project-osrm.org';

    /**
     * @inheritdoc
     */
    private static string $table_name = 'Geocoding_OpenStreetMapService';

    /**
     * @inheritdoc
     */
    private static array $db = [
        'BaseUrl'     => 'Varchar',
        'OsrmBaseUrl' => 'Varchar',
        'RateLimitMs' => 'Int',
    ];

    /**
     * Sets default values before the record is first saved.
     *
     * @return void
     */
    public function populateDefaults(): void
    {
        parent::populateDefaults();
        $this->RateLimitMs = 1100;
    }

    /**
     * Returns translated labels for all fields.
     *
     * @param bool $includerelations
     * @return array<string, string>
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);

        $labels['BaseUrl']     = _t(__CLASS__ . '.BASE_URL', 'Nominatim base URL');
        $labels['OsrmBaseUrl'] = _t(__CLASS__ . '.OSRM_BASE_URL', 'OSRM base URL');
        $labels['RateLimitMs'] = _t(__CLASS__ . '.RATE_LIMIT_MS', 'Rate limit (ms)');

        return $labels;
    }

    /**
     * Returns the CMS edit form fields.
     *
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->removeByName(['BaseUrl', 'OsrmBaseUrl', 'RateLimitMs']);

        /** @var TextField $baseUrlField */
        $baseUrlField = TextField::create('BaseUrl', $this->fieldLabel('BaseUrl'));
        $baseUrlField->setDescription(
            _t(__CLASS__ . '.BASE_URL_DESCRIPTION', 'Leave empty to use the public Nominatim instance (https://nominatim.openstreetmap.org).')
        );

        /** @var TextField $osrmBaseUrlField */
        $osrmBaseUrlField = TextField::create('OsrmBaseUrl', $this->fieldLabel('OsrmBaseUrl'));
        $osrmBaseUrlField->setDescription(
            _t(__CLASS__ . '.OSRM_BASE_URL_DESCRIPTION', 'Leave empty to use the public OSRM instance (https://router.project-osrm.org).')
        );

        /** @var NumericField $rateLimitMsField */
        $rateLimitMsField = NumericField::create('RateLimitMs', $this->fieldLabel('RateLimitMs'));
        $rateLimitMsField->setDescription(
            _t(__CLASS__ . '.RATE_LIMIT_MS_DESCRIPTION', 'Minimum wait time between two requests in milliseconds. Recommended: 1100 for the public Nominatim instance.')
        );

        $fields->addFieldsToTab('Root.Main', [$baseUrlField, $osrmBaseUrlField, $rateLimitMsField]);

        return $fields;
    }

    /**
     * Returns the configured rate limit delay in microseconds, or 0 if not set.
     *
     * @return int
     */
    protected function getRateLimitDelay(): int
    {
        return $this->RateLimitMs > 0 ? $this->RateLimitMs * 1000 : 0;
    }

    /**
     * Applies the configured rate limit delay before an outgoing request.
     *
     * @return void
     */
    protected function applyRateLimit(): void
    {
        $delay = $this->getRateLimitDelay();
        if ($delay > 0) {
            usleep($delay);
        }
    }

    /**
     * OSM classes that represent named places (amenity, leisure, building, …).
     *
     * A result whose `class` is in this list will have its `name` promoted to the
     * Address `Name` (Bezeichnung) field automatically.
     */
    private const NAMED_PLACE_CLASSES = [
        'amenity', 'leisure', 'building', 'shop', 'tourism',
        'sport', 'healthcare', 'office', 'craft', 'emergency',
        'military', 'religion', 'historic', 'club',
    ];

    /**
     * Resolves an address to geographic coordinates using the Nominatim API.
     *
     * Example: geocode(['street' => 'Unter den Linden 1', 'city' => 'Berlin', 'postalCode' => '10117', 'country' => 'DE'])
     *
     * @param array $address Associative array with keys: street, city, postalCode, country.
     * @return DBGeoCoordinate|null Returns null if geocoding fails or no result is found.
     */
    public function geocode(array $address): ?DBGeoCoordinate
    {
        $result = $this->geocodeFull($address);

        return $result !== null ? $result['coordinate'] : null;
    }

    /**
     * Resolves an address to geographic coordinates and – for named places such as schools
     * or sports halls – an optional place name using the Nominatim API.
     *
     * Returns an associative array with keys:
     * - coordinate (DBGeoCoordinate)
     * - placeName  (?string) – non-null only when the result is a named place
     *
     * Example: geocodeFull(['street' => 'Schulstraße 5', 'city' => 'Kirchheim', 'postalCode' => '73230', 'country' => 'DE'])
     *
     * @param array $address Associative array with keys: street, city, postalCode, country.
     * @return array{coordinate: DBGeoCoordinate, placeName: string|null}|null
     */
    public function geocodeFull(array $address): ?array
    {
        $baseUrl = $this->BaseUrl ?: self::DEFAULT_NOMINATIM_BASE_URL;

        $query = implode(',', array_filter([
            $address['street']     ?? '',
            $address['postalCode'] ?? '',
            $address['city']       ?? '',
            $address['country']    ?? '',
        ]));

        $url = $baseUrl . '/search?q=' . urlencode($query) . '&format=json&limit=1&addressdetails=1';

        try {
            $this->applyRateLimit();

            $client   = new Client();
            $response = $client->get($url, ['headers' => ['User-Agent' => 'SilverstripeGeocodingModule/1.0']]);
            $data     = json_decode((string) $response->getBody(), true);

            if (empty($data) || !isset($data[0]['lat'], $data[0]['lon'])) {
                return null;
            }

            $coordinate = DBGeoCoordinate::create();
            $coordinate->setLatitude((float) $data[0]['lat']);
            $coordinate->setLongitude((float) $data[0]['lon']);

            return [
                'coordinate' => $coordinate,
                'placeName'  => $this->extractPlaceName($data[0]),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Extracts the place name from a Nominatim result entry.
     *
     * Returns the `name` field only when the result's OSM `class` indicates a
     * named place (amenity, leisure, building, …). For plain street addresses
     * the method returns null.
     *
     * @param array<string, mixed> $result A single Nominatim search result.
     * @return string|null
     */
    private function extractPlaceName(array $result): ?string
    {
        $class = $result['class'] ?? '';
        $name  = trim((string) ($result['name'] ?? ''));

        if ($name !== '' && in_array($class, self::NAMED_PLACE_CLASSES, true)) {
            return $name;
        }

        return null;
    }

    /**
     * Resolves geographic coordinates to a street address using the Nominatim reverse API.
     *
     * Example: reverseGeocode(52.5163, 13.3777)
     *
     * @param float $latitude
     * @param float $longitude
     * @return array|null Associative array with keys: street, city, postalCode, country.
     *                    Returns null if the lookup fails.
     */
    public function reverseGeocode(float $latitude, float $longitude): ?array
    {
        $baseUrl = $this->BaseUrl ?: self::DEFAULT_NOMINATIM_BASE_URL;
        $url     = $baseUrl . '/reverse?lat=' . $latitude . '&lon=' . $longitude . '&format=json';

        try {
            $this->applyRateLimit();

            $client   = new Client();
            $response = $client->get($url, ['headers' => ['User-Agent' => 'SilverstripeGeocodingModule/1.0']]);
            $data     = json_decode((string) $response->getBody(), true);

            if (empty($data) || !isset($data['address'])) {
                return null;
            }

            $addr = $data['address'];

            return [
                'street'     => $addr['road']         ?? '',
                'city'       => $addr['city']         ?? ($addr['town'] ?? ($addr['village'] ?? '')),
                'postalCode' => $addr['postcode']     ?? '',
                'region'     => $addr['state']        ?? '',
                'country'    => $addr['country_code'] ?? '',
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Calculates a driving route using the OSRM API.
     *
     * Example: route(['lat' => 52.5163, 'lng' => 13.3777], ['lat' => 48.1351, 'lng' => 11.5820])
     *
     * @param array $origin      Associative array with keys: lat, lng.
     * @param array $destination Associative array with keys: lat, lng.
     * @param array $waypoints   Optional list of waypoints, each with keys: lat, lng.
     * @return array|null Associative array with keys: distanceMeters (int), durationSeconds (int), steps (array).
     *                    Returns null if routing fails.
     */
    public function route(array $origin, array $destination, array $waypoints = []): ?array
    {
        $osrmBaseUrl = $this->OsrmBaseUrl ?: self::DEFAULT_OSRM_BASE_URL;
        $allPoints   = array_merge([$origin], $waypoints, [$destination]);

        $coordinates = implode(';', array_map(
            static fn(array $p): string => $p['lng'] . ',' . $p['lat'],
            $allPoints
        ));

        $url = $osrmBaseUrl . '/route/v1/driving/' . $coordinates . '?overview=false&steps=true';

        try {
            $client   = new Client();
            $response = $client->get($url);
            $data     = json_decode((string) $response->getBody(), true);

            if (empty($data['routes']) || !isset($data['routes'][0]['distance'], $data['routes'][0]['duration'])) {
                return null;
            }

            $route = $data['routes'][0];
            $steps = [];

            foreach ($route['legs'] ?? [] as $leg) {
                foreach ($leg['steps'] ?? [] as $step) {
                    $steps[] = $step;
                }
            }

            return [
                'distanceMeters'  => (int) $route['distance'],
                'durationSeconds' => (int) $route['duration'],
                'steps'           => $steps,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Returns up to $limit address suggestions for a free-text query using the Nominatim search API.
     *
     * Example: suggest('Unter den Linden Berlin', 5)
     *
     * @param string $query  Free-text search string.
     * @param int    $limit  Maximum number of results.
     * @return array<array{label: string, lat: float, lng: float, street: string, housenumber: string, city: string, postalCode: string, country: string}>
     */
    public function suggest(string $query, int $limit = 5): array
    {
        $baseUrl = rtrim($this->BaseUrl ?: self::DEFAULT_NOMINATIM_BASE_URL, '/');
        $url     = $baseUrl . '/search?q=' . urlencode($query)
            . '&format=json&limit=' . $limit . '&addressdetails=1';

        try {
            $this->applyRateLimit();

            $client   = new Client();
            $response = $client->get($url, ['headers' => ['User-Agent' => 'SilverstripeGeocodingModule/1.0']]);
            $data     = json_decode((string) $response->getBody(), true);

            if (empty($data)) {
                return [];
            }

            $suggestions = [];

            foreach ($data as $item) {
                $addr        = $item['address'] ?? [];
                $housenumber = $addr['house_number'] ?? '';
                $road        = $addr['road'] ?? '';
                $street      = $housenumber !== '' ? $road . ' ' . $housenumber : $road;

                $suggestions[] = [
                    'label'       => $item['display_name'] ?? '',
                    'lat'         => (float) ($item['lat'] ?? 0),
                    'lng'         => (float) ($item['lon'] ?? 0),
                    'street'      => trim($street),
                    'housenumber' => $housenumber,
                    'city'        => $addr['city'] ?? ($addr['town'] ?? ($addr['village'] ?? '')),
                    'postalCode'  => $addr['postcode'] ?? '',
                    'region'      => $addr['state'] ?? '',
                    'country'     => strtoupper($addr['country_code'] ?? ''),
                ];
            }

            return $suggestions;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Tests the Nominatim connection by sending a minimal geocode request.
     *
     * Uses the Berlin Brandenburg Gate as a test address.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        $baseUrl = rtrim($this->BaseUrl ?: self::DEFAULT_NOMINATIM_BASE_URL, '/');

        try {
            $client   = new Client(['timeout' => 5]);
            $response = $client->get($baseUrl . '/search', [
                'query' => [
                    'q'              => 'Pariser Platz 1, 10117 Berlin',
                    'format'         => 'json',
                    'limit'          => 1,
                    'addressdetails' => 0,
                ],
                'headers' => [
                    'User-Agent' => 'silverstripe-geocoding/test',
                    'Accept'     => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body       = json_decode((string) $response->getBody(), true);

            if ($statusCode === 200 && is_array($body)) {
                return [
                    'success' => true,
                    'message' => _t(__CLASS__ . '.TEST_SUCCESS', 'Nominatim is reachable. Connection successful.'),
                ];
            }

            return [
                'success' => false,
                'message' => _t(
                    __CLASS__ . '.TEST_FAILED',
                    'Unexpected response (HTTP {code}).',
                    ['code' => $statusCode]
                ),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => _t(
                    __CLASS__ . '.TEST_ERROR',
                    'Connection error: {error}',
                    ['error' => $e->getMessage()]
                ),
            ];
        }
    }

}
