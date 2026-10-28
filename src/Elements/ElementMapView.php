<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Elements;

use Clesson\Silverstripe\Geocoding\Forms\AddressField;
use Clesson\Silverstripe\Geocoding\Forms\GridFieldConfig_AddressesInMapElement;
use Clesson\Silverstripe\Geocoding\Forms\MapMarker;
use Clesson\Silverstripe\Geocoding\Interfaces\MapProviderInterface;
use Clesson\Silverstripe\Geocoding\Models\Address;
use Clesson\Silverstripe\Geocoding\Models\ElementMapViewAddress;
use Clesson\Silverstripe\Geocoding\Models\GeocodingService;
use Clesson\Silverstripe\Geocoding\Services\MapProviderRegistry;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\HasManyList;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;

/**
 * Elemental content element that renders an interactive map.
 *
 * The map uses the map provider configured in SiteConfig.
 * One or more addresses can be linked via a GridField. Each can have
 * a custom marker image and an info window text.
 *
 * When Zoom is set to 0, the map automatically fits the viewport to
 * show all markers (fit-bounds mode).
 *
 * @property int $Zoom      Zoom level (0 = auto-fit all markers)
 * @property int $MapHeight Map height in pixels
 * @property bool $ShowZoomControl       Whether to show the zoom control
 * @property bool $ShowFullscreenControl Whether to show the fullscreen control
 * @property bool $ShowScaleControl      Whether to show the scale control
 * @property int $CenterAddressID
 *
 * @method Address CenterAddress()
 * @method HasManyList<ElementMapViewAddress> MapAddresses()
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Elements
 */
class ElementMapView extends BaseElement
{
    /**
     * @var string
     */
    private static string $singular_name = 'Map element';

    /**
     * @var string
     */
    private static string $plural_name = 'Map elements';

    /**
     * @var string
     */
    private static string $class_description = 'Displays an interactive map with one or more address markers.';

    /**
     * GridFields have no React schema component — inline editing must be disabled.
     *
     * @var bool
     */
    private static bool $inline_editable = false;

    /**
     * @inheritdoc
     */
    private static string $table_name = 'Geocoding_ElementMapView';

    /**
     * @inheritdoc
     */
    private static array $db = [
        'Zoom'                  => 'Int',
        'MapHeight'             => 'Int',
        'ShowZoomControl'       => 'Boolean(1)',
        'ShowFullscreenControl' => 'Boolean(1)',
        'ShowScaleControl'      => 'Boolean(1)',
    ];

    /**
     * @inheritdoc
     */
    private static array $has_one = [
        'CenterAddress' => Address::class,
    ];

    /**
     * @inheritdoc
     */
    private static array $has_many = [
        'MapAddresses' => ElementMapViewAddress::class . '.Element',
    ];

    /**
     * @inheritdoc
     */
    private static array $owns = [
        'MapAddresses',
    ];

    /**
     * @inheritdoc
     */
    private static array $cascade_deletes = [
        'MapAddresses',
    ];

    /**
     * Cached map provider instance.
     */
    private ?MapProviderInterface $cachedProvider = null;

    /**
     * Cached geocoding service instance.
     */
    private ?GeocodingService $cachedService = null;

    /**
     * Whether the provider resolution has already been attempted.
     */
    private bool $providerResolved = false;

    /**
     * @inheritdoc
     */
    public function populateDefaults(): void
    {
        parent::populateDefaults();
        $this->Zoom                  = 0;
        $this->MapHeight             = 400;
        $this->ShowZoomControl       = true;
        $this->ShowFullscreenControl = true;
        $this->ShowScaleControl      = true;
    }

    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Zoom']                  = _t(__CLASS__ . '.ZOOM', 'Zoom level');
        $labels['MapHeight']             = _t(__CLASS__ . '.MAP_HEIGHT', 'Map height (px)');
        $labels['MapAddresses']          = _t(__CLASS__ . '.MAP_ADDRESSES', 'Addresses');
        $labels['CenterAddress']         = _t(__CLASS__ . '.CENTER_ADDRESS', 'Center address');
        $labels['ShowZoomControl']       = _t(__CLASS__ . '.SHOW_ZOOM_CONTROL', 'Show zoom control');
        $labels['ShowFullscreenControl'] = _t(__CLASS__ . '.SHOW_FULLSCREEN_CONTROL', 'Show fullscreen control');
        $labels['ShowScaleControl']      = _t(__CLASS__ . '.SHOW_SCALE_CONTROL', 'Show scale control');
        return $labels;
    }

    /**
     * @inheritdoc
     */
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        // Remove auto-scaffolded GridField — it has no React schema component.
        $fields->removeByName(['MapAddresses', 'CenterAddressID']);

        /** @var AddressField $centerAddressField */
        $centerAddressField = AddressField::create('CenterAddressID', $this->fieldLabel('CenterAddress'));
        $centerAddressField->setDescription(_t(__CLASS__ . '.CENTER_ADDRESS_EMPTY', 'Auto-center (fit all markers)'));

        /** @var NumericField $zoomField */
        $zoomField = NumericField::create('Zoom', $this->fieldLabel('Zoom'))
            ->setDescription(_t(__CLASS__ . '.ZOOM_DESCRIPTION', '0 = automatically fit all markers in view'));

        /** @var NumericField $mapHeightField */
        $mapHeightField = NumericField::create('MapHeight', $this->fieldLabel('MapHeight'))
            ->setDescription(_t(__CLASS__ . '.MAP_HEIGHT_DESCRIPTION', 'Height of the map in pixels, e.g. 400'));

        $fields->addFieldsToTab('Root.Main', [$centerAddressField]);

        if ($this->isInDB()) {
            /** @var GridField $addressesField */
            $addressesField = GridField::create(
                'MapAddresses',
                $this->fieldLabel('MapAddresses'),
                $this->MapAddresses(),
                GridFieldConfig_AddressesInMapElement::create()
            );

            $fields->addFieldToTab('Root.Main', $addressesField);
        }

        $fields->addFieldsToTab('Root.Main', [$zoomField, $mapHeightField]);

        /** @var CheckboxField $showZoomControlField */
        $showZoomControlField = CheckboxField::create('ShowZoomControl', $this->fieldLabel('ShowZoomControl'));

        /** @var CheckboxField $showFullscreenControlField */
        $showFullscreenControlField = CheckboxField::create('ShowFullscreenControl', $this->fieldLabel('ShowFullscreenControl'));

        /** @var CheckboxField $showScaleControlField */
        $showScaleControlField = CheckboxField::create('ShowScaleControl', $this->fieldLabel('ShowScaleControl'));

        $fields->addFieldsToTab('Root.Main', [$showZoomControlField, $showFullscreenControlField, $showScaleControlField]);

        return $fields;
    }

    /**
     * Returns a short summary shown in the Elemental overview.
     *
     * @return string
     */
    public function getSummary(): string
    {
        $count   = $this->MapAddresses()->count();
        $zoomStr = (int) $this->Zoom === 0
            ? _t(__CLASS__ . '.ZOOM_AUTO', 'auto')
            : (string) $this->Zoom;

        return sprintf(
            _t(__CLASS__ . '.SUMMARY', '%d address(es), zoom: %s'),
            $count,
            $zoomStr
        );
    }

    /**
     * Returns the element type label shown in the CMS.
     *
     * @return string
     */
    public function getType(): string
    {
        return _t(__CLASS__ . '.TYPE', 'Map');
    }

    /**
     * Loads the required frontend map assets and returns an empty string.
     *
     * Called from the template via {$MapAssets} to inject Requirements
     * into the page's <head> at render time.
     *
     * @return string Always empty — side effect is loading Requirements.
     */
    public function getMapAssets(): string
    {
        Requirements::javascript('clesson-de/silverstripe-geocoding:client/admin/dist/map-utils.js');

        $provider = $this->resolveProvider();
        $service  = $this->resolveService();

        if ($provider !== null && $service !== null) {
            foreach ($provider->getCSSResources() as $css) {
                Requirements::css($css);
            }
            foreach ($provider->getJavaScriptResources($service) as $js) {
                Requirements::javascript($js);
            }
        }

        return '';
    }

    /**
     * Returns the active map provider key (e.g. 'osm', 'google').
     *
     * @return string
     */
    public function getProviderKey(): string
    {
        return $this->resolveProvider()?->getProviderKey() ?? 'none';
    }

    /**
     * Returns the provider configuration as a JSON-encoded string.
     *
     * @return string
     */
    public function getProviderConfigJson(): string
    {
        $provider = $this->resolveProvider();
        $service  = $this->resolveService();

        if ($provider !== null && $service !== null) {
            return json_encode($provider->getConfig($service));
        }

        return '{}';
    }

    /**
     * Returns all map markers as a JSON-encoded string.
     *
     * Only addresses with valid geo coordinates are included.
     *
     * @return string
     */
    public function getMarkersJson(): string
    {
        $markers = [];

        foreach ($this->MapAddresses()->sort('"Sort" ASC') as $entry) {
            /** @var ElementMapViewAddress $entry */
            if (!$entry->Active || !$entry->ShowMarker) {
                continue;
            }

            $address = $entry->Address();

            if (!$address || !$address->exists()) {
                continue;
            }

            $geo = $address->dbObject('GeoCoordinates');

            if ($geo === null || $geo->getLatitude() === null || $geo->getLongitude() === null) {
                continue;
            }

            $marker = MapMarker::create(
                (float) $geo->getLatitude(),
                (float) $geo->getLongitude()
            );

            $marker->setInfoWindow((string) $entry->InfoWindowText);

            $image = $entry->MarkerImage();
            if ($image && $image->exists()) {
                $marker->setIconUrl(
                    (string) $image->getAbsoluteURL(),
                    (int) $image->getWidth(),
                    (int) $image->getHeight()
                );
            }

            $markers[] = $marker->toArray();
        }

        return json_encode($markers);
    }

    /**
     * Returns true when the map should auto-fit all markers (Zoom = 0).
     *
     * @return bool
     */
    public function getFitBounds(): bool
    {
        return (int) $this->Zoom === 0;
    }

    /**
     * Returns the effective zoom level. Falls back to 8 when auto-fit is active.
     *
     * @return int
     */
    public function getEffectiveZoom(): int
    {
        $zoom = (int) $this->Zoom;
        return $zoom > 0 ? $zoom : 8;
    }

    /**
     * Returns the map height in pixels (minimum 200).
     *
     * @return int
     */
    public function getEffectiveMapHeight(): int
    {
        $height = (int) $this->MapHeight;
        return max(200, $height > 0 ? $height : 400);
    }

    /**
     * Returns the latitude of the center address, or the first marker address, or the default.
     *
     * @return float
     */
    public function getCenterLatitude(): float
    {
        $center = $this->CenterAddress();
        if ($center && $center->exists()) {
            $geo = $center->dbObject('GeoCoordinates');
            if ($geo && $geo->getLatitude() !== null) {
                return (float) $geo->getLatitude();
            }
        }

        foreach ($this->MapAddresses()->sort('"Sort" ASC') as $entry) {
            $geo = $entry->Address()?->dbObject('GeoCoordinates');
            if ($geo && $geo->getLatitude() !== null) {
                return (float) $geo->getLatitude();
            }
        }

        return (float) self::config()->get('default_latitude') ?: 51.1657;
    }

    /**
     * Returns the longitude of the center address, or the first marker address, or the default.
     *
     * @return float
     */
    public function getCenterLongitude(): float
    {
        $center = $this->CenterAddress();
        if ($center && $center->exists()) {
            $geo = $center->dbObject('GeoCoordinates');
            if ($geo && $geo->getLongitude() !== null) {
                return (float) $geo->getLongitude();
            }
        }

        foreach ($this->MapAddresses()->sort('"Sort" ASC') as $entry) {
            $geo = $entry->Address()?->dbObject('GeoCoordinates');
            if ($geo && $geo->getLongitude() !== null) {
                return (float) $geo->getLongitude();
            }
        }

        return (float) self::config()->get('default_longitude') ?: 10.4515;
    }

    /**
     * Resolves and caches the active map provider from the registry.
     *
     * @return MapProviderInterface|null
     */
    private function resolveProvider(): ?MapProviderInterface
    {
        if ($this->providerResolved) {
            return $this->cachedProvider;
        }

        $this->providerResolved = true;
        $service = $this->resolveService();

        if ($service !== null) {
            /** @var MapProviderRegistry $registry */
            $registry = Injector::inst()->get(MapProviderRegistry::class);
            $this->cachedProvider = $registry->getProviderForService($service);
        }

        return $this->cachedProvider;
    }

    /**
     * Resolves and caches the active geocoding service from SiteConfig.
     *
     * @return GeocodingService|null
     */
    private function resolveService(): ?GeocodingService
    {
        if ($this->cachedService !== null) {
            return $this->cachedService;
        }

        $siteConfig = SiteConfig::current_site_config();
        $mapService = $siteConfig->Geocoding_MapDisplayService();

        if ($mapService && $mapService->Active) {
            $this->cachedService = $mapService;
        }

        return $this->cachedService;
    }
}


