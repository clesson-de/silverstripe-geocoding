<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Controllers;

use Clesson\Silverstripe\Geocoding\Helpers\GeoCoder;
use Clesson\Silverstripe\Geocoding\Models\Address;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Controller for dynamic address-related requests.
 *
 * Provides AJAX endpoints for dependent dropdowns (e.g. Region based on Country)
 * and for the AddressField (suggest, create).
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Controllers
 */
class AddressController extends Controller
{
    /**
     * @inheritdoc
     */
    private static array $allowed_actions = [
        'regions',
        'suggest',
        'createAddress',
    ];

    /**
     * Returns subdivisions (regions/states) for a given country as JSON.
     *
     * URL example: /geocoding-api/address/regions?country=US&locale=en
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function regions(HTTPRequest $request): HTTPResponse
    {
        if (!Permission::check('CMS_ACCESS', 'any', Security::getCurrentUser())) {
            return $this->jsonResponse(['error' => 'Access denied'], 403);
        }

        $countryCode = $request->getVar('country');
        $locale = $request->getVar('locale');

        if (!$countryCode) {
            return $this->jsonResponse(['error' => 'Missing country parameter'], 400);
        }

        $options = Address::getSubdivisionOptions((string) $countryCode, $locale);

        return $this->jsonResponse([
            'options' => $options,
            'debug'   => [
                'country' => $countryCode,
                'locale'  => $locale,
                'count'   => count($options),
            ],
        ]);
    }

    /**
     * Returns address suggestions for a free-text query string.
     *
     * URL example: /geocoding-api/address/suggest?q=Unter+den+Linden+Berlin&limit=5
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function suggest(HTTPRequest $request): HTTPResponse
    {
        if (!Permission::check('CMS_ACCESS', 'any', Security::getCurrentUser())) {
            return $this->jsonResponse(['error' => 'Access denied'], 403);
        }

        $query = trim((string) $request->getVar('q'));
        $limit = max(1, min(10, (int) ($request->getVar('limit') ?: 5)));

        if (strlen($query) < 2) {
            return $this->jsonResponse([]);
        }

        $suggestions = GeoCoder::suggest($query, $limit);

        return $this->jsonResponse($suggestions);
    }

    /**
     * Creates an Address model from structured data or coordinates and returns its ID.
     *
     * POST body (JSON):
     *   { source: 'suggestion', street, city, postalCode, country, lat, lng, label }
     *   { source: 'coordinates', lat, lng }
     *
     * Response:
     *   { id, title, lat, lng }
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function createAddress(HTTPRequest $request): HTTPResponse
    {
        if (!Permission::check('CMS_ACCESS', 'any', Security::getCurrentUser())) {
            return $this->jsonResponse(['error' => 'Access denied'], 403);
        }

        if (!$request->isPOST()) {
            return $this->jsonResponse(['error' => 'POST required'], 405);
        }

        $body = json_decode((string) $request->getBody(), true);

        if (!is_array($body)) {
            return $this->jsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $source = $body['source'] ?? 'suggestion';
        $lat    = isset($body['lat']) && $body['lat'] !== '' ? (float) $body['lat'] : null;
        $lng    = isset($body['lng']) && $body['lng'] !== '' ? (float) $body['lng'] : null;

        if ($source === 'coordinates') {
            return $this->createFromCoordinates($lat, $lng);
        }

        return $this->createFromSuggestion($body, $lat, $lng);
    }

    /**
     * Creates an Address from a geocoding suggestion payload.
     * Returns an existing Address if one with matching coordinates already exists.
     *
     * @param array      $body
     * @param float|null $lat
     * @param float|null $lng
     * @return HTTPResponse
     */
    private function createFromSuggestion(array $body, ?float $lat, ?float $lng): HTTPResponse
    {
        if ($lat !== null && $lng !== null) {
            $existing = $this->findByCoordinates($lat, $lng);
            if ($existing !== null) {
                return $this->addressResponse($existing, $lat, $lng);
            }
        }

        /** @var Address $address */
        $address              = Address::create();
        $address->Name        = $body['label'] ?? '';
        $address->AddressLine1 = $body['street'] ?? '';
        $address->City        = $body['city'] ?? '';
        $address->PostalCode  = $body['postalCode'] ?? '';
        $address->CountryCode = strtoupper($body['country'] ?? '');
        $address->write();

        if ($lat !== null && $lng !== null) {
            $address->GeoCoordinatesLatitude  = $lat;
            $address->GeoCoordinatesLongitude = $lng;
            $address->write();
        }

        return $this->addressResponse($address, $lat, $lng);
    }

    /**
     * Creates an Address from coordinates via reverse geocoding.
     * Returns an existing Address if one with matching coordinates already exists.
     *
     * @param float|null $lat
     * @param float|null $lng
     * @return HTTPResponse
     */
    private function createFromCoordinates(?float $lat, ?float $lng): HTTPResponse
    {
        if ($lat === null || $lng === null) {
            return $this->jsonResponse(['error' => 'lat and lng are required'], 400);
        }

        $existing = $this->findByCoordinates($lat, $lng);
        if ($existing !== null) {
            return $this->addressResponse($existing, $lat, $lng);
        }

        $data = GeoCoder::reverseGeocode($lat, $lng);

        /** @var Address $address */
        $address = Address::create();

        if ($data !== null) {
            $address->AddressLine1 = $data['street'] ?? '';
            $address->City         = $data['city'] ?? '';
            $address->PostalCode   = $data['postalCode'] ?? '';
            $address->CountryCode  = strtoupper($data['country'] ?? '');
            $address->Name         = implode(', ', array_filter([
                $address->AddressLine1,
                $address->PostalCode . ' ' . $address->City,
                $address->CountryCode,
            ]));
        }

        $address->write();

        $address->GeoCoordinatesLatitude  = $lat;
        $address->GeoCoordinatesLongitude = $lng;
        $address->write();

        return $this->addressResponse($address, $lat, $lng);
    }

    /**
     * Finds an existing Address by matching geo coordinates (7-digit precision).
     *
     * @param float $lat
     * @param float $lng
     * @return Address|null
     */
    private function findByCoordinates(float $lat, float $lng): ?Address
    {
        return Address::get()->where([
            'ABS("GeoCoordinatesLatitude" - ?)  < 0.0000005' => $lat,
            'ABS("GeoCoordinatesLongitude" - ?) < 0.0000005' => $lng,
        ])->first();
    }

    /**
     * Returns the standard JSON response for an Address record.
     *
     * @param Address    $address
     * @param float|null $lat
     * @param float|null $lng
     * @return HTTPResponse
     */
    private function addressResponse(Address $address, ?float $lat, ?float $lng): HTTPResponse
    {
        return $this->jsonResponse([
            'id'    => $address->ID,
            'title' => $address->getTitle() ?: $address->Name,
            'lat'   => $lat,
            'lng'   => $lng,
        ]);
    }

    /**
     * Returns a JSON response with the given data and status code.
     *
     * @param array $data
     * @param int   $statusCode
     * @return HTTPResponse
     */
    protected function jsonResponse(array $data, int $statusCode = 200): HTTPResponse
    {
        /** @var HTTPResponse $response */
        $response = HTTPResponse::create();
        $response->setStatusCode($statusCode);
        $response->addHeader('Content-Type', 'application/json');
        $response->setBody(json_encode($data));

        return $response;
    }
}

