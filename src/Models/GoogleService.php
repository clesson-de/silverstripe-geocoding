<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Models;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use Clesson\Silverstripe\Geocoding\ORM\DBGeoCoordinate;
use GuzzleHttp\Client;
use Throwable;

/**
 * Represents a Google Maps geocoding service configuration.
 *
 * Uses the Google Geocoding API for geocoding and reverse geocoding,
 * and the Google Directions API for routing.
 *
 * @property string $ApiKey
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Models
 */
class GoogleService extends GeocodingService
{

    /** Base URL for all Google Maps API requests. */
    private const BASE_URL = 'https://maps.googleapis.com';

    /**
     * @inheritdoc
     */
    private static string $table_name = 'Geocoding_GoogleService';

    /**
     * @inheritdoc
     */
    private static array $db = [
        'ApiKey' => 'Varchar(512)',
    ];

    /**
     * Returns translated labels for all fields.
     *
     * @param bool $includerelations
     * @return array<string, string>
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);

        $labels['ApiKey'] = _t(__CLASS__ . '.API_KEY', 'API key');

        return $labels;
    }

    /** Placeholder shown in the API key field when a key is already stored. */
    private const API_KEY_PLACEHOLDER = '••••••••••••••••••••••••••••••••••••••••';

    /**
     * Returns the CMS edit form fields.
     *
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->removeByName(['ApiKey']);

        /** @var TextField $apiKeyField */
        $apiKeyField = TextField::create('ApiKey', $this->fieldLabel('ApiKey'));
        $apiKeyField->setDescription(
            _t(__CLASS__ . '.API_KEY_DESCRIPTION', 'Your Google Maps API key. Requires the Geocoding API and Directions API to be enabled.')
        );

        // When a key is already stored, show a masked placeholder instead of the real value.
        // The user only needs to type in this field to replace the key.
        if ($this->ApiKey) {
            $apiKeyField->setAttribute('placeholder', self::API_KEY_PLACEHOLDER);
            $apiKeyField->setValue('');
            $apiKeyField->setDescription(
                _t(__CLASS__ . '.API_KEY_DESCRIPTION', 'Your Google Maps API key. Requires the Geocoding API and Directions API to be enabled.')
                . ' ' . _t(__CLASS__ . '.API_KEY_STORED', 'A key is already stored. Leave this field empty to keep the existing key.')
            );
        }

        $fields->addFieldToTab('Root.Main', $apiKeyField);

        return $fields;
    }

    /**
     * Preserve the existing API key when the field is submitted empty.
     *
     * @inheritdoc
     */
    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        // If the field was left blank the user did not intend to change the key.
        if (empty($this->ApiKey) && $this->isInDB()) {
            /** @var static $original */
            $original = static::get()->byID($this->ID);
            if ($original && $original->ApiKey) {
                $this->ApiKey = $original->ApiKey;
            }
        }
    }

    /**
     * Resolves an address to geographic coordinates using the Google Geocoding API.
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
     * Resolves an address to geographic coordinates using the Google Geocoding API.
     *
     * The Google Geocoding API does not expose a place name in its response, so
     * `placeName` is always null. The method signature mirrors OpenStreetMapService
     * so that GeoCoder::geocodeFull() can dispatch to it uniformly.
     *
     * @param array $address Associative array with keys: street, city, postalCode, country.
     * @return array{coordinate: DBGeoCoordinate, placeName: string|null}|null
     */
    public function geocodeFull(array $address): ?array
    {
        $addressString = implode(', ', array_filter([
            $address['street']                                               ?? '',
            trim(($address['postalCode'] ?? '') . ' ' . ($address['city'] ?? '')),
            $address['country']                                              ?? '',
        ]));

        $url = self::BASE_URL . '/maps/api/geocode/json?address=' . urlencode($addressString) . '&key=' . $this->ApiKey;

        try {
            $client   = new Client();
            $response = $client->get($url);
            $data     = json_decode((string) $response->getBody(), true);

            if (
                empty($data['results'])
                || !isset($data['results'][0]['geometry']['location']['lat'], $data['results'][0]['geometry']['location']['lng'])
            ) {
                return null;
            }

            $location   = $data['results'][0]['geometry']['location'];
            $coordinate = DBGeoCoordinate::create();
            $coordinate->setLatitude((float) $location['lat']);
            $coordinate->setLongitude((float) $location['lng']);

            return [
                'coordinate' => $coordinate,
                'placeName'  => null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolves geographic coordinates to a street address using the Google Geocoding API.
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
        $url = self::BASE_URL . '/maps/api/geocode/json?latlng=' . $latitude . ',' . $longitude . '&key=' . $this->ApiKey;

        try {
            $client   = new Client();
            $response = $client->get($url);
            $data     = json_decode((string) $response->getBody(), true);

            if (empty($data['results']) || !isset($data['results'][0]['address_components'])) {
                return null;
            }

            $components   = $data['results'][0]['address_components'];
            $streetNumber = '';
            $route        = '';
            $city         = '';
            $postalCode   = '';
            $country      = '';

            foreach ($components as $component) {
                $types = $component['types'] ?? [];
                if (in_array('street_number', $types, true)) {
                    $streetNumber = $component['long_name'];
                } elseif (in_array('route', $types, true)) {
                    $route = $component['long_name'];
                } elseif (in_array('locality', $types, true)) {
                    $city = $component['long_name'];
                } elseif (in_array('postal_code', $types, true)) {
                    $postalCode = $component['long_name'];
                } elseif (in_array('country', $types, true)) {
                    $country = $component['short_name'];
                }
            }

            return [
                'street'     => trim($streetNumber ? $route . ' ' . $streetNumber : $route),
                'city'       => $city,
                'postalCode' => $postalCode,
                'country'    => $country,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Calculates a route using the Google Directions API.
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
        $url = self::BASE_URL . '/maps/api/directions/json'
            . '?origin='      . urlencode($origin['lat'] . ',' . $origin['lng'])
            . '&destination=' . urlencode($destination['lat'] . ',' . $destination['lng'])
            . '&key='         . $this->ApiKey;

        if (!empty($waypoints)) {
            $url .= '&waypoints=' . urlencode(implode('|', array_map(
                static fn(array $wp): string => $wp['lat'] . ',' . $wp['lng'],
                $waypoints
            )));
        }

        try {
            $client   = new Client();
            $response = $client->get($url);
            $data     = json_decode((string) $response->getBody(), true);

            if (empty($data['routes']) || empty($data['routes'][0]['legs'])) {
                return null;
            }

            $totalDistance = 0;
            $totalDuration = 0;
            $steps         = [];

            foreach ($data['routes'][0]['legs'] as $leg) {
                $totalDistance += $leg['distance']['value'] ?? 0;
                $totalDuration += $leg['duration']['value'] ?? 0;

                foreach ($leg['steps'] ?? [] as $step) {
                    $steps[] = [
                        'instruction'     => html_entity_decode(strip_tags($step['html_instructions'] ?? '')),
                        'distanceMeters'  => (int) ($step['distance']['value'] ?? 0),
                        'durationSeconds' => (int) ($step['duration']['value'] ?? 0),
                    ];
                }
            }

            return [
                'distanceMeters'  => $totalDistance,
                'durationSeconds' => $totalDuration,
                'steps'           => $steps,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Tests the Google API key by sending a minimal geocode request.
     *
     * Uses the Berlin Brandenburg Gate as a test address — cheap, always resolvable.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        if (empty($this->ApiKey)) {
            return [
                'success' => false,
                'message' => _t(__CLASS__ . '.TEST_NO_KEY', 'No API key configured.'),
            ];
        }

        try {
            $client   = new Client(['base_uri' => self::BASE_URL, 'timeout' => 5]);
            $response = $client->get('/maps/api/geocode/json', [
                'query' => [
                    'address' => 'Pariser Platz 1, 10117 Berlin',
                    'key'     => $this->ApiKey,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);
            $status = $body['status'] ?? 'UNKNOWN';

            if ($status === 'OK') {
                return [
                    'success' => true,
                    'message' => _t(__CLASS__ . '.TEST_SUCCESS', 'API key is valid. Connection successful.'),
                ];
            }

            if ($status === 'REQUEST_DENIED') {
                return [
                    'success' => false,
                    'message' => _t(
                        __CLASS__ . '.TEST_DENIED',
                        'Request denied by Google: {error}',
                        ['error' => $body['error_message'] ?? $status]
                    ),
                ];
            }

            return [
                'success' => false,
                'message' => _t(
                    __CLASS__ . '.TEST_FAILED',
                    'Unexpected API status: {status}',
                    ['status' => $status]
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
