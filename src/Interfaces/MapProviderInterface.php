<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Interfaces;

use Clesson\Silverstripe\Geocoding\Models\GeocodingService;

/**
 * Interface for map provider implementations.
 *
 * Implement this interface to add support for additional map providers
 * (e.g. Bing Maps, Mapbox) in external modules.
 *
 * Register your provider via YAML config:
 * ```yaml
 * Clesson\Silverstripe\Geocoding\Services\MapProviderRegistry:
 *   providers:
 *     bing: 'MyVendor\MyModule\Providers\BingMapProvider'
 * ```
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Interfaces
 */
interface MapProviderInterface
{

    /**
     * Returns the unique identifier for this provider (e.g. 'google', 'osm', 'bing').
     *
     * @return string
     */
    public function getProviderKey(): string;

    /**
     * Returns the human-readable name of this provider.
     *
     * @return string
     */
    public function getProviderName(): string;

    /**
     * Checks if this provider supports the given GeocodingService model.
     *
     * @param GeocodingService $service
     * @return bool
     */
    public function supports(GeocodingService $service): bool;

    /**
     * Returns configuration data needed for JavaScript initialization.
     *
     * This data is passed to the frontend as data-attributes and JSON config.
     *
     * @param GeocodingService $service
     * @return array Configuration data (e.g. ['apiKey' => '...'])
     */
    public function getConfig(GeocodingService $service): array;

    /**
     * Returns the paths to CSS resources required by this provider.
     *
     * @return array<string> Array of CSS file paths or CDN URLs
     */
    public function getCSSResources(): array;

    /**
     * Returns the paths to JavaScript resources required by this provider.
     *
     * @param GeocodingService $service
     * @return array<string> Array of JS file paths or CDN URLs
     */
    public function getJavaScriptResources(GeocodingService $service): array;

    /**
     * Returns available map layers for this provider as an associative array.
     *
     * The keys are layer identifiers passed to the JavaScript provider script,
     * the values are human-readable labels shown in the layer switcher UI.
     *
     * Return an empty array if the provider does not support multiple layers
     * — in that case no layer switcher will be shown.
     *
     * Example (OSM):
     * ```php
     * return [
     *     'osm'   => 'OpenStreetMap',
     *     'topo'  => 'OpenTopoMap',
     * ];
     * ```
     *
     * Example (Google):
     * ```php
     * return [
     *     'roadmap'   => 'Roadmap',
     *     'satellite' => 'Satellite',
     *     'terrain'   => 'Terrain',
     *     'hybrid'    => 'Hybrid',
     * ];
     * ```
     *
     * @return array<string, string>
     */
    public function getLayerOptions(): array;

    /**
     * Returns the default layer key to use when no layer is explicitly selected.
     *
     * Must be one of the keys returned by getLayerOptions(), or an empty string
     * if the provider does not support multiple layers.
     *
     * @return string
     */
    public function getDefaultLayer(): string;

}

