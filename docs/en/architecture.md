# Map Provider Architecture

## Overview

The geocoding module's map provider system is designed for modularity and extensibility. Third-party modules can register their own map providers (e.g. Bing Maps, Mapbox) without modifying core code.

---

## Architecture diagram

```
┌─────────────────────────────────────────────────────────────┐
│                         MapField                             │
│  (FormField that renders interactive maps)                   │
└───────────────────────────┬─────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                  MapProviderRegistry                         │
│  (Central registry for all map providers)                    │
│  - Reads YAML config                                         │
│  - Instantiates providers via Injector                       │
│  - Matches GeocodingService → Provider                       │
└───────────────────────────┬─────────────────────────────────┘
                            │
          ┌─────────────────┼─────────────────┐
          ▼                 ▼                 ▼
    ┌──────────┐      ┌──────────┐      ┌──────────┐
    │ Google   │      │   OSM    │      │  Bing    │
    │ Provider │      │ Provider │      │ Provider │
    │          │      │          │      │ (custom) │
    └────┬─────┘      └────┬─────┘      └────┬─────┘
         │                 │                 │
         │  implements     │  implements     │  implements
         ▼                 ▼                 ▼
    ┌────────────────────────────────────────────────┐
    │         MapProviderInterface                    │
    │  - getProviderKey()                             │
    │  - getProviderName()                            │
    │  - supports(GeocodingService)                   │
    │  - getConfig(GeocodingService)                  │
    │  - getCSSResources()                            │
    │  - getJavaScriptResources(GeocodingService)     │
    └────────────────────────────────────────────────┘
```

---

## Components

### 1. MapProviderInterface

Defines the contract for all map providers:

| Method | Purpose |
|---|---|
| `getProviderKey()` | Returns unique identifier (e.g. 'google', 'osm', 'bing') |
| `getProviderName()` | Returns human-readable name |
| `supports(GeocodingService)` | Checks if provider supports a given service model |
| `getConfig(GeocodingService)` | Returns JSON config for JavaScript (e.g. API key) |
| `getCSSResources()` | Returns array of CSS URLs/paths |
| `getJavaScriptResources()` | Returns array of JS URLs/paths |

---

### 2. MapProviderRegistry

Central registry that:
- Reads provider classes from YAML config
- Instantiates them via Injector (allows for dependency injection)
- Matches GeocodingService instances to the correct provider
- Provides access to all registered providers

**Configuration example:**

```yaml
Clesson\Silverstripe\Geocoding\Services\MapProviderRegistry:
  providers:
    google: 'Clesson\Silverstripe\Geocoding\Providers\GoogleMapProvider'
    osm: 'Clesson\Silverstripe\Geocoding\Providers\OpenStreetMapProvider'
    bing: 'MyVendor\MyModule\Providers\BingMapProvider'
```

---

### 3. MapField

The FormField that renders the map:

1. Reads the selected service from `SiteConfig->Geocoding_MapDisplayService()`
2. Asks `MapProviderRegistry` for the matching provider
3. Calls provider methods to:
   - Load CSS/JS resources via `Requirements`
   - Get configuration data as JSON
4. Renders template with data attributes
5. JavaScript reads data attributes and initializes the map

**Template data flow:**

```
MapField (PHP)
  → getTemplateData()
    → MapProvider->getConfig()
      → JSON encoded as data-provider-config
        → JavaScript reads and parses JSON
          → Initializes map with provider-specific API
```

---

### JavaScript modules

Each provider has its own JavaScript file:

- `map-utils.js` — Shared utilities and provider registry
- `map-entwine.js` — jQuery Entwine integration for AJAX-loaded content
- `google-map.js` — Google Maps implementation
- `osm-map.js` — OpenStreetMap/Leaflet implementation
- `bing-map.js` — (Example) Bing Maps implementation

**Key responsibilities:**

- **Provider scripts** register themselves with `GeocodingMapUtils.registerProvider()`
- **Entwine hook** watches for `.geocoding-map-container` elements and initializes them via `GeocodingMapUtils.initializeMap()`
- **Single container init** — each provider implements a function that initializes one container
- **Batch init** — providers also support initializing all containers on page load
- **Prevents double-init** — checks `data-initialized` attribute
- **Error handling** — catches and displays user-friendly errors
- **Event dispatch** — fires `geocoding:coordinates-updated` event
- **Double-click interaction** — markers are placed/moved on double-click (single-click pans the map)

**Why Entwine?**

Silverstripe CMS loads content dynamically via AJAX (e.g. GridField detail forms, tabs). Without Entwine:
- ❌ Maps only work on initial page load
- ❌ Maps show as grey boxes when loaded via AJAX
- ❌ Reload required to see map tiles

With Entwine:
- ✅ Maps initialize automatically when loaded via AJAX
- ✅ Works in GridField detail forms
- ✅ Works when switching tabs
- ✅ No reload needed

**Standard pattern:**

```javascript
(function() {
    'use strict';

    const Utils = window.GeocodingMapUtils;

    // Single container initialization function
    function initMyProviderMap(container) {
        const config = Utils.parseProviderConfig(container);
        const params = Utils.getMapParameters(container);
        
        // Initialize map...
    }

    // Batch initialization function
    function initMyProviderMaps() {
        Utils.initializeAllMaps('myprovider');
    }

    // Register with the utilities
    if (Utils && Utils.registerProvider) {
        Utils.registerProvider('myprovider', initMyProviderMap);
    }

    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMyProviderMaps);
    } else {
        initMyProviderMaps();
    }
})();
```

**Entwine integration (automatic):**

The `map-entwine.js` file handles AJAX-loaded content automatically — your provider script only needs to register itself with `GeocodingMapUtils.registerProvider()`, and Entwine will call your init function when containers appear in the DOM.

---

## Extension points

### For other modules

**1. Register a new provider:**

```yaml
# mymodule/_config/config.yml
Clesson\Silverstripe\Geocoding\Services\MapProviderRegistry:
  providers:
    bing: 'MyVendor\MyModule\Providers\BingMapProvider'
```

**2. Create a GeocodingService subclass:**

```php
class BingService extends GeocodingService { /* ... */ }
```

**3. Implement MapProviderInterface:**

```php
class BingMapProvider implements MapProviderInterface { /* ... */ }
```

**4. Add JavaScript initialization:**

```javascript
// bing-map.js
const containers = document.querySelectorAll('[data-provider="bing"]');
// Initialize Bing Maps...
```

---

### For developers using the module

**Listen for coordinate updates:**

```javascript
window.addEventListener('geocoding:coordinates-updated', function(event) {
    const { latitude, longitude } = event.detail;
    // Trigger reverse geocoding, update other fields, etc.
});
```

**Extend MapField via subclassing:**

```php
class MyCustomMapField extends MapField
{
    protected function addMapAssets(): void
    {
        parent::addMapAssets();
        Requirements::javascript('mymodule/dist/overlay.js');
    }
}
```

---

## Benefits

✅ **Zero core modifications** — add providers in separate modules  
✅ **Dependency injection** — providers can have injected dependencies  
✅ **Type-safe** — Interface enforces consistent API  
✅ **Configurable** — All providers registered via YAML  
✅ **Testable** — Mock providers for unit tests  
✅ **Extensible** — Custom events for JavaScript integration  

---

## Related files

- `/src/Interfaces/MapProviderInterface.php` — Provider contract
- `/src/Services/MapProviderRegistry.php` — Central registry
- `/src/Providers/GoogleMapProvider.php` — Google Maps implementation
- `/src/Providers/OpenStreetMapProvider.php` — OSM implementation
- `/src/Forms/MapField.php` — FormField using the registry
- `/docs/en/adding-custom-providers.md` — Developer guide

---

**See also:** [Adding custom map providers guide](adding-custom-providers.md)

