# Eigene Karten-Provider hinzufügen

Diese Anleitung erklärt, wie Sie Unterstützung für zusätzliche Karten-Provider (z.B. Bing Maps, Mapbox, HERE Maps) in Ihrem eigenen Modul hinzufügen.

---

## Überblick

Das Geocoding-Modul verwendet eine **provider-basierte Architektur**, die es externen Modulen ermöglicht, eigene Karten-Provider zu registrieren, ohne den Core-Code zu ändern. Jeder Provider implementiert das `MapProviderInterface` und registriert sich über YAML-Konfiguration.

---

## Schritt 1: GeocodingService-Unterklasse erstellen

Erstellen Sie zunächst ein DataObject-Model, das `GeocodingService` erweitert und die spezifischen Konfigurationen Ihres Providers speichert (z.B. API-Schlüssel, Basis-URL):

```php
<?php

namespace MyVendor\MyModule\Models;

use Clesson\Silverstripe\Geocoding\Models\GeocodingService;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;

class BingService extends GeocodingService
{
    private static string $table_name = 'MyModule_BingService';

    private static array $db = [
        'ApiKey' => 'Varchar(512)',
    ];

    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['ApiKey'] = _t(__CLASS__ . '.API_KEY', 'Bing Maps API-Schlüssel');
        return $labels;
    }

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['ApiKey']);

        /** @var TextField $apiKeyField */
        $apiKeyField = TextField::create('ApiKey', $this->fieldLabel('ApiKey'));
        $fields->addFieldToTab('Root.Main', $apiKeyField);

        return $fields;
    }

    // Implementieren Sie geocode(), reverseGeocode(), route() Methoden hier
}
```

---

## Schritt 2: MapProviderInterface implementieren

Erstellen Sie eine Provider-Klasse, die `Clesson\Silverstripe\Geocoding\Interfaces\MapProviderInterface` implementiert:

```php
<?php

namespace MyVendor\MyModule\Providers;

use Clesson\Silverstripe\Geocoding\Interfaces\MapProviderInterface;
use Clesson\Silverstripe\Geocoding\Models\GeocodingService;
use MyVendor\MyModule\Models\BingService;

class BingMapProvider implements MapProviderInterface
{
    public function getProviderKey(): string
    {
        return 'bing';
    }

    public function getProviderName(): string
    {
        return 'Bing Maps';
    }

    public function supports(GeocodingService $service): bool
    {
        return $service instanceof BingService;
    }

    public function getConfig(GeocodingService $service): array
    {
        if (!$service instanceof BingService) {
            return [];
        }

        return [
            'apiKey' => $service->ApiKey,
        ];
    }

    public function getCSSResources(): array
    {
        return [
            'myvendor-mymodule/client/admin/dist/bing-map.css',
        ];
    }

    public function getJavaScriptResources(GeocodingService $service): array
    {
        if (!$service instanceof BingService) {
            return [];
        }

        return [
            'https://www.bing.com/api/maps/mapcontrol?key=' . $service->ApiKey,
            'myvendor-mymodule/client/admin/dist/bing-map.js',
        ];
    }
}
```

---

## Schritt 3: JavaScript implementieren

Die JavaScript-Implementierung folgt dem gleichen Muster wie die bestehenden Provider. Weitere Details finden Sie in der [englischen Version dieser Dokumentation](adding-custom-providers.md#step-3-implement-the-javascript).

---

## Schritt 4: Provider via YAML registrieren

In der `_config/config.yml` Ihres Moduls:

```yaml
---
Name: mymodule-map-provider
After:
  - '#geocoding-config'
---
Clesson\Silverstripe\Geocoding\Services\MapProviderRegistry:
  providers:
    bing: 'MyVendor\MyModule\Providers\BingMapProvider'
```

---

## Schritt 5: Assets exposen

In der `composer.json` Ihres Moduls:

```json
{
    "extra": {
        "expose": [
            "client/admin/dist"
        ]
    }
}
```

Nach der Installation müssen Benutzer ausführen:

```bash
composer vendor-expose
```

---

## Erweiterbarkeit

### Custom Events

Das Modul löst ein `geocoding:coordinates-updated` Event aus, wenn sich Koordinaten ändern:

```javascript
window.addEventListener('geocoding:coordinates-updated', function(event) {
    console.log('Neue Koordinaten:', event.detail.latitude, event.detail.longitude);
});
```

---

## Hilfe benötigt?

Öffnen Sie ein Issue auf GitHub oder konsultieren Sie die Silverstripe-Dokumentation.

