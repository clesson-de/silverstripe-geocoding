# Map Provider System - Implementation Summary

## Overview

The MapField system has been completely refactored to support modular, extensible map providers. External modules can now add support for additional map services (e.g. Bing Maps, Mapbox, HERE Maps) without modifying the geocoding module's core code.

---

## New architecture components

### PHP Components

| File | Purpose |
|---|---|
| `src/Interfaces/MapProviderInterface.php` | Contract for all map provider implementations |
| `src/Services/MapProviderRegistry.php` | Central registry that manages all providers |
| `src/Providers/GoogleMapProvider.php` | Google Maps implementation |
| `src/Providers/OpenStreetMapProvider.php` | OpenStreetMap/Leaflet implementation |
| `src/Forms/MapField.php` | Refactored to use registry instead of hard-coded providers |
| `src/Extensions/GeocodingConfig.php` | Added `has_one` for map display service selection |

### JavaScript Components

| File | Purpose |
|---|---|
| `client/admin/src/map-utils.js` | Shared utilities for all providers |
| `client/admin/src/google-map.js` | Google Maps initialization (uses Utils) |
| `client/admin/src/osm-map.js` | OpenStreetMap initialization (uses Utils) |

### Configuration

| File | Changes |
|---|---|
| `_config/config.yml` | Added MapProviderRegistry configuration |
| `composer.json` | Added `extra.expose` for client assets |
| `package.json` | Added npm build scripts |
| `rollup.config.mjs` | Configured to compile all JS files |
| `.nvmrc` | Node.js version 22 |

### Documentation

| File | Purpose |
|---|---|
| `docs/en/adding-custom-providers.md` | Tutorial for adding custom providers |
| `docs/en/adding-custom-providers.de.md` | German version of tutorial |
| `docs/en/architecture.md` | Technical architecture documentation |
| `docs/en/README.md` | Documentation index |

### Translations

- Added `MAP_DISPLAY_SERVICE`, `MAP_DISPLAY_SERVICE_EMPTY`, `MAP_DISPLAY_SERVICE_DESCRIPTION` to `lang/en.yml` and `lang/de.yml`

---

## How it works

### 1. Provider registration (YAML)

```yaml
Clesson\Silverstripe\Geocoding\Services\MapProviderRegistry:
  providers:
    google: 'Clesson\Silverstripe\Geocoding\Providers\GoogleMapProvider'
    osm: 'Clesson\Silverstripe\Geocoding\Providers\OpenStreetMapProvider'
    bing: 'MyVendor\MyModule\Providers\BingMapProvider'  # Custom provider
```

### 2. Provider detection (PHP)

```php
MapField::__construct()
  → detectProvider()
    → SiteConfig::Geocoding_MapDisplayService()
      → MapProviderRegistry::getProviderForService($service)
        → Checks all registered providers via supports($service)
          → Returns matching MapProviderInterface instance
```

### 3. Resource loading (PHP)

```php
MapField::addMapAssets()
  → Requirements::javascript('map-utils.js')  // Always loaded first
  → provider->getCSSResources()                // Provider-specific CSS
  → provider->getJavaScriptResources()         // Provider-specific JS
```

### 4. Template rendering (PHP → Template)

```php
MapField::getTemplateData()
  → provider->getProviderKey()      // e.g. 'google', 'osm', 'bing'
  → provider->getConfig($service)    // JSON config (API keys, etc.)
  → Rendered as data-provider and data-provider-config
```

### 5. Map initialization (JavaScript)

```javascript
// Provider-specific script (e.g. bing-map.js)
document.querySelectorAll('[data-provider="bing"]')
  → Utils.parseProviderConfig(container)  // Parse JSON config
  → Utils.getMapParameters(container)      // Extract lat, lng, zoom, etc.
  → Initialize map API (Bing, Google, Leaflet, etc.)
  → On click/drag: Utils.updateCoordinateFields()
    → Updates hidden inputs
    → Dispatches 'geocoding:coordinates-updated' event
```

---

## Key benefits

✅ **Zero modifications needed** — Add providers in separate modules  
✅ **Type-safe interface** — MapProviderInterface enforces consistent API  
✅ **Dependency injection** — Providers instantiated via Injector  
✅ **Shared utilities** — GeocodingMapUtils reduces code duplication  
✅ **Event-driven** — Custom events for cross-module integration  
✅ **Config-driven** — All providers registered via YAML  
✅ **Testable** — Mock providers for unit tests  
✅ **Consistent UX** — All providers behave the same way  

---

## Extension points for external modules

### 1. Add a new provider

```php
// 1. Create service model
class BingService extends GeocodingService { ... }

// 2. Implement provider interface
class BingMapProvider implements MapProviderInterface { ... }

// 3. Register via YAML
MapProviderRegistry:
  providers:
    bing: 'MyVendor\MyModule\Providers\BingMapProvider'

// 4. Add JavaScript
// bing-map.js using GeocodingMapUtils
```

### 2. Listen for coordinate updates

```javascript
window.addEventListener('geocoding:coordinates-updated', function(event) {
    const { latitude, longitude } = event.detail;
    // Trigger reverse geocoding, analytics, etc.
});
```

### 3. Extend MapField

```php
class EnhancedMapField extends MapField
{
    protected function addMapAssets(): void
    {
        parent::addMapAssets();
        Requirements::javascript('mymodule/dist/map-overlay.js');
    }
}
```

---

## Migration notes

### Breaking changes

- ❌ Direct instantiation with hard-coded providers removed
- ❌ `$provider` property type changed from `string` to `MapProviderInterface|null`
- ❌ `$apiKey` property removed (now in provider config)

### Upgrade path

No changes needed for existing users — the module works exactly the same way from the CMS perspective. The refactoring is purely architectural and maintains backward compatibility in terms of functionality.

---

## Testing checklist

- [ ] Select a map display service in SiteConfig → Geocoding
- [ ] Edit an Address record
- [ ] Verify map renders correctly
- [ ] Click on map to set coordinates
- [ ] Drag marker to update coordinates
- [ ] Save and verify coordinates persist
- [ ] Test with Google Maps service
- [ ] Test with OpenStreetMap service
- [ ] Test without a selected service (should show default/error)
- [ ] Verify `geocoding:coordinates-updated` event fires
- [ ] Run `composer vendor-expose` and verify assets are exposed

---

## Files to commit

```
silverstripe-geocoding/
├── _config/
│   └── config.yml                              # ✏️ Modified
├── client/admin/
│   ├── src/
│   │   ├── google-map.js                       # ✏️ Modified
│   │   ├── osm-map.js                          # ✏️ Modified
│   │   ├── map-utils.js                        # ✨ New
│   │   └── scss/
│   │       └── map-field.scss                  # ✏️ Modified
│   └── dist/
│       ├── google-map.js                       # 🔄 Rebuilt
│       ├── osm-map.js                          # 🔄 Rebuilt
│       ├── map-utils.js                        # ✨ New
│       └── map-field.css                       # 🔄 Rebuilt
├── docs/en/
│   ├── README.md                               # ✨ New
│   ├── architecture.md                         # ✨ New
│   ├── adding-custom-providers.md              # ✨ New
│   └── adding-custom-providers.de.md           # ✨ New
├── lang/
│   ├── en.yml                                  # ✏️ Modified
│   └── de.yml                                  # ✏️ Modified
├── src/
│   ├── Extensions/
│   │   ├── AddressExtension.php                # ✏️ Modified
│   │   └── GeocodingConfig.php                 # ✏️ Modified
│   ├── Forms/
│   │   └── MapField.php                        # ✏️ Modified
│   ├── Interfaces/
│   │   └── MapProviderInterface.php            # ✨ New
│   ├── Providers/
│   │   ├── GoogleMapProvider.php               # ✨ New
│   │   └── OpenStreetMapProvider.php           # ✨ New
│   └── Services/
│       └── MapProviderRegistry.php             # ✨ New
├── templates/Clesson/Silverstripe/Geocoding/Forms/
│   └── MapField.ss                             # ✨ New
├── .nvmrc                                      # ✨ New
├── composer.json                               # ✏️ Modified
├── package.json                                # ✨ New
├── rollup.config.mjs                           # ✨ New
└── README.md                                   # ✏️ Modified
```

**Legend:**
- ✨ New file
- ✏️ Modified file
- 🔄 Rebuilt asset

---

## Next steps

1. Run `dev/build?flush=all`
2. Run `composer vendor-expose`
3. Configure a map display service in Settings
4. Test the interactive map in an Address record
5. (Optional) Create a custom provider module following the guide

---

**Related documentation:**
- [Architecture overview](docs/en/architecture.md)
- [Adding custom providers](docs/en/adding-custom-providers.md)

