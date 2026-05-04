<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Providers;

use Clesson\Silverstripe\Geocoding\Interfaces\MapProviderInterface;
use Clesson\Silverstripe\Geocoding\Models\GeocodingService;
use Clesson\Silverstripe\Geocoding\Models\OpenStreetMapService;

/**
 * OpenStreetMap provider implementation.
 *
 * Supports OpenStreetMapService models and provides configuration for
 * the Leaflet JavaScript library with OpenStreetMap tiles.
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Providers
 */
class OpenStreetMapProvider implements MapProviderInterface
{

    /**
     * Returns the unique identifier for this provider.
     *
     * @return string
     */
    public function getProviderKey(): string
    {
        return 'osm';
    }

    /**
     * Returns the human-readable name of this provider.
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return 'OpenStreetMap';
    }

    /**
     * Checks if this provider supports the given GeocodingService model.
     *
     * @param GeocodingService $service
     * @return bool
     */
    public function supports(GeocodingService $service): bool
    {
        return $service instanceof OpenStreetMapService;
    }

    /**
     * Returns configuration data needed for JavaScript initialization.
     *
     * @param GeocodingService $service
     * @return array
     */
    /** Tile URL definitions per layer key. */
    private const TILE_URLS = [
        'osm'    => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        'topo'   => 'https://{s}.tile.openstreetmap.de/{z}/{x}/{y}.png',
        'cyclosm'=> 'https://{s}.tile-cyclosm.openstreetmap.fr/cyclosm/{z}/{x}/{y}.png',
    ];

    public function getConfig(GeocodingService $service): array
    {
        if (!$service instanceof OpenStreetMapService) {
            return [];
        }

        return [
            'tileUrl'      => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'layerTileUrls' => self::TILE_URLS,
        ];
    }

    /**
     * Returns the paths to CSS resources required by this provider.
     *
     * @return array<string>
     */
    public function getCSSResources(): array
    {
        return [
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        ];
    }

    /**
     * Returns the paths to JavaScript resources required by this provider.
     *
     * @param GeocodingService $service
     * @return array<string>
     */
    public function getJavaScriptResources(GeocodingService $service): array
    {
        return [
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            'clesson-de/silverstripe-geocoding:client/admin/dist/osm-map.js',
        ];
    }

    /**
     * Returns the available tile layers for OpenStreetMap.
     *
     * @return array<string, string>
     */
    public function getLayerOptions(): array
    {
        return [
            'osm'     => 'OpenStreetMap',
            'topo'    => 'OpenStreetMap DE',
            'cyclosm' => 'CyclOSM',
        ];
    }

    /**
     * Returns the default layer key.
     *
     * @return string
     */
    public function getDefaultLayer(): string
    {
        return 'osm';
    }

}

