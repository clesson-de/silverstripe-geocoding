# Adding custom map providers

This guide explains how to add support for additional map providers (e.g. Bing Maps, Mapbox, HERE Maps) in your own module.

---

## Overview

The geocoding module uses a **provider-based architecture** that allows external modules to register their own map providers without modifying core code. Each provider implements the `MapProviderInterface` and registers itself via YAML configuration.

---

## Step 1: Create a GeocodingService subclass

First, create a DataObject model that extends `GeocodingService` and stores your provider's specific configuration (e.g. API key, base URL):

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
        $labels['ApiKey'] = _t(__CLASS__ . '.API_KEY', 'Bing Maps API key');
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

    // Implement geocode(), reverseGeocode(), route() methods here
}
```

---

## Step 2: Implement the MapProviderInterface

Create a provider class that implements `Clesson\Silverstripe\Geocoding\Interfaces\MapProviderInterface`:

```php
<?php

namespace MyVendor\MyModule\Providers;

use Clesson\Silverstripe\Geocoding\Interfaces\MapProviderInterface;
use Clesson\Silverstripe\Geocoding\Models\GeocodingService;
use MyVendor\MyModule\Models\BingService;

class BingMapProvider implements MapProviderInterface
{
    /**
     * Unique provider key (used in data-provider attribute).
     */
    public function getProviderKey(): string
    {
        return 'bing';
    }

    /**
     * Human-readable provider name.
     */
    public function getProviderName(): string
    {
        return 'Bing Maps';
    }

    /**
     * Check if this provider supports the given service model.
     */
    public function supports(GeocodingService $service): bool
    {
        return $service instanceof BingService;
    }

    /**
     * Return configuration data for JavaScript (passed as JSON).
     */
    public function getConfig(GeocodingService $service): array
    {
        if (!$service instanceof BingService) {
            return [];
        }

        return [
            'apiKey' => $service->ApiKey,
        ];
    }

    /**
     * CSS resources (CDN or module paths).
     */
    public function getCSSResources(): array
    {
        return [
            'myvendor-mymodule/client/admin/dist/bing-map.css',
        ];
    }

    /**
     * JavaScript resources (CDN or module paths).
     */
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

## Step 3: Implement the JavaScript

Create a JavaScript file (e.g. `bing-map.js`) that initializes maps for your provider.

**Important:** Use the shared `GeocodingMapUtils` utility to ensure consistent behavior across all providers.

```javascript
(function() {
    'use strict';

    // Use the shared utilities provided by the geocoding module
    const Utils = window.GeocodingMapUtils;

    function initBingMaps() {
        const containers = document.querySelectorAll('.geocoding-map-container[data-provider="bing"]');
        
        containers.forEach(container => {
            try {
                // Use Utils to parse config and parameters
                const config = Utils.parseProviderConfig(container);
                const params = Utils.getMapParameters(container);
                const apiKey = config.apiKey;

                if (!apiKey) {
                    Utils.showError(container, 'Bing Maps API key not configured.');
                    return;
                }

                // Initialize Bing Maps here...
                // See: https://docs.microsoft.com/en-us/bingmaps/v8-web-control/

                const map = new Microsoft.Maps.Map(container, {
                    credentials: apiKey,
                    center: new Microsoft.Maps.Location(params.latitude, params.longitude),
                    zoom: params.zoom,
                });

                let pin = null;

                if (params.hasMarker) {
                    pin = new Microsoft.Maps.Pushpin(map.getCenter(), { draggable: true });
                    map.entities.push(pin);

                    Microsoft.Maps.Events.addHandler(pin, 'dragend', function(e) {
                        const loc = e.target.getLocation();
                        // Use Utils to update fields and dispatch events
                        Utils.updateCoordinateFields(
                            params.latFieldId, 
                            params.lngFieldId, 
                            loc.latitude, 
                            loc.longitude
                        );
                    });
                }

                Microsoft.Maps.Events.addHandler(map, 'click', function(e) {
                    const loc = e.location;
                    
                    if (!pin) {
                        pin = new Microsoft.Maps.Pushpin(loc, { draggable: true });
                        map.entities.push(pin);
                        
                        Microsoft.Maps.Events.addHandler(pin, 'dragend', function(ev) {
                            const l = ev.target.getLocation();
                            Utils.updateCoordinateFields(
                                params.latFieldId, 
                                params.lngFieldId, 
                                l.latitude, 
                                l.longitude
                            );
                        });
                    } else {
                        pin.setLocation(loc);
                    }

                    Utils.updateCoordinateFields(
                        params.latFieldId, 
                        params.lngFieldId, 
                        loc.latitude, 
                        loc.longitude
                    );
                });
            } catch (error) {
                console.error('Error initializing Bing Maps:', error);
                Utils.showError(container, 'Failed to initialize Bing Maps.');
            }
        });
    }

    if (typeof Microsoft !== 'undefined' && Microsoft.Maps) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initBingMaps);
        } else {
            initBingMaps();
        }
    } else {
        window.bingMapsCallback = initBingMaps;
    }

})();
```

## Step 3: Implement the JavaScript

Create a JavaScript file (e.g. `bing-map.js`) that initializes maps for your provider.

**Important:** 
1. Use the shared `GeocodingMapUtils` utility to ensure consistent behavior
2. Register your provider initialization function with the registry
3. The Entwine integration (`map-entwine.js`) will automatically call your function when containers appear (including AJAX-loaded content)

```javascript
(function() {
    'use strict';

    // Use the shared utilities provided by the geocoding module
    const Utils = window.GeocodingMapUtils;

    /**
     * Initializes a single Bing Maps container.
     * This function will be called by Entwine for each container.
     *
     * @param {HTMLElement} container
     */
    function initBingMap(container) {
        // Use Utils to parse config and parameters
        const config = Utils.parseProviderConfig(container);
        const params = Utils.getMapParameters(container);
        const apiKey = config.apiKey;

        if (!apiKey) {
            Utils.showError(container, 'Bing Maps API key not configured.');
            return;
        }

        if (typeof Microsoft === 'undefined' || !Microsoft.Maps) {
            Utils.showError(container, 'Bing Maps library not loaded.');
            return;
        }

        // Initialize Bing Maps
        const map = new Microsoft.Maps.Map(container, {
            credentials: apiKey,
            center: new Microsoft.Maps.Location(params.latitude, params.longitude),
            zoom: params.zoom,
        });

        let pin = null;

        if (params.hasMarker) {
            pin = new Microsoft.Maps.Pushpin(map.getCenter(), { draggable: true });
            map.entities.push(pin);

            Microsoft.Maps.Events.addHandler(pin, 'dragend', function(e) {
                const loc = e.target.getLocation();
                Utils.updateCoordinateFields(
                    params.latFieldId, 
                    params.lngFieldId, 
                    loc.latitude, 
                    loc.longitude
                );
            });
        }

        Microsoft.Maps.Events.addHandler(map, 'dblclick', function(e) {
            const loc = e.location;
            
            if (!pin) {
                pin = new Microsoft.Maps.Pushpin(loc, { draggable: true });
                map.entities.push(pin);
                
                Microsoft.Maps.Events.addHandler(pin, 'dragend', function(ev) {
                    const l = ev.target.getLocation();
                    Utils.updateCoordinateFields(
                        params.latFieldId, 
                        params.lngFieldId, 
                        l.latitude, 
                        l.longitude
                    );
                });
            } else {
                pin.setLocation(loc);
            }

            Utils.updateCoordinateFields(
                params.latFieldId, 
                params.lngFieldId, 
                loc.latitude, 
                loc.longitude
            );
        });
    }

    /**
     * Batch initialization for all Bing Maps containers.
     * Called on initial page load.
     */
    function initBingMaps() {
        Utils.initializeAllMaps('bing');
    }

    // Register this provider with GeocodingMapUtils
    // The Entwine integration will call initBingMap() for each container
    if (Utils && Utils.registerProvider) {
        Utils.registerProvider('bing', initBingMap);
    }

    // Initialize all containers on page load
    if (typeof Microsoft !== 'undefined' && Microsoft.Maps) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initBingMaps);
        } else {
            initBingMaps();
        }
    } else {
        // Wait for Bing Maps API to load
        window.bingMapsCallback = initBingMaps;
    }

})();
```

### Shared utilities (GeocodingMapUtils)

The geocoding module provides `window.GeocodingMapUtils` with helper functions:

| Method | Purpose |
|---|---|
| `registerProvider(key, initFn)` | Register your initialization function for Entwine integration |
| `initializeMap(container, key)` | Initialize a single container (called by Entwine) |
| `initializeAllMaps(key)` | Initialize all containers for a provider (batch) |
| `parseProviderConfig(container)` | Safely parse JSON from `data-provider-config` |
| `getMapParameters(container)` | Extract all standard data attributes (lat, lng, zoom, **isReadonly**, etc.) |
| `updateCoordinateFields(latId, lngId, lat, lng)` | Update hidden inputs and dispatch event |
| `showError(container, message)` | Display user-friendly error message |
| `escapeHtml(text)` | Sanitize text for safe HTML injection |

### Key points:

1. **Register your provider** — call `Utils.registerProvider('key', initFunction)` to enable Entwine integration
2. **Single-container init** — implement a function that initializes ONE container (called by Entwine)
3. **Batch init** — also provide a batch function for initial page load using `Utils.initializeAllMaps()`
4. **Use shared utilities** — `window.GeocodingMapUtils` for parsing config, updating fields, showing errors
5. **Error handling** — use `Utils.showError()` for user-friendly error messages
6. **Double-click** — use `dblclick` event to place/move markers (prevents accidental placement during map panning)
7. **Readonly/Disabled** — always check `params.isReadonly` and skip all editing interactions accordingly

**Benefits of this pattern:**
- ✅ Works with AJAX-loaded content (GridFields, tabs)
- ✅ Prevents double initialization
- ✅ Consistent behavior across all providers
- ✅ Automatic event dispatching
- ✅ Reduced code duplication
- ✅ User-friendly: single-click pans, double-click places marker
- ✅ Respects readonly/disabled state automatically

---

## Readonly and disabled state

When a `MapField` is set to readonly or disabled (e.g. in a `DetailForm`, a permissions-restricted view, or
explicitly via `$field->setReadonly(true)`), the field communicates this to JavaScript via the
`data-readonly="true"` attribute on the map container.

**Your provider JavaScript must check `params.isReadonly` and skip all editing interactions:**

```javascript
function initBingMap(container) {
    const config = Utils.parseProviderConfig(container);
    const params = Utils.getMapParameters(container);

    // Initialize map (always allowed — the map is still visible)
    const map = new Microsoft.Maps.Map(container, {
        credentials: config.apiKey,
        center: new Microsoft.Maps.Location(params.latitude, params.longitude),
        zoom: params.zoom,
    });

    let pin = null;

    if (params.hasMarker) {
        pin = new Microsoft.Maps.Pushpin(map.getCenter(), {
            // Marker is only draggable when the field is interactive
            draggable: !params.isReadonly,
        });
        map.entities.push(pin);

        if (!params.isReadonly) {
            Microsoft.Maps.Events.addHandler(pin, 'dragend', function(e) {
                const loc = e.target.getLocation();
                Utils.updateCoordinateFields(
                    params.latFieldId,
                    params.lngFieldId,
                    loc.latitude,
                    loc.longitude
                );
            });
        }
    }

    // Only register editing events when the field is interactive
    if (!params.isReadonly) {
        Microsoft.Maps.Events.addHandler(map, 'dblclick', function(e) {
            const loc = e.location;
            if (!pin) {
                pin = new Microsoft.Maps.Pushpin(loc, { draggable: true });
                map.entities.push(pin);
                Microsoft.Maps.Events.addHandler(pin, 'dragend', function(ev) {
                    const l = ev.target.getLocation();
                    Utils.updateCoordinateFields(
                        params.latFieldId,
                        params.lngFieldId,
                        l.latitude,
                        l.longitude
                    );
                });
            } else {
                pin.setLocation(loc);
            }
            Utils.updateCoordinateFields(
                params.latFieldId,
                params.lngFieldId,
                loc.latitude,
                loc.longitude
            );
        });
    }
}
```

**What happens in readonly/disabled mode:**

| Aspect | Interactive | Readonly / Disabled |
|---|---|---|
| Map tiles rendered | ✅ | ✅ |
| Marker visible | ✅ | ✅ |
| Pan / zoom | ✅ | ❌ (disabled via map library options) |
| Scroll wheel zoom | ✅ | ❌ |
| Double-click to place marker | ✅ | ❌ |
| Drag marker | ✅ | ❌ |
| Hidden inputs present | ✅ | ❌ (not rendered, so nothing is submitted) |
| `saveInto()` writes data | ✅ | ❌ |

**Important:** The correct way to disable map interactions is via the **map library's own options**, not via CSS. A CSS overlay cannot reliably intercept all pointer events that the map library registers on its canvas/SVG elements.

For Leaflet, pass the interaction options when constructing the map:

```javascript
const mapOptions = params.isReadonly
    ? {
        dragging: false,
        touchZoom: false,
        doubleClickZoom: false,
        scrollWheelZoom: false,
        boxZoom: false,
        keyboard: false,
        zoomControl: false,
    }
    : {};

const map = L.map(container, mapOptions);
```

For Google Maps, use `gestureHandling: 'none'` and `disableDefaultUI: true`.
For your own provider library, consult its documentation for disabling interaction handlers.

**CSS**: The `geocoding-map-field--readonly` class is still added to the wrapper `<div>` for visual styling (e.g. reduced opacity). Your provider CSS may hook into it:

```scss
.geocoding-map-field--readonly {
    .geocoding-map-container[data-provider="bing"] {
        opacity: 0.85;
    }
}
```

---

## Step 4: Register your provider via YAML

In your module's `_config/config.yml`:

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

## Step 5: Add translations

In your module's `lang/en.yml` and `lang/de.yml`:

```yaml
en:
  MyVendor\MyModule\Models\BingService:
    SINGULARNAME: 'Bing Maps service'
    PLURALNAME: 'Bing Maps services'
    API_KEY: 'API key'
```

---

## Step 6: Expose your assets

In your module's `composer.json`:

```json
{
    "extra": {
        "expose": [
            "client/admin/dist"
        ]
    }
}
```

After installation, users must run:

```bash
composer vendor-expose
```

---

## Extensibility features

### Custom events

The module dispatches the following events on `window` or the map container:

| Event | Target | When |
|---|---|---|
| `geocoding:coordinates-updated` | `window` | After marker is placed or dragged |
| `geocoding:coordinates-cleared` | `window` | After the clear button is clicked |
| `geocoding:clear` | map container | Clear button clicked — provider must remove the marker |
| `geocoding:resize` | map container | Fullscreen toggled — provider must resize the map |

**Your provider must listen to `geocoding:clear` and `geocoding:resize` on the container:**

```javascript
// Remove marker when clear button is clicked
container.addEventListener('geocoding:clear', function() {
    if (pin) {
        map.entities.remove(pin);
        pin = null;
    }
});

// Resize map when entering/exiting fullscreen
container.addEventListener('geocoding:resize', function() {
    setTimeout(function() {
        // Trigger your library's resize/invalidate method, e.g.:
        map.setView(map.getView()); // Bing
        // map.invalidateSize();    // Leaflet
        // google.maps.event.trigger(map, 'resize'); // Google
    }, 50);
});
```

**Listening for coordinate changes from other modules:**

```javascript
window.addEventListener('geocoding:coordinates-updated', function(event) {
    console.log('New coordinates:', event.detail.latitude, event.detail.longitude);
});

window.addEventListener('geocoding:coordinates-cleared', function(event) {
    console.log('Coordinates cleared for field:', event.detail.latFieldId);
});
```

### PHP hooks

You can extend `MapField` via subclassing or decorators:

```php
class MyCustomMapField extends MapField
{
    protected function addMapAssets(): void
    {
        parent::addMapAssets();
        
        // Add your custom assets
        Requirements::javascript('mymodule/dist/custom-overlay.js');
    }
}
```

---

## Testing your provider

1. Create a new service record in **Settings → Geocoding**:
   - Choose your custom service type (e.g. "Bing Maps service")
   - Enter the required credentials
   - Mark it as **Active**

2. Set it as the **Map display service** in the Geocoding tab.

3. Edit an Address record — you should see your custom map.

---

## Example: Complete Bing Maps module structure

```
silverstripe-bing-geocoding/
├── _config/
│   └── config.yml          # Register BingMapProvider
├── client/
│   └── admin/
│       ├── src/
│       │   ├── bing-map.js
│       │   └── scss/
│       │       └── bing-map.scss
│       └── dist/           # Compiled assets
├── lang/
│   ├── en.yml
│   └── de.yml
├── src/
│   ├── Models/
│   │   └── BingService.php
│   └── Providers/
│       └── BingMapProvider.php
├── composer.json
├── package.json
├── rollup.config.mjs
└── .nvmrc
```

---

## Need help?

Open an issue on GitHub or consult the Silverstripe documentation:
- [Creating modules](https://docs.silverstripe.org/en/6/developer_guides/extending/modules/)
- [Injector and services](https://docs.silverstripe.org/en/6/developer_guides/extending/injector/)



