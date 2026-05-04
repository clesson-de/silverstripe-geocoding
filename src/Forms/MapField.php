<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Forms;

use Clesson\Silverstripe\Geocoding\Interfaces\MapProviderInterface;
use Clesson\Silverstripe\Geocoding\Models\GeocodingService;
use Clesson\Silverstripe\Geocoding\ORM\DBGeoCoordinate;
use Clesson\Silverstripe\Geocoding\Services\MapProviderRegistry;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;

/**
 * An interactive map form field for selecting geographic coordinates.
 *
 * Renders a map based on the map display service configured in SiteConfig.
 * If a DBGeoCoordinate value exists, displays a marker. The user can double-click
 * the map to update the coordinates, or drag the marker.
 *
 * User interaction:
 * - Single-click: Pan the map (no marker placement)
 * - Double-click: Place or move the marker
 * - Drag marker: Update coordinates
 *
 * The field is extensible — other modules can register their own map providers
 * (e.g. Bing Maps, Mapbox) via the MapProviderRegistry.
 *
 * Usage:
 * ```php
 * MapField::create('GeoCoordinates', $this->fieldLabel('GeoCoordinates'))
 * ```
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Forms
 */
class MapField extends FormField
{
    use Configurable;

    /**
     * Default map center latitude when no marker is set.
     * Configurable via YAML: Clesson\Silverstripe\Geocoding\Forms\MapField.default_latitude
     *
     * @config
     */
    private static float $default_latitude = 51.1657;

    /**
     * Default map center longitude when no marker is set.
     * Configurable via YAML: Clesson\Silverstripe\Geocoding\Forms\MapField.default_longitude
     *
     * @config
     */
    private static float $default_longitude = 10.4515;

    /**
     * The active map provider instance.
     */
    protected ?MapProviderInterface $provider = null;

    /**
     * The GeocodingService model used for map rendering.
     */
    protected ?GeocodingService $service = null;


    /**
     * Default zoom level.
     */
    protected int $zoomLevel = 6;

    /**
     * Zoom level when a marker is present.
     */
    protected int $zoomLevelWithMarker = 15;

    /**
     * Whether the user may place or move a marker by double-clicking the map.
     * Dragging an existing marker is also controlled by this flag.
     * Default is true.
     */
    protected bool $allowPlaceMarker = true;

    /**
     * Whether to show a button that removes the current marker and clears the coordinates.
     */
    protected bool $allowClear = true;

    /**
     * Whether to show a fullscreen toggle button.
     * Default is false — fullscreen must be explicitly enabled.
     */
    protected bool $allowFullscreen = false;

    /**
     * Whether to show a layer switcher control.
     * Only has an effect when the active provider returns layer options.
     * Default is false.
     */
    protected bool $allowLayerSwitcher = false;

    /**
     * The layer key to pre-select.
     * When empty the provider's default layer is used.
     */
    protected string $activeLayer = '';

    /**
     * Additional read-only markers to display on the map.
     *
     * @var MapMarker[]
     */
    protected array $additionalMarkers = [];

    /**
     * Optional route to display on the map.
     */
    protected ?MapRoute $route = null;

    /**
     * Optional custom icon URL for the primary (editable) marker.
     */
    protected string $primaryMarkerIconUrl = '';

    /**
     * Optional primary marker icon width in pixels.
     */
    protected int $primaryMarkerIconWidth = 0;

    /**
     * Optional primary marker icon height in pixels.
     */
    protected int $primaryMarkerIconHeight = 0;

    /**
     * Optional HTML shown in an info window when the primary marker is clicked.
     */
    protected string $primaryMarkerInfoWindow = '';

    /**
     * When true the map automatically zooms and pans so that all visible markers
     * (primary, additional and route waypoints) are inside the viewport.
     * When false the map uses the configured zoom level and center coordinates.
     * Default is false.
     */
    protected bool $fitBounds = false;

    /**
     * Sets the zoom level for the map.
     *
     * @param int $zoom Zoom level (1-20, where 1 = world view, 20 = street level)
     * @return static
     */
    public function setZoomLevel(int $zoom): static
    {
        $this->zoomLevel = $zoom;
        return $this;
    }

    /**
     * Sets the zoom level used when a marker is already present.
     *
     * @param int $zoom Zoom level (1-20)
     * @return static
     */
    public function setZoomLevelWithMarker(int $zoom): static
    {
        $this->zoomLevelWithMarker = $zoom;
        return $this;
    }

    /**
     * Enables or disables the clear button that removes the current marker.
     *
     * @param bool $allow
     * @return static
     */
    public function setAllowClear(bool $allow): static
    {
        $this->allowClear = $allow;
        return $this;
    }

    /**
     * Enables or disables manual marker placement and dragging.
     *
     * When set to false, the user can no longer place a new marker by
     * double-clicking the map, and any existing marker becomes non-draggable.
     * The clear button is also hidden automatically.
     * Default is true.
     *
     * @param bool $allow
     * @return static
     */
    public function setAllowPlaceMarker(bool $allow): static
    {
        $this->allowPlaceMarker = $allow;
        return $this;
    }

    /**
     * Enables or disables the fullscreen toggle button.
     *
     * @param bool $allow
     * @return static
     */
    public function setAllowFullscreen(bool $allow): static
    {
        $this->allowFullscreen = $allow;
        return $this;
    }

    /**
     * Enables or disables the layer switcher control.
     *
     * The switcher is only shown when the active provider also returns layer
     * options via getLayerOptions(). If the provider supports no layers the
     * switcher remains hidden regardless of this setting.
     *
     * @param bool $allow
     * @return static
     */
    public function setAllowLayerSwitcher(bool $allow): static
    {
        $this->allowLayerSwitcher = $allow;
        return $this;
    }

    /**
     * Pre-selects a specific layer by its provider layer key.
     *
     * The key must be one of the values returned by the provider's
     * getLayerOptions(). When not set the provider's default layer is used.
     *
     * @param string $layerKey
     * @return static
     */
    public function setActiveLayer(string $layerKey): static
    {
        $this->activeLayer = $layerKey;
        return $this;
    }

    /**
     * Adds an additional read-only marker to the map.
     *
     * Additional markers do not interact with the coordinate inputs and are
     * not saved when the form is submitted.
     *
     * @param MapMarker $marker
     * @return static
     */
    public function addMarker(MapMarker $marker): static
    {
        $this->additionalMarkers[] = $marker;
        return $this;
    }

    /**
     * Sets the route to display on the map.
     *
     * Only one route can be displayed at a time. Call this method again to
     * replace the existing route. Pass null to remove the route.
     *
     * @param MapRoute|null $route
     * @return static
     */
    public function setRoute(?MapRoute $route): static
    {
        $this->route = $route;
        return $this;
    }

    /**
     * Sets a custom icon for the primary (editable) marker.
     *
     * @param string $url    Absolute URL to an image file.
     * @param int    $width  Icon width in pixels (0 = provider default).
     * @param int    $height Icon height in pixels (0 = provider default).
     * @return static
     */
    public function setPrimaryMarkerIcon(string $url, int $width = 0, int $height = 0): static
    {
        $this->primaryMarkerIconUrl    = $url;
        $this->primaryMarkerIconWidth  = $width;
        $this->primaryMarkerIconHeight = $height;
        return $this;
    }

    /**
     * Sets the HTML content shown in the info window when the primary marker is clicked.
     *
     * @param string $html
     * @return static
     */
    public function setPrimaryMarkerInfoWindow(string $html): static
    {
        $this->primaryMarkerInfoWindow = $html;
        return $this;
    }

    /**
     * When enabled the map automatically fits its viewport to include all visible
     * markers: the primary marker, all additional markers and all route waypoints.
     *
     * When only a single point is visible the map zooms to ZoomLevelWithMarker
     * instead of fitting (a bounding box with a single point has no area).
     *
     * Default is false — the map uses the configured center and zoom level.
     *
     * @param bool $fit
     * @return static
     */
    public function setFitBounds(bool $fit): static
    {
        $this->fitBounds = $fit;
        return $this;
    }

    /**
     * Builds the map field and determines the provider from SiteConfig.
     *
     * @param string      $name  Field name, must match the composite DB field name.
     * @param string|null $title Optional label. Defaults to the field name.
     * @param mixed       $value Initial value (DBGeoCoordinate, array, or null).
     */
    public function __construct(string $name, ?string $title = null, mixed $value = null)
    {
        $this->setName($name);
        $this->detectProvider();

        parent::__construct($name, $title, $value);
    }

    /**
     * Determines the map provider based on the SiteConfig map display service.
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
     * Sets the value from a DBGeoCoordinate instance or an associative array.
     *
     * Example: ['Latitude' => 52.5163, 'Longitude' => 13.3777]
     *
     * @param mixed      $value
     * @param mixed|null $data
     * @return static
     */
    public function setValue($value, $data = null): static
    {
        if ($value instanceof DBGeoCoordinate) {
            $this->value = [
                'Latitude'  => $value->getLatitude(),
                'Longitude' => $value->getLongitude(),
            ];
        } elseif (is_array($value)) {
            $this->value = [
                'Latitude'  => $value['Latitude'] ?? null,
                'Longitude' => $value['Longitude'] ?? null,
            ];
        } else {
            $this->value = [
                'Latitude'  => null,
                'Longitude' => null,
            ];
        }

        return $this;
    }

    /**
     * Sets the value from submitted form data.
     *
     * @param mixed      $value
     * @param mixed|null $data
     * @return static
     */
    public function setSubmittedValue($value, $data = null): static
    {
        if (is_array($value)) {
            $this->value = [
                'Latitude'  => isset($value['Latitude']) && $value['Latitude'] !== '' ? (float) $value['Latitude'] : null,
                'Longitude' => isset($value['Longitude']) && $value['Longitude'] !== '' ? (float) $value['Longitude'] : null,
            ];
        } else {
            $this->value = [
                'Latitude'  => null,
                'Longitude' => null,
            ];
        }

        return $this;
    }

    /**
     * Returns the current value as an associative array.
     *
     * @return array{Latitude: float|null, Longitude: float|null}
     */
    public function dataValue(): array
    {
        return [
            'Latitude'  => $this->value['Latitude'] ?? null,
            'Longitude' => $this->value['Longitude'] ?? null,
        ];
    }

    /**
     * Saves the latitude and longitude values into the composite DB columns.
     *
     * Does nothing when the field is disabled or read-only.
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

        $record->{$name . 'Latitude'}  = $this->value['Latitude'] ?? null;
        $record->{$name . 'Longitude'} = $this->value['Longitude'] ?? null;
    }

    /**
     * Includes the necessary JavaScript and CSS for the map rendering.
     *
     * @param array $properties
     * @return string
     */
    public function Field($properties = []): string
    {
        $this->addMapAssets();

        // Merge template data into properties for template rendering
        $properties = array_merge($properties, $this->getTemplateData());

        return parent::Field($properties)->getValue();
    }

    /**
     * Adds the required CSS and JavaScript resources for the selected map provider.
     *
     * @return void
     */
    protected function addMapAssets(): void
    {
        // Always load shared utilities first
        Requirements::javascript('clesson-de/silverstripe-geocoding:client/admin/dist/map-utils.js');
        Requirements::javascript('clesson-de/silverstripe-geocoding:client/admin/dist/map-entwine.js');
        Requirements::css('clesson-de/silverstripe-geocoding:client/admin/dist/map-field.css');

        if ($this->provider === null || $this->service === null) {
            return;
        }

        // Add provider-specific CSS resources
        foreach ($this->provider->getCSSResources() as $css) {
            if (filter_var($css, FILTER_VALIDATE_URL)) {
                Requirements::css($css);
            } else {
                Requirements::css($css);
            }
        }

        // Add provider-specific JavaScript resources
        foreach ($this->provider->getJavaScriptResources($this->service) as $js) {
            if (filter_var($js, FILTER_VALIDATE_URL)) {
                Requirements::javascript($js);
            } else {
                Requirements::javascript($js);
            }
        }
    }

    /**
     * Returns template variables for rendering the map.
     *
     * @return array
     */
    protected function getTemplateData(): array
    {
        $providerKey    = $this->provider ? $this->provider->getProviderKey() : 'none';
        $providerConfig = ($this->provider && $this->service)
            ? $this->provider->getConfig($this->service)
            : [];

        $hasMarker  = isset($this->value['Latitude']) && isset($this->value['Longitude']);
        $isReadonly = $this->isReadonly() || $this->isDisabled();

        // When placing markers is disabled, the clear button makes no sense either
        $allowClear = $this->allowClear && $this->allowPlaceMarker;

        // Resolve layer options from the provider (provider-specific)
        $layerOptions  = $this->provider ? $this->provider->getLayerOptions() : [];
        $defaultLayer  = $this->provider ? $this->provider->getDefaultLayer() : '';
        $activeLayer   = ($this->activeLayer !== '' && isset($layerOptions[$this->activeLayer]))
            ? $this->activeLayer
            : $defaultLayer;
        // Only show the switcher when the provider actually has multiple layers
        $showLayerSwitcher = $this->allowLayerSwitcher && count($layerOptions) > 1;

        return [
            'Provider'               => $providerKey,
            'ProviderConfig'         => json_encode($providerConfig),
            'Latitude'               => $this->value['Latitude'] ?? self::config()->get('default_latitude'),
            'Longitude'              => $this->value['Longitude'] ?? self::config()->get('default_longitude'),
            'HasMarker'              => $hasMarker,
            'ZoomLevel'              => $this->zoomLevel,
            'ZoomLevelWithMarker'    => $this->zoomLevelWithMarker,
            'LatitudeFieldID'        => $this->ID() . '_Latitude',
            'LongitudeFieldID'       => $this->ID() . '_Longitude',
            'IsReadonly'             => $isReadonly,
            'AllowClear'             => $allowClear,
            'AllowPlaceMarker'       => $this->allowPlaceMarker,
            'AllowFullscreen'        => $this->allowFullscreen,
            'LayerOptions'           => $layerOptions,
            'ActiveLayer'            => $activeLayer,
            'ShowLayerSwitcher'      => $showLayerSwitcher,
            // Primary marker customisation
            'PrimaryMarkerIconUrl'    => $this->primaryMarkerIconUrl,
            'PrimaryMarkerIconWidth'  => $this->primaryMarkerIconWidth,
            'PrimaryMarkerIconHeight' => $this->primaryMarkerIconHeight,
            'PrimaryMarkerInfoWindow' => $this->primaryMarkerInfoWindow,
            // Additional markers (read-only, not saved)
            'AdditionalMarkers'      => json_encode(
                array_map(fn(MapMarker $m) => $m->toArray(), $this->additionalMarkers)
            ),
            // Route
            'Route'                  => $this->route ? json_encode($this->route->toArray()) : 'null',
            // Fit viewport to all markers
            'FitBounds'              => $this->fitBounds,
            'LabelRemoveMarker'      => _t('Clesson\\Silverstripe\\Geocoding\\Forms\\MapField.REMOVE_MARKER', 'Remove marker'),
            'LabelFullscreen'        => _t('Clesson\\Silverstripe\\Geocoding\\Forms\\MapField.FULLSCREEN', 'Fullscreen'),
            'LabelExitFullscreen'    => _t('Clesson\\Silverstripe\\Geocoding\\Forms\\MapField.EXIT_FULLSCREEN', 'Exit fullscreen'),
            'LabelLayer'             => _t('Clesson\\Silverstripe\\Geocoding\\Forms\\MapField.LAYER', 'Layer'),
        ];
    }

}

