<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Tests;

use Clesson\Silverstripe\Geocoding\Models\OpenStreetMapService;
use Clesson\Silverstripe\Geocoding\Models\GoogleService;
use DOMDocument;
use DOMXPath;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\SecurityToken;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\SiteConfig\SiteConfigLeftAndMain;

/**
 * Covers stylesheet loading in the actual CMS settings response.
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Tests
 */
class GeocodingSettingsPageTest extends FunctionalTest
{
    /** Settings must load the card stylesheet as well as the card markup. */
    public function testSettingsLoadsCardStyles(): void
    {
        $this->logInWithPermission('ADMIN');
        $response = $this->get(SiteConfigLeftAndMain::singleton()->Link());
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('geocoding-config-cards', $response->getBody());
        self::assertMatchesRegularExpression(
            '/<link[^>]+href="[^"]*geocoding-config\.css[^"]*"/',
            $response->getBody()
        );
        self::assertMatchesRegularExpression(
            '/<script[^>]+src="[^"]*silverstripe-gridfield-pro\.js[^"]*"/',
            $response->getBody()
        );
        self::assertMatchesRegularExpression(
            '/<link[^>]+href="[^"]*silverstripe-gridfield-pro\.css[^"]*"/',
            $response->getBody()
        );
    }

    /** Opens the chosen concrete type, validates it and saves into the correct through relation. */
    public function testDropdownCreatesSelectedService(): void
    {
        $this->logInWithPermission('ADMIN');
        SecurityToken::enable();
        $response = $this->get(SiteConfigLeftAndMain::singleton()->Link());
        $xpath = $this->xpath($response->getBody());
        $link = $xpath->query('//a[contains(@class, "gridfield-add-new-dropdown__link")'
            . ' and contains(@href, "Geocoding_RouteServices") and contains(@href, "OpenStreetMapService")]')->item(0);
        self::assertNotNull($link);
        $url = $link->getAttribute('href');
        $response = $this->get($url);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('RateLimitMs', $response->getBody());
        $form = $this->xpath($response->getBody())->query('//form[.//input[@name="Name"]]')->item(0);
        self::assertNotNull($form);
        self::assertStringContainsString('/new-class/', $form->getAttribute('action'));
        $formID = $form->getAttribute('id');
        $response = $this->submitForm($formID, 'action_doSave', ['Name' => ''], true);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('/new-class/', $response->getBody());
        self::assertSame(0, OpenStreetMapService::get()->filter('Name', 'Created using dropdown')->count());
        $response = $this->submitForm($formID, 'action_doSave', ['Name' => 'Created using dropdown'], true);
        self::assertLessThan(400, $response->getStatusCode());
        $record = OpenStreetMapService::get()->filter('Name', 'Created using dropdown')->first();
        self::assertNotNull($record);
        self::assertTrue(SiteConfig::current_site_config()->Geocoding_RouteServices()->byID($record->ID)->exists());
        self::assertCount(0, SiteConfig::current_site_config()->Geocoding_GeocodeServices());
    }

    /** Creation remains CSRF protected and cannot be changed to another class by posted data. */
    public function testDropdownRejectsInvalidCreationPosts(): void
    {
        $this->logInWithPermission('ADMIN');
        SecurityToken::enable();
        $response = $this->get(SiteConfigLeftAndMain::singleton()->Link());
        $link = $this->xpath($response->getBody())->query('//a[contains(@class, "gridfield-add-new-dropdown__link")'
            . ' and contains(@href, "Geocoding_RouteServices") and contains(@href, "OpenStreetMapService")]')->item(0);
        $response = $this->get($link->getAttribute('href'));
        $form = $this->xpath($response->getBody())->query('//form[.//input[@name="Name"]]')->item(0);
        $action = $form->getAttribute('action');
        $response = $this->post($action, ['action_doSave' => '1', 'Name' => 'Must not be created', 'SecurityID' => 'invalid']);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, OpenStreetMapService::get()->filter('Name', 'Must not be created')->count());
        $response = $this->post($action, ['action_doSave' => '1', 'Name' => 'Must not be created']);
        self::assertSame(400, $response->getStatusCode());
        $response = $this->get($link->getAttribute('href'));
        $token = $this->xpath($response->getBody())->query('//input[@name="SecurityID"]')->item(0)->getAttribute('value');
        $response = $this->post($action, [
            'action_doSave' => '1', 'Name' => 'Must not be created', 'SecurityID' => $token, 'ClassName' => GoogleService::class,
        ]);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(0, OpenStreetMapService::get()->filter('Name', 'Must not be created')->count());
        self::assertSame(0, GoogleService::get()->filter('Name', 'Must not be created')->count());
    }

    /** Parses rendered CMS HTML without relying on fixed form IDs. */
    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return new DOMXPath($document);
    }
}
