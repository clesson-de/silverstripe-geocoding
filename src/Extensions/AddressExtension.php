<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Extensions;

use Clesson\Silverstripe\Geocoding\Forms\MapField;
use Clesson\Silverstripe\Geocoding\Helpers\GeoCoder;
use Clesson\Silverstripe\Geocoding\ORM\DBGeoCoordinate;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;

/**
 * Extends the Address model with a GeoCoordinates composite field.
 *
 * The field stores Latitude and Longitude as a DBGeoCoordinate composite type.
 * Coordinates are resolved automatically by GeoCoder::geocode() via the
 * onAfterWrite hook whenever the record is created or a relevant address field
 * (AddressLine1, City, PostalCode, CountryCode) changes.
 *
 * Manual marker placement is intentionally disabled in the CMS — the geocoder
 * is the sole source of truth for the coordinates.
 *
 * @property-read DBGeoCoordinate $GeoCoordinates
 * @property float                $GeoCoordinatesLatitude
 * @property float                $GeoCoordinatesLongitude
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Extensions
 */
class AddressExtension extends Extension
{

    /**
     * @inheritdoc
     */
    private static array $db = [
        'GeoCoordinates' => 'Clesson\Silverstripe\Geocoding\ORM\DBGeoCoordinate',
    ];

    /**
     * Adds translated field labels for all fields contributed by this extension.
     *
     * @param array $labels
     * @return void
     */
    public function updateFieldLabels(&$labels): void
    {
        $labels['GeoCoordinates']          = _t(__CLASS__ . '.GEO_COORDINATES', 'Geo coordinates');
        $labels['GeoCoordinatesLatitude']  = _t(__CLASS__ . '.LATITUDE', 'Latitude');
        $labels['GeoCoordinatesLongitude'] = _t(__CLASS__ . '.LONGITUDE', 'Longitude');
    }

    /**
     * Adds the geo coordinates field to the CMS edit form.
     *
     * Shows a MapField in read-only mode for the coordinates — the marker is
     * always set automatically by the geocoder and must not be placed manually.
     *
     * @param FieldList $fields
     * @return void
     */
    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName(['GeoCoordinatesLatitude', 'GeoCoordinatesLongitude']);

        /** @var MapField $geoCoordinatesField */
        $geoCoordinatesField = MapField::create('GeoCoordinates', '');
        $geoCoordinatesField->setValue($this->getOwner()->dbObject('GeoCoordinates'));
        $geoCoordinatesField->setZoomLevel(6);
        $geoCoordinatesField->setZoomLevelWithMarker(16);
        // The marker is always set by the geocoder — manual placement is not allowed.
        $geoCoordinatesField->setAllowPlaceMarker(false);

        $fields->addFieldToTab('Root.Main', $geoCoordinatesField);
    }

    /**
     * Guard flag to prevent recursive writes triggered by onAfterWrite.
     */
    private bool $isGeocodingWrite = false;

    /**
     * Address fields that trigger a new geocoding run when they change.
     */
    private const GEOCODING_FIELDS = ['AddressLine1', 'City', 'PostalCode', 'CountryCode'];

    /**
     * Automatically resolves geo coordinates from the address data after each write.
     *
     * Geocoding is triggered when:
     * - at least AddressLine1 and City are filled in, AND
     * - the record is new (first write), OR
     * - at least one of the relevant address fields (AddressLine1, City,
     *   PostalCode, CountryCode) has changed compared to the previous version.
     *
     * This means explicitly cleared coordinates will be re-set the next time
     * the address is saved with a changed address field.
     *
     * @return void
     */
    public function onAfterWrite(): void
    {
        if ($this->isGeocodingWrite) {
            return;
        }

        $owner = $this->getOwner();

        if (empty($owner->AddressLine1) || empty($owner->City)) {
            return;
        }

        // Determine whether geocoding should run:
        // - always on the very first write (Version 1)
        // - on subsequent writes only when a relevant address field changed
        $shouldGeocode = false;

        if ($owner->Version <= 1) {
            $shouldGeocode = true;
        } else {
            foreach (self::GEOCODING_FIELDS as $field) {
                if ($owner->isChanged($field)) {
                    $shouldGeocode = true;
                    break;
                }
            }
        }

        if (!$shouldGeocode) {
            return;
        }

        $result = GeoCoder::geocodeFull([
            'street'     => $owner->AddressLine1,
            'city'       => $owner->City,
            'postalCode' => $owner->PostalCode,
            'country'    => $owner->CountryCode,
        ]);

        if ($result === null) {
            return;
        }

        $coordinate = $result['coordinate'];
        $placeName  = $result['placeName'];

        $this->isGeocodingWrite = true;

        $owner->GeoCoordinatesLatitude  = $coordinate->getLatitude();
        $owner->GeoCoordinatesLongitude = $coordinate->getLongitude();

        // Auto-fill the Bezeichnung (Name) only when the geocoder found a named
        // place (school, sports hall, …) and the field is currently empty.
        if (empty($owner->Name) && $placeName !== null) {
            $owner->Name = $placeName;
        }

        $owner->write();

        $this->isGeocodingWrite = false;
    }

}


