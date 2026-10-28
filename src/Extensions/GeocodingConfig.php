<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Extensions;

use Clesson\Silverstripe\Geocoding\Forms\GridFieldConfig_GeocodeServicesInSiteConfig;
use Clesson\Silverstripe\Geocoding\Forms\GridFieldConfig_ReverseGeocodeServicesInSiteConfig;
use Clesson\Silverstripe\Geocoding\Forms\GridFieldConfig_RouteServicesInSiteConfig;
use Clesson\Silverstripe\Geocoding\Models\GeocodingService;
use Clesson\Silverstripe\Geocoding\Models\SiteConfigGeocodeService;
use Clesson\Silverstripe\Geocoding\Models\SiteConfigReverseGeocodeService;
use Clesson\Silverstripe\Geocoding\Models\SiteConfigRouteService;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\ManyManyThroughList;

/**
 * Extends SiteConfig with three many_many lists for geocoding services.
 *
 * Services are assigned to specific methods (Geocode, ReverseGeocode, Route)
 * via dedicated join classes. Each join record stores a daily quota limit
 * and sort order for priority (drag-and-drop).
 *
 * @property int $Geocoding_MapDisplayServiceID
 *
 * @method ManyManyThroughList|GeocodingService[] Geocoding_GeocodeServices()
 * @method ManyManyThroughList|GeocodingService[] Geocoding_ReverseGeocodeServices()
 * @method ManyManyThroughList|GeocodingService[] Geocoding_RouteServices()
 * @method GeocodingService Geocoding_MapDisplayService()
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Extensions
 */
class GeocodingConfig extends Extension
{

    private static array $has_one = [
        'Geocoding_MapDisplayService' => GeocodingService::class,
    ];

    private static array $many_many = [
        'Geocoding_GeocodeServices'        => [
            'through' => SiteConfigGeocodeService::class,
            'from'    => 'SiteConfig',
            'to'      => 'GeocodingService',
        ],
        'Geocoding_ReverseGeocodeServices' => [
            'through' => SiteConfigReverseGeocodeService::class,
            'from'    => 'SiteConfig',
            'to'      => 'GeocodingService',
        ],
        'Geocoding_RouteServices'          => [
            'through' => SiteConfigRouteService::class,
            'from'    => 'SiteConfig',
            'to'      => 'GeocodingService',
        ],
    ];

    public function updateFieldLabels(&$labels): void
    {
        $labels['Geocoding_MapDisplayService']      = _t(__CLASS__ . '.MAP_DISPLAY_SERVICE', 'Map display service');
        $labels['Geocoding_GeocodeServices']        = _t(__CLASS__ . '.GEOCODE_SERVICES', 'Geocoding services');
        $labels['Geocoding_ReverseGeocodeServices'] = _t(__CLASS__ . '.REVERSE_GEOCODE_SERVICES', 'Reverse geocoding services');
        $labels['Geocoding_RouteServices']          = _t(__CLASS__ . '.ROUTE_SERVICES', 'Routing services');
    }

    public function updateCMSFields(FieldList $fields): void
    {
        /** @var Tab $geocodingTab */
        $geocodingTab = Tab::create(
            'Geocoding',
            _t(__CLASS__ . '.GEOCODING_TAB', 'Geocoding')
        );
        $fields->addFieldToTab('Root', $geocodingTab);

        /** @var DropdownField $mapDisplayServiceField */
        $mapDisplayServiceField = DropdownField::create(
            'Geocoding_MapDisplayServiceID',
            $this->getOwner()->fieldLabel('Geocoding_MapDisplayService'),
            GeocodingService::get()->filter('Active', true)->map('ID', 'Name')
        );
        $mapDisplayServiceField->setEmptyString(_t(__CLASS__ . '.MAP_DISPLAY_SERVICE_EMPTY', '-- Select a service --'));

        /** @var HeaderField $geocodeHeader */
        $geocodeHeader = HeaderField::create(
            'GeocodeServicesHeader',
            _t(__CLASS__ . '.GEOCODE_SERVICES_HEADER', 'Geocoding (Address → Coordinates)'),
            2
        );

        /** @var LiteralField $geocodeDescription */
        $geocodeDescription = LiteralField::create(
            'GeocodeServicesDescription',
            '<p class="message">' . _t(
                __CLASS__ . '.GEOCODE_SERVICES_DESCRIPTION',
                'Convert street addresses into geographic coordinates (latitude/longitude). Services are processed in order (drag to reorder) — the first with available quota is used.'
            ) . '</p>'
        );

        /** @var GridField $geocodeServicesField */
        $geocodeServicesField = GridField::create(
            'Geocoding_GeocodeServices',
            $this->getOwner()->fieldLabel('Geocoding_GeocodeServices'),
            $this->getOwner()->Geocoding_GeocodeServices(),
            GridFieldConfig_GeocodeServicesInSiteConfig::create()
        );

        /** @var HeaderField $reverseGeocodeHeader */
        $reverseGeocodeHeader = HeaderField::create(
            'ReverseGeocodeServicesHeader',
            _t(__CLASS__ . '.REVERSE_GEOCODE_SERVICES_HEADER', 'Reverse Geocoding (Coordinates → Address)'),
            2
        );

        /** @var LiteralField $reverseGeocodeDescription */
        $reverseGeocodeDescription = LiteralField::create(
            'ReverseGeocodeServicesDescription',
            '<p class="message">' . _t(
                __CLASS__ . '.REVERSE_GEOCODE_SERVICES_DESCRIPTION',
                'Resolve geographic coordinates (latitude/longitude) back into human-readable addresses. Services are processed in order (drag to reorder) — the first with available quota is used.'
            ) . '</p>'
        );

        /** @var GridField $reverseGeocodeServicesField */
        $reverseGeocodeServicesField = GridField::create(
            'Geocoding_ReverseGeocodeServices',
            $this->getOwner()->fieldLabel('Geocoding_ReverseGeocodeServices'),
            $this->getOwner()->Geocoding_ReverseGeocodeServices(),
            GridFieldConfig_ReverseGeocodeServicesInSiteConfig::create()
        );

        /** @var HeaderField $routeHeader */
        $routeHeader = HeaderField::create(
            'RouteServicesHeader',
            _t(__CLASS__ . '.ROUTE_SERVICES_HEADER', 'Routing (Calculate Routes)'),
            2
        );

        /** @var LiteralField $routeDescription */
        $routeDescription = LiteralField::create(
            'RouteServicesDescription',
            '<p class="message">' . _t(
                __CLASS__ . '.ROUTE_SERVICES_DESCRIPTION',
                'Calculate routes, distances and travel times between locations. Services are processed in order (drag to reorder) — the first with available quota is used.'
            ) . '</p>'
        );

        /** @var GridField $routeServicesField */
        $routeServicesField = GridField::create(
            'Geocoding_RouteServices',
            $this->getOwner()->fieldLabel('Geocoding_RouteServices'),
            $this->getOwner()->Geocoding_RouteServices(),
            GridFieldConfig_RouteServicesInSiteConfig::create()
        );
        $geocodingTab->setChildren(FieldList::create($this->cards([
            [$this->mapDisplayHeader(), $this->mapDisplayDescription(), $mapDisplayServiceField],
            [$routeHeader, $routeDescription, $routeServicesField],
            [$reverseGeocodeHeader, $reverseGeocodeDescription, $reverseGeocodeServicesField],
            [$geocodeHeader, $geocodeDescription, $geocodeServicesField],
        ])));
    }

    /** Reuses the existing field instances to preserve values, relation actions and validation. */
    private function cards(array $groups): CompositeField
    {
        $cards = [];
        foreach ($groups as $index => $group) {
            $card = CompositeField::create(...$group)->setName('GeocodingCard' . $index);
            $card->setFieldHolderTemplate('Clesson/Silverstripe/Geocoding/Forms/GeocodingConfigGroup');
            $card->addExtraClass('geocoding-config-card');
            $cards[] = $card;
        }
        $layout = CompositeField::create(...$cards)->setName('GeocodingCards');
        $layout->setFieldHolderTemplate('Clesson/Silverstripe/Geocoding/Forms/GeocodingConfigGroup');
        return $layout->addExtraClass('geocoding-config-cards');
    }

    /** Gives map display the same visual hierarchy as the three lookup services. */
    private function mapDisplayHeader(): HeaderField
    {
        return HeaderField::create('MapDisplayHeader', _t(__CLASS__ . '.MAP_DISPLAY_SERVICE', 'Map display service'), 2);
    }

    /** Keeps descriptions outside form-field labels for a consistent card layout. */
    private function mapDisplayDescription(): LiteralField
    {
        $description = _t(__CLASS__ . '.MAP_DISPLAY_SERVICE_DESCRIPTION',
            'Choose which geocoding service provider should render interactive maps (e.g. in the CMS).');
        return LiteralField::create('MapDisplayDescription', '<p class="message">'
            . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>');
    }
}
