<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Helpers;

use Clesson\Silverstripe\Geocoding\Models\GeocodingService;
use Clesson\Silverstripe\Geocoding\Models\GoogleService;
use Clesson\Silverstripe\Geocoding\Models\OpenStreetMapService;
use Clesson\Silverstripe\Geocoding\Models\SiteConfigGeocodeService;
use Clesson\Silverstripe\Geocoding\Models\SiteConfigReverseGeocodeService;
use Clesson\Silverstripe\Geocoding\Models\SiteConfigRouteService;
use Clesson\Silverstripe\Geocoding\ORM\DBGeoCoordinate;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\SiteConfig\SiteConfig;
use Psr\SimpleCache\CacheInterface;

/**
 * Static helper for geocoding, reverse geocoding and routing.
 *
 * Selects the best available service for each method based on priority
 * (higher value = higher priority). If a service has exhausted its daily
 * quota for the requested method, it is skipped and the next-best service
 * is tried.
 *
 * Quota counters are stored in the Silverstripe object cache, keyed by
 * service ID, method name and calendar date. They reset automatically at
 * midnight because the date is part of the cache key.
 *
 * A quota of 0 means unlimited.
 *
 * Usage:
 * ```php
 * use Clesson\Silverstripe\Geocoding\Helpers\GeoCoder;
 *
 * $coord   = GeoCoder::geocode(['street' => 'Unter den Linden 1', 'city' => 'Berlin', 'postalCode' => '10117', 'country' => 'DE']);
 * $address = GeoCoder::reverseGeocode(52.5163, 13.3777);
 * $route   = GeoCoder::route(['lat' => 52.5163, 'lng' => 13.3777], ['lat' => 48.1351, 'lng' => 11.5820]);
 * ```
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Helpers
 */
class GeoCoder
{

    /** Cache namespace used for all quota counters. */
    private const CACHE_NAMESPACE = 'geocoding_quota';

    /**
     * Resolves an address to geographic coordinates.
     *
     * Tries active services in descending GeocodePriority order.
     * Skips services whose daily GeocodeQuota is exhausted.
     *
     * Example: GeoCoder::geocode(['street' => 'Unter den Linden 1', 'city' => 'Berlin', 'postalCode' => '10117', 'country' => 'DE'])
     *
     * @param array $address Associative array with keys: street, city, postalCode, country.
     * @return DBGeoCoordinate|null Returns null if no service is available or all fail.
     */
    public static function geocode(array $address): ?DBGeoCoordinate
    {
        foreach (self::getCandidates('geocode') as $service) {
            if (!self::isQuotaAvailable($service, 'geocode')) {
                continue;
            }

            $result = $service->geocode($address);

            if ($result !== null) {
                self::incrementQuota($service, 'geocode');
                return $result;
            }
        }

        return null;
    }

    /**
     * Resolves a free-text query to a list of address suggestions.
     *
     * Tries active geocode services in priority order. Returns the first
     * non-empty result. Falls back to an empty array when no service is
     * available or no service implements suggest().
     *
     * Example: GeoCoder::suggest('Unter den Linden Berlin', 5)
     *
     * @param string $query  Free-text query string.
     * @param int    $limit  Maximum number of results to return.
     * @return array<array{label: string, lat: float, lng: float, street: string, housenumber: string, city: string, postalCode: string, country: string}>
     */
    public static function suggest(string $query, int $limit = 5): array
    {
        foreach (self::getCandidates('geocode') as $service) {
            if (!method_exists($service, 'suggest')) {
                continue;
            }

            if (!self::isQuotaAvailable($service, 'geocode')) {
                continue;
            }

            $result = $service->suggest($query, $limit);

            if (!empty($result)) {
                self::incrementQuota($service, 'geocode');
                return $result;
            }
        }

        return [];
    }

    /**
     * Resolves geographic coordinates to a street address.
     *
     * Tries active services in descending ReverseGeocodePriority order.
     * Skips services whose daily ReverseGeocodeQuota is exhausted.
     *
     * Example: GeoCoder::reverseGeocode(52.5163, 13.3777)
     *
     * @param float $latitude
     * @param float $longitude
     * @return array|null Associative array with keys: street, city, postalCode, country.
     *                    Returns null if no service is available or all fail.
     */
    public static function reverseGeocode(float $latitude, float $longitude): ?array
    {
        foreach (self::getCandidates('reverseGeocode') as $service) {
            if (!self::isQuotaAvailable($service, 'reverseGeocode')) {
                continue;
            }

            $result = $service->reverseGeocode($latitude, $longitude);

            if ($result !== null) {
                self::incrementQuota($service, 'reverseGeocode');
                return $result;
            }
        }

        return null;
    }

    /**
     * Calculates a route from origin through optional waypoints to destination.
     *
     * Tries active services in descending RoutePriority order.
     * Skips services whose daily RouteQuota is exhausted.
     *
     * Example: GeoCoder::route(
     *   ['lat' => 52.5163, 'lng' => 13.3777],
     *   ['lat' => 48.1351, 'lng' => 11.5820],
     *   [['lat' => 50.9333, 'lng' => 6.9500]]
     * )
     *
     * @param array $origin      Associative array with keys: lat, lng.
     * @param array $destination Associative array with keys: lat, lng.
     * @param array $waypoints   Optional list of waypoints, each with keys: lat, lng.
     * @return array|null Associative array with keys: distanceMeters (int), durationSeconds (int), steps (array).
     *                    Returns null if no service is available or all fail.
     */
    public static function route(array $origin, array $destination, array $waypoints = []): ?array
    {
        foreach (self::getCandidates('route') as $service) {
            if (!self::isQuotaAvailable($service, 'route')) {
                continue;
            }

            $result = $service->route($origin, $destination, $waypoints);

            if ($result !== null) {
                self::incrementQuota($service, 'route');
                return $result;
            }
        }

        return null;
    }

    /**
     * Returns the quota usage for a given service and method as a percentage (0–100).
     *
     * Returns null if the quota is unlimited (0) or not configured.
     *
     * Example: getQuotaUsagePercent($siteConfig, $service, 'geocode') → 42.5
     *
     * @param \SilverStripe\SiteConfig\SiteConfig $siteConfig
     * @param GeocodingService $service
     * @param string           $method One of: geocode, reverseGeocode, route.
     * @return float|null Percentage between 0 and 100, or null if unlimited.
     */
    public static function getQuotaUsagePercent($siteConfig, GeocodingService $service, string $method): ?float
    {
        $relation = match ($method) {
            'geocode'        => 'Geocoding_GeocodeServices',
            'reverseGeocode' => 'Geocoding_ReverseGeocodeServices',
            'route'          => 'Geocoding_RouteServices',
            default          => null,
        };

        if (!$relation) {
            return null;
        }

        $list = $siteConfig->{$relation}();
        $joinRecord = $list->filter('ID', $service->ID)->first();

        if (!$joinRecord) {
            return null;
        }

        $limit = (int) ($joinRecord->Quota ?? 0);

        if ($limit === 0) {
            return null;
        }

        $cache = self::getCache();
        $key   = self::CACHE_NAMESPACE . '_' . $service->ID . '_' . $method . '_' . date('Y-m-d');
        $used  = (int) $cache->get($key, 0);

        return round(($used / $limit) * 100, 1);
    }

    /**
     * Returns services for the given method sorted by Sort ASC (= highest priority first).
     *
     * Only active services are returned.
     *
     * Fetches the join records first (sorted by their own Sort field) and then
     * resolves the related GeocodingService objects. This avoids the ambiguous
     * column error that occurs when sorting a ManyManyThroughList by a field that
     * exists in both the join table and the target table.
     *
     * @param string $method One of: geocode, reverseGeocode, route.
     * @return array<OpenStreetMapService|GoogleService>
     */
    private static function getCandidates(string $method): array
    {
        $joinClass = match ($method) {
            'geocode'        => SiteConfigGeocodeService::class,
            'reverseGeocode' => SiteConfigReverseGeocodeService::class,
            'route'          => SiteConfigRouteService::class,
            default          => null,
        };

        if (!$joinClass) {
            return [];
        }

        $siteConfigId = SiteConfig::current_site_config()->ID;

        $joinRecords = $joinClass::get()
            ->filter('SiteConfigID', $siteConfigId)
            ->sort('Sort', 'ASC');

        $candidates = [];

        foreach ($joinRecords as $join) {
            /** @var GeocodingService|null $service */
            $service = $join->GeocodingService();

            if (!$service || !$service->exists() || !$service->Active) {
                continue;
            }

            if ($service instanceof OpenStreetMapService || $service instanceof GoogleService) {
                $candidates[] = $service;
            }
        }

        return $candidates;
    }

    /**
     * Returns true if the given service still has quota available for the given method today.
     *
     * A quota of 0 means unlimited — always returns true in that case.
     *
     * @param OpenStreetMapService|GoogleService $service
     * @param string                             $method One of: geocode, reverseGeocode, route.
     * @return bool
     */
    private static function isQuotaAvailable(OpenStreetMapService|GoogleService $service, string $method): bool
    {
        $quota = self::getQuotaLimit($service, $method);

        if ($quota === 0) {
            return true;
        }

        return self::getQuotaUsed($service, $method) < $quota;
    }

    /**
     * Returns the configured quota limit for the given service and method.
     *
     * Reads from the join table 'Quota' field via ManyManyThroughList.
     *
     * @param OpenStreetMapService|GoogleService $service
     * @param string                             $method
     * @return int 0 means unlimited.
     */
    private static function getQuotaLimit(OpenStreetMapService|GoogleService $service, string $method): int
    {
        $relation = match ($method) {
            'geocode'        => 'Geocoding_GeocodeServices',
            'reverseGeocode' => 'Geocoding_ReverseGeocodeServices',
            'route'          => 'Geocoding_RouteServices',
            default          => null,
        };

        if (!$relation) {
            return 0;
        }

        $siteConfig = SiteConfig::current_site_config();
        $list = $siteConfig->{$relation}();

        // Filter for the specific service and get the join record
        $joinRecord = $list->filter('ID', $service->ID)->first();

        if (!$joinRecord) {
            return 0;
        }

        return (int) ($joinRecord->Quota ?? 0);
    }

    /**
     * Returns the number of calls already made today for the given service and method.
     *
     * @param OpenStreetMapService|GoogleService $service
     * @param string                             $method
     * @return int
     */
    private static function getQuotaUsed(OpenStreetMapService|GoogleService $service, string $method): int
    {
        $cache = self::getCache();
        $key   = self::getCacheKey($service, $method);

        return (int) ($cache->get($key, 0));
    }

    /**
     * Increments the call counter for the given service and method by one.
     *
     * The counter is stored until the end of the current calendar day (UTC).
     *
     * @param OpenStreetMapService|GoogleService $service
     * @param string                             $method
     * @return void
     */
    private static function incrementQuota(OpenStreetMapService|GoogleService $service, string $method): void
    {
        $cache   = self::getCache();
        $key     = self::getCacheKey($service, $method);
        $current = (int) ($cache->get($key, 0));

        // TTL: seconds remaining until midnight UTC
        $ttl = strtotime('tomorrow 00:00:00 UTC') - time();

        $cache->set($key, $current + 1, $ttl);
    }

    /**
     * Builds a unique, date-scoped cache key for the given service and method.
     *
     * The date component ensures counters reset automatically each calendar day.
     *
     * @param OpenStreetMapService|GoogleService $service
     * @param string                             $method
     * @return string
     */
    private static function getCacheKey(OpenStreetMapService|GoogleService $service, string $method): string
    {
        return self::CACHE_NAMESPACE . '_' . $service->ID . '_' . $method . '_' . date('Y-m-d');
    }

    /**
     * Returns the Silverstripe object cache instance.
     *
     * @return CacheInterface
     */
    private static function getCache(): CacheInterface
    {
        return Injector::inst()->get(CacheInterface::class . '.geocodingQuota');
    }

}
