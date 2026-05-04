<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Providers;

use Clesson\Silverstripe\Geocoding\Interfaces\MapProviderInterface;
use Clesson\Silverstripe\Geocoding\Models\GeocodingService;
use Clesson\Silverstripe\Geocoding\Models\GoogleService;

/**
 * Google Maps provider implementation.
 *
 * Supports GoogleService models and provides configuration for
 * the Google Maps JavaScript API.
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Providers
 */
class GoogleMapProvider implements MapProviderInterface
{

    /**
     * Returns the unique identifier for this provider.
     *
     * @return string
     */
    public function getProviderKey(): string
    {
        return 'google';
    }

    /**
     * Returns the human-readable name of this provider.
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return 'Google Maps';
    }

    /**
     * Checks if this provider supports the given GeocodingService model.
     *
     * @param GeocodingService $service
     * @return bool
     */
    public function supports(GeocodingService $service): bool
    {
        return $service instanceof GoogleService;
    }

    /**
     * Returns configuration data needed for JavaScript initialization.
     *
     * @param GeocodingService $service
     * @return array
     */
    public function getConfig(GeocodingService $service): array
    {
        if (!$service instanceof GoogleService) {
            return [];
        }

        return [
            'apiKey' => $service->ApiKey,
        ];
    }

    /**
     * Returns the paths to CSS resources required by this provider.
     *
     * @return array<string>
     */
    public function getCSSResources(): array
    {
        return [];
    }

    /**
     * Returns the paths to JavaScript resources required by this provider.
     *
     * @param GeocodingService $service
     * @return array<string>
     */
    public function getJavaScriptResources(GeocodingService $service): array
    {
        if (!$service instanceof GoogleService) {
            return [];
        }

        return [
            'https://maps.googleapis.com/maps/api/js?key=' . $service->ApiKey,
            'clesson-de/silverstripe-geocoding:client/admin/dist/google-map.js',
        ];
    }

    /**
     * Returns the available map type layers for Google Maps.
     *
     * @return array<string, string>
     */
    public function getLayerOptions(): array
    {
        return [
            'roadmap'   => 'Roadmap',
            'satellite' => 'Satellite',
            'terrain'   => 'Terrain',
            'hybrid'    => 'Hybrid',
        ];
    }

    /**
     * Returns the default layer key.
     *
     * @return string
     */
    public function getDefaultLayer(): string
    {
        return 'roadmap';
    }

}

