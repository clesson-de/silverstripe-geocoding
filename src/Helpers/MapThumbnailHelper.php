<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Helpers;

use Clesson\Silverstripe\Geocoding\ORM\DBGeoCoordinate;
use SilverStripe\Control\Director;

/**
 * Helper for generating static map thumbnail URLs.
 *
 * The thumbnails are rendered by StaticMapController, which stitches OSM
 * tiles server-side using PHP GD. No external static map API is required.
 *
 * Usage:
 * ```php
 * // From a DBGeoCoordinate
 * $url = MapThumbnailHelper::urlFromCoordinate($address->dbObject('GeoCoordinates'));
 *
 * // From raw lat/lng
 * $url = MapThumbnailHelper::url(48.1351, 11.5820, zoom: 14, width: 400, height: 300);
 *
 * // In a template
 * <img src="$GeoCoordinates.ThumbnailUrl" alt="Map" />
 * ```
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Helpers
 */
class MapThumbnailHelper
{

    /**
     * Builds a thumbnail URL for the given coordinates.
     *
     * @param float  $lat    Latitude
     * @param float  $lng    Longitude
     * @param int    $zoom   Zoom level (1–18, default 14)
     * @param int    $width  Image width in pixels (default 400)
     * @param int    $height Image height in pixels (default 300)
     * @param string $layer  Optional layer key (e.g. 'topo', 'cyclosm').
     *                       Must be one of the keys defined in StaticMapController.layers config.
     *                       Leave empty to use the default tile layer.
     * @return string Absolute URL
     */
    public static function url(
        float $lat,
        float $lng,
        int $zoom    = 14,
        int $width   = 400,
        int $height  = 300,
        string $layer = ''
    ): string {
        $params = [
            'lat'    => $lat,
            'lng'    => $lng,
            'zoom'   => $zoom,
            'width'  => $width,
            'height' => $height,
        ];

        if ($layer !== '') {
            $params['layer'] = $layer;
        }

        return rtrim(Director::absoluteBaseURL(), '/') . '/geocoding-static-map?' . http_build_query($params);
    }

    /**
     * Builds a thumbnail URL from a DBGeoCoordinate instance.
     * Returns an empty string when the coordinate is not set.
     *
     * @param DBGeoCoordinate|null $coordinate
     * @param int                  $zoom
     * @param int                  $width
     * @param int                  $height
     * @param string               $layer  Optional layer key (see StaticMapController.layers config).
     * @return string Absolute URL or empty string
     */
    public static function urlFromCoordinate(
        ?DBGeoCoordinate $coordinate,
        int $zoom    = 14,
        int $width   = 400,
        int $height  = 300,
        string $layer = ''
    ): string {
        if ($coordinate === null || !$coordinate->exists()) {
            return '';
        }

        return self::url(
            $coordinate->getLatitude(),
            $coordinate->getLongitude(),
            $zoom,
            $width,
            $height,
            $layer
        );
    }

}

