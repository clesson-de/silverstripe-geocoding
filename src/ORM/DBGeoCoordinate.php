<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\ORM;

use Clesson\Silverstripe\Geocoding\Forms\GeoCoordinateField;
use Clesson\Silverstripe\Geocoding\Helpers\MapThumbnailHelper;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\FieldType\DBComposite;

/**
 * A composite DB field storing a geographic coordinate as Latitude + Longitude.
 *
 * Usage in $db: 'Location' => 'GeoCoordinate'
 * This creates two columns: LocationLatitude DECIMAL(10,7) and LocationLongitude DECIMAL(10,7)
 *
 * Precision: 7 decimal places = ~1.1 cm accuracy, which is the industry standard for GPS
 * coordinates. Services like Google Maps may return 14+ decimal places, but the additional
 * precision is only relevant for scientific/surveying applications and will be automatically
 * rounded when saved to the database.
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage ORM
 */
class DBGeoCoordinate extends DBComposite
{

    /**
     * @inheritdoc
     */
    private static array $composite_db = [
        'Latitude'  => 'Decimal(10,7)',
        'Longitude' => 'Decimal(10,7)',
    ];

    /**
     * Returns the stored latitude value, or null if not set.
     *
     * @return float|null
     */
    public function getLatitude(): ?float
    {
        return $this->getField('Latitude') ? (float)$this->getField('Latitude') : null;
    }

    /**
     * Returns the stored longitude value, or null if not set.
     *
     * @return float|null
     */
    public function getLongitude(): ?float
    {
        return $this->getField('Longitude') ? (float)$this->getField('Longitude') : null;
    }

    /**
     * Sets the latitude value.
     *
     * @param float $lat
     * @return static
     */
    public function setLatitude(float $lat): static
    {
        $this->setField('Latitude', $lat);
        return $this;
    }

    /**
     * Sets the longitude value.
     *
     * @param float $lng
     * @return static
     */
    public function setLongitude(float $lng): static
    {
        $this->setField('Longitude', $lng);
        return $this;
    }

    /**
     * Returns true if both Latitude and Longitude are set.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->getLatitude() !== null && $this->getLongitude() !== null;
    }

    /**
     * Returns a GeoCoordinateField for editing this composite field in the CMS.
     *
     * @param string|null $title
     * @param array       $params
     * @return GeoCoordinateField
     */
    public function scaffoldFormField(?string $title = null, array $params = []): GeoCoordinateField
    {
        return GeoCoordinateField::create($this->getName(), $title)
            ->setValue($this);
    }

    /**
     * Returns a human-readable representation of the coordinate in "lat, lng" format.
     *
     * Returns an empty string if the coordinate is not set.
     *
     * @return string
     */
    public function Nice(): string
    {
        if (!$this->exists()) {
            return '';
        }

        return $this->getLatitude() . ', ' . $this->getLongitude();
    }

    /**
     * Returns the URL of a static map thumbnail image centered on this coordinate.
     *
     * The image is rendered by StaticMapController using OSM-compatible tiles and PHP GD.
     * Returns an empty string when the coordinate is not set.
     *
     * Usage in templates:
     * ```
     * <img src="$GeoCoordinates.ThumbnailUrl" alt="Map" />
     * <img src="{$GeoCoordinates.ThumbnailUrl(14, 400, 300, 'topo')}" alt="Map" />
     * ```
     *
     * @param int    $zoom   Zoom level (1–18, default 14)
     * @param int    $width  Image width in pixels (default 400)
     * @param int    $height Image height in pixels (default 300)
     * @param string $layer  Optional layer key (e.g. 'topo', 'cyclosm').
     *                       Must match a key in StaticMapController.layers config.
     * @return string Absolute URL or empty string
     */
    public function ThumbnailUrl(int $zoom = 14, int $width = 400, int $height = 300, string $layer = ''): string
    {
        return MapThumbnailHelper::urlFromCoordinate($this, $zoom, $width, $height, $layer);
    }

}

