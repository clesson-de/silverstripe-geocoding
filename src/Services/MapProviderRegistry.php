<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Services;

use Clesson\Silverstripe\Geocoding\Interfaces\MapProviderInterface;
use Clesson\Silverstripe\Geocoding\Models\GeocodingService;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

/**
 * Registry for map provider implementations.
 *
 * Modules can register their own map providers via YAML config:
 *
 * ```yaml
 * Clesson\Silverstripe\Geocoding\Services\MapProviderRegistry:
 *   providers:
 *     bing: 'MyVendor\MyModule\Providers\BingMapProvider'
 *     mapbox: 'MyVendor\MyModule\Providers\MapboxMapProvider'
 * ```
 *
 * The registry automatically detects which provider supports a given
 * GeocodingService instance and provides configuration data.
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Services
 */
class MapProviderRegistry
{

    use Injectable;
    use Configurable;

    /**
     * Map provider classes keyed by provider identifier.
     *
     * @config
     * @var array<string, string> ['providerKey' => 'ProviderClassName']
     */
    private static array $providers = [
        'google' => 'Clesson\Silverstripe\Geocoding\Providers\GoogleMapProvider',
        'osm'    => 'Clesson\Silverstripe\Geocoding\Providers\OpenStreetMapProvider',
    ];

    /**
     * Cache for instantiated provider instances.
     *
     * @var array<string, MapProviderInterface>
     */
    protected array $providerInstances = [];

    /**
     * Returns all registered map provider instances.
     *
     * @return array<string, MapProviderInterface>
     */
    public function getProviders(): array
    {
        if (empty($this->providerInstances)) {
            $this->loadProviders();
        }

        return $this->providerInstances;
    }

    /**
     * Loads and instantiates all registered provider classes.
     *
     * @return void
     */
    protected function loadProviders(): void
    {
        $providers = $this->config()->get('providers') ?: [];

        foreach ($providers as $key => $className) {
            if (!class_exists($className)) {
                continue;
            }

            /** @var MapProviderInterface $instance */
            $instance = Injector::inst()->create($className);

            if ($instance instanceof MapProviderInterface) {
                $this->providerInstances[$key] = $instance;
            }
        }
    }

    /**
     * Finds the first provider that supports the given GeocodingService.
     *
     * @param GeocodingService|null $service
     * @return MapProviderInterface|null
     */
    public function getProviderForService(?GeocodingService $service): ?MapProviderInterface
    {
        if ($service === null || !$service->Active) {
            return null;
        }

        foreach ($this->getProviders() as $provider) {
            if ($provider->supports($service)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Returns a specific provider by its key.
     *
     * @param string $key Provider key (e.g. 'google', 'osm', 'bing')
     * @return MapProviderInterface|null
     */
    public function getProvider(string $key): ?MapProviderInterface
    {
        $providers = $this->getProviders();

        return $providers[$key] ?? null;
    }

}

