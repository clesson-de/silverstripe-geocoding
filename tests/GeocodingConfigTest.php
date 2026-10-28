<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Tests;

use Clesson\Silverstripe\Forms\GridField\GridFieldAddExistingSelect;
use Clesson\Silverstripe\Forms\GridField\GridFieldAddNewDropdown;
use Clesson\Silverstripe\Geocoding\Forms\GridFieldConfig_GeocodeServicesInSiteConfig;
use Clesson\Silverstripe\Geocoding\Forms\GridFieldConfig_ReverseGeocodeServicesInSiteConfig;
use Clesson\Silverstripe\Geocoding\Forms\GridFieldConfig_RouteServicesInSiteConfig;
use Clesson\Silverstripe\Geocoding\Models\OpenStreetMapService;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\SiteConfig\SiteConfigLeftAndMain;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\SSViewer;
use DOMDocument;
use DOMXPath;

/**
 * Verifies grouped settings preserve their editable fields and relation controls.
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Tests
 */
class GeocodingConfigTest extends SapphireTest
{
    protected $usesDatabase = true;

    /** All four cards contain a heading, description and the original form field. */
    public function testConfigurationCards(): void
    {
        $this->logInWithPermission('ADMIN');
        $owner = SiteConfig::current_site_config();
        $fields = $owner->getCMSFields();
        $form = Form::create(SiteConfigLeftAndMain::create(), 'SettingsTest', $fields, FieldList::create());
        $form->loadDataFrom($owner);
        $tab = $fields->fieldByName('Root.Geocoding');
        $layout = $tab->getChildren()->first();
        self::assertStringContainsString('geocoding-config-cards', $layout->extraClass());
        self::assertCount(4, $layout->getChildren());
        foreach ($layout->getChildren() as $card) {
            self::assertStringContainsString('geocoding-config-card', $card->extraClass());
            self::assertCount(3, $card->getChildren());
        }
        self::assertInstanceOf(DropdownField::class, $fields->dataFieldByName('Geocoding_MapDisplayServiceID'));
        foreach ([
            'Route' => GridFieldConfig_RouteServicesInSiteConfig::class,
            'ReverseGeocode' => GridFieldConfig_ReverseGeocodeServicesInSiteConfig::class,
            'Geocode' => GridFieldConfig_GeocodeServicesInSiteConfig::class,
        ] as $method => $configClass) {
            $grid = $fields->dataFieldByName('Geocoding_' . $method . 'Services');
            self::assertInstanceOf(GridField::class, $grid);
            self::assertSame($configClass, get_class($grid->getConfig()));
            $picker = $grid->getConfig()->getComponentByType(GridFieldAddExistingSelect::class);
            self::assertInstanceOf(GridFieldAddExistingSelect::class, $picker);
            self::assertSame(['Name'], $picker->getSearchFields());
            self::assertSame('$Name', $picker->getResultsFormat());
            self::assertInstanceOf(
                GridFieldAddNewDropdown::class, $grid->getConfig()->getComponentByType(GridFieldAddNewDropdown::class)
            );
        }
        $themes = SSViewer::get_themes();
        SSViewer::set_themes(['silverstripe/admin:cms-forms', '$default']);
        try {
            $html = (string) $layout->FieldHolder();
        } finally {
            SSViewer::set_themes($themes);
        }
        self::assertStringContainsString('name="Geocoding_MapDisplayServiceID"', $html);
        self::assertStringContainsString('Geocoding_RouteServices', $html);
        self::assertSame(3, substr_count($html, 'data-search-url='));
        self::assertSame(3, substr_count($html, 'aria-haspopup="menu"'));
        self::assertStringNotContainsString('data-add-multiclass', $html);
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        self::assertSame(4, $xpath->query(
            '//div[contains(@class, "geocoding-config-cards")]/div[contains(@class, "geocoding-config-card")]'
        )->length);
        self::assertSame(4, $xpath->query(
            '//div[contains(@class, "geocoding-config-card")]/h2'
        )->length);
    }

    /** Each through relation searches by name and excludes services already assigned to it. */
    public function testServicePickersSearchTheirOwnRelations(): void
    {
        $this->logInWithPermission('ADMIN');
        $owner = SiteConfig::current_site_config();
        $service = OpenStreetMapService::create(['Name' => 'Reusable provider']);
        $service->write();
        $fields = $owner->getCMSFields();
        $form = Form::create(SiteConfigLeftAndMain::create(), 'SettingsTest', $fields, FieldList::create());
        $form->loadDataFrom($owner);
        foreach (['Route', 'ReverseGeocode', 'Geocode'] as $method) {
            $grid = $fields->dataFieldByName('Geocoding_' . $method . 'Services');
            $request = new HTTPRequest('GET', 'existing-search', ['gridfield_relationsearch' => 'Reusable']);
            $response = $grid->handleRequest($request);
            $results = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame([(int)$service->ID], array_column($results, 'id'));
            self::assertSame('Reusable provider', $results[0]['label']);
            $this->linkService($grid, (int)$service->ID);
            self::assertTrue($grid->getList()->byID($service->ID)->exists());
            $response = $grid->handleRequest(new HTTPRequest(
                'GET', 'existing-search', ['gridfield_relationsearch' => 'Reusable']
            ));
            self::assertSame('[]', $response->getBody());
        }
    }

    /** Submits the generated GridField action with the settings form's security token. */
    private function linkService(GridField $grid, int $id): void
    {
        preg_match('/name="(action_gridFieldAlterAction[^"]+)"/', (string)$grid->FieldHolder(), $match);
        $form = $grid->getForm();
        $token = $form->getSecurityToken();
        $request = new HTTPRequest('POST', '/settings', [], [
            html_entity_decode($match[1]) => '1',
            $grid->getName() => ['ExistingID' => (string)$id],
            $token->getName() => $token->getValue(),
        ]);
        $request->setSession(Controller::curr()->getRequest()->getSession());
        $form->getController()->setRequest($request);
        $grid->gridFieldAlterAction([], $form, $request);
    }
}
