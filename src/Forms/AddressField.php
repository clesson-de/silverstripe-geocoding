<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Forms;

use Clesson\Silverstripe\Geocoding\Interfaces\MapProviderInterface;
use Clesson\Silverstripe\Geocoding\Models\Address;
use Clesson\Silverstripe\Geocoding\Models\GeocodingService;
use Clesson\Silverstripe\Geocoding\Services\MapProviderRegistry;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;

/**
 * Form field for selecting and creating an Address via an interactive search modal.
 *
 * The field stores a foreign-key address ID (e.g. OperationLocationID) and renders:
 * - A preview line showing the currently linked address summary.
 * - A "Adresse wählen" button that opens a modal.
 *
 * The modal contains a live-search input with autocomplete suggestions and an
 * interactive map. The user can either pick a suggestion or double-click the map
 * and confirm the position. In both cases a new Address record is created and
 * linked to the parent record.
 *
 * Usage:
 * ```php
 * AddressField::create('OperationLocationID', 'Einsatzort')
 * ```
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Forms
 */
class AddressField extends FormField
{
    use Configurable;

    /**
     * Default map center latitude when no address is linked.
     *
     * @config
     */
    private static float $default_latitude = 51.1657;

    /**
     * Default map center longitude when no address is linked.
     *
     * @config
     */
    private static float $default_longitude = 10.4515;

    /**
     * Active map provider.
     */
    protected ?MapProviderInterface $provider = null;

    /**
     * Active geocoding service model.
     */
    protected ?GeocodingService $service = null;

    /**
     * @param string      $name  Field name — must be the foreign-key column, e.g. OperationLocationID.
     * @param string|null $title Optional label.
     * @param mixed       $value Initial value (integer address ID or null).
     */
    public function __construct(string $name, ?string $title = null, mixed $value = null)
    {
        $this->detectProvider();
        parent::__construct($name, $title, $value);
    }

    /**
     * Resolves the active map provider from SiteConfig.
     *
     * @return void
     */
    protected function detectProvider(): void
    {
        /** @var SiteConfig $siteConfig */
        $siteConfig = SiteConfig::current_site_config();

        /** @var GeocodingService|null $mapService */
        $mapService = $siteConfig->Geocoding_MapDisplayService();

        if ($mapService && $mapService->Active) {
            /** @var MapProviderRegistry $registry */
            $registry = Injector::inst()->get(MapProviderRegistry::class);

            $this->provider = $registry->getProviderForService($mapService);
            $this->service  = $mapService;
        }
    }

    /**
     * @inheritdoc
     */
    public function setValue($value, $data = null): static
    {
        $this->value = $value ? (int) $value : null;
        return $this;
    }

    /**
     * Saves the address ID integer into the parent record's field.
     *
     * @param DataObjectInterface $record
     * @return void
     */
    public function saveInto(DataObjectInterface $record): void
    {
        if ($this->isReadonly() || $this->isDisabled()) {
            return;
        }

        $name = $this->getName();
        $record->$name = $this->value ?? 0;
    }

    /**
     * Returns the summary of the currently linked Address, or an empty string.
     *
     * @return string
     */
    public function getAddressTitle(): string
    {
        if (!$this->value) {
            return '';
        }

        /** @var Address|null $address */
        $address = Address::get()->byID((int) $this->value);

        return $address ? ($address->getTitle() ?: $address->Name) : '';
    }

    /**
     * Returns the current address coordinates as a JSON-encoded array, or 'null'.
     *
     * @return string JSON: {lat: float, lng: float} | 'null'
     */
    public function getAddressCoordinates(): string
    {
        $coords = $this->resolveAddressCoords();

        return $coords !== null ? json_encode($coords) : 'null';
    }

    /**
     * Returns the latitude of the linked address, or the default latitude.
     *
     * @return float
     */
    public function getAddressLatitude(): float
    {
        return $this->resolveAddressCoords()['lat'] ?? (float) self::config()->get('default_latitude');
    }

    /**
     * Returns the longitude of the linked address, or the default longitude.
     *
     * @return float
     */
    public function getAddressLongitude(): float
    {
        return $this->resolveAddressCoords()['lng'] ?? (float) self::config()->get('default_longitude');
    }

    /**
     * Returns true when the linked address has valid geo coordinates.
     *
     * @return bool
     */
    public function getHasAddressMarker(): bool
    {
        return $this->resolveAddressCoords() !== null;
    }

    /**
     * Resolves the coordinates of the linked Address record.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function resolveAddressCoords(): ?array
    {
        if (!$this->value) {
            return null;
        }

        /** @var Address|null $address */
        $address = Address::get()->byID((int) $this->value);

        if (!$address) {
            return null;
        }

        $geo = $address->dbObject('GeoCoordinates');

        if ($geo === null || $geo->getLatitude() === null || $geo->getLongitude() === null) {
            return null;
        }

        return ['lat' => $geo->getLatitude(), 'lng' => $geo->getLongitude()];
    }

    /**
     * Renders the field HTML and registers its assets.
     *
     * @param array $properties
     * @return string
     */
    public function Field($properties = []): string
    {
        $this->addAssets();

        $providerKey    = $this->provider ? $this->provider->getProviderKey() : 'none';
        $providerConfig = ($this->provider && $this->service)
            ? json_encode($this->provider->getConfig($this->service))
            : '{}';

        $properties = array_merge($properties, [
            'AddressTitle'       => $this->getAddressTitle(),
            'AddressCoordinates' => $this->getAddressCoordinates(),
            'AddressLatitude'    => $this->getAddressLatitude(),
            'AddressLongitude'   => $this->getAddressLongitude(),
            'HasAddressMarker'   => $this->getHasAddressMarker(),
            'Provider'           => $providerKey,
            'ProviderConfig'     => $providerConfig,
            'DefaultLatitude'    => self::config()->get('default_latitude'),
            'DefaultLongitude'   => self::config()->get('default_longitude'),
            'SuggestUrl'         => '/geocoding-api/address/suggest',
            'CreateUrl'          => '/geocoding-api/address/createAddress',
            'LabelSearch'        => _t(__CLASS__ . '.SEARCH_PLACEHOLDER', 'Search address…'),
            'LabelChange'        => _t(__CLASS__ . '.CHANGE', 'Select address'),
            'LabelClear'         => _t(__CLASS__ . '.CLEAR', 'Remove address'),
            'LabelAccept'        => _t(__CLASS__ . '.ACCEPT', 'Accept'),
            'LabelCancel'        => _t(__CLASS__ . '.CANCEL', 'Cancel'),
            'LabelMapHint'       => _t(__CLASS__ . '.MAP_HINT', 'Double-click the map to place a marker, then click "Accept".'),
            'LabelNoProvider'    => _t(__CLASS__ . '.NO_PROVIDER', 'No map provider configured.'),
        ]);

        return parent::Field($properties)->getValue();
    }

    /**
     * Registers the required CSS and JS assets.
     *
     * @return void
     */
    protected function addAssets(): void
    {
        // Map utilities (provider-agnostic)
        Requirements::javascript('silverstripe-geocoding/client/admin/dist/map-utils.js');
        Requirements::javascript('silverstripe-geocoding/client/admin/dist/map-entwine.js');
        Requirements::css('silverstripe-geocoding/client/admin/dist/map-field.css');

        // AddressField-specific
        Requirements::javascript('silverstripe-geocoding/client/admin/dist/address-field.js');
        Requirements::css('silverstripe-geocoding/client/admin/dist/address-field.css');

        if ($this->provider !== null && $this->service !== null) {
            foreach ($this->provider->getCSSResources() as $css) {
                Requirements::css($css);
            }
            foreach ($this->provider->getJavaScriptResources($this->service) as $js) {
                Requirements::javascript($js);
            }
        }
    }
}



