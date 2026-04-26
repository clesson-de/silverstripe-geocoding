# Map Provider Best Practices

Guidelines for developing custom map providers that integrate seamlessly with the geocoding module.

---

## PHP Best Practices

### 1. Service model naming

```php
// ✅ Good
class BingService extends GeocodingService { }
class MapboxService extends GeocodingService { }

// ❌ Bad
class BingMapsGeocodingService { }  // Too verbose
class Bing { }                      // Too generic
```

### 2. Provider class naming

```php
// ✅ Good
class BingMapProvider implements MapProviderInterface { }
class MapboxMapProvider implements MapProviderInterface { }

// ❌ Bad
class BingProvider { }              // Not specific enough
class BingMapsGeocoding { }         // Wrong pattern
```

### 3. Provider keys

Use lowercase, URL-safe identifiers:

```php
// ✅ Good
public function getProviderKey(): string {
    return 'bing';
}

// ✅ Also good
public function getProviderKey(): string {
    return 'mapbox-gl';  // Hyphen OK for multi-word
}

// ❌ Bad
return 'BingMaps';    // Not lowercase
return 'bing_maps';   // Use hyphen, not underscore
```

### 4. Configuration arrays

Return only what JavaScript needs:

```php
// ✅ Good
public function getConfig(GeocodingService $service): array {
    return [
        'apiKey' => $service->ApiKey,
        'theme' => 'dark',
    ];
}

// ❌ Bad
return [
    'serviceId' => $service->ID,        // Not needed in JS
    'created' => $service->Created,     // Not needed in JS
    'dbConnection' => DB::get_conn(),   // Security risk!
];
```

### 5. Resource paths

Use module-relative paths or full CDN URLs:

```php
// ✅ Good
return [
    'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css',
    'myvendor-mymodule/client/admin/dist/mapbox.js',
];

// ❌ Bad
return [
    '/client/admin/dist/mapbox.js',    // Missing module name
    '../dist/mapbox.js',                // Relative path won't work
];
```

---

## JavaScript Best Practices

### 1. Always use GeocodingMapUtils

```javascript
// ✅ Good
const Utils = window.GeocodingMapUtils;
const config = Utils.parseProviderConfig(container);
const params = Utils.getMapParameters(container);
Utils.updateCoordinateFields(latId, lngId, lat, lng);

// ❌ Bad
const config = JSON.parse(container.dataset.providerConfig);  // No error handling
const lat = parseFloat(container.dataset.latitude);           // Duplicate logic
document.getElementById(latId).value = lat;                   // No event dispatch
```

### 2. Respect zoom levels

Use the appropriate zoom level based on whether a marker is present:

```javascript
// ✅ Good
const params = Utils.getMapParameters(container);
const map = L.map(container).setView([params.latitude, params.longitude], params.zoom);
// params.zoom is automatically params.markerZoom if hasMarker === true

// ❌ Bad
const zoom = parseInt(container.dataset.zoom, 10) || 6;  // Ignores zoomWithMarker
```

**Recommended zoom levels:**
- **1-5** — Continent/country view
- **6-10** — Region/state view
- **11-14** — City view
- **15-17** — Street-level view (good for addresses)
- **18-20** — Building-level view

### 3. Selector specificity

Target only your provider's containers:

```javascript
// ✅ Good
const containers = document.querySelectorAll('.geocoding-map-container[data-provider="bing"]');

// ❌ Bad
const containers = document.querySelectorAll('.geocoding-map-container');  // Matches all providers!
```

### 3. Error handling

Always wrap initialization in try/catch:

```javascript
// ✅ Good
containers.forEach(container => {
    try {
        // Init map...
    } catch (error) {
        console.error('Error initializing Bing Maps:', error);
        Utils.showError(container, 'Failed to initialize Bing Maps.');
    }
});

// ❌ Bad
containers.forEach(container => {
    // Init map... (no error handling)
});
```

### 4. Library detection

Check if external libraries are loaded:

```javascript
// ✅ Good
if (typeof Microsoft === 'undefined' || !Microsoft.Maps) {
    Utils.showError(container, 'Bing Maps library not loaded.');
    return;
}

// ❌ Bad
const map = new Microsoft.Maps.Map(container, options);  // May throw ReferenceError
```

### 5. Async loading

Handle cases where your library loads asynchronously:

```javascript
// ✅ Good
if (typeof Microsoft !== 'undefined' && Microsoft.Maps) {
    initBingMaps();
} else {
    window.bingMapsCallback = initBingMaps;  // Called by SDK
}

// ❌ Bad
initBingMaps();  // Assumes library is already loaded
```

---

## CSS Best Practices

### 1. Namespace your styles

```scss
// ✅ Good
.geocoding-map-container[data-provider="bing"] {
    .bing-copyright {
        font-size: 10px;
    }
}

// ❌ Bad
.copyright {  // Too generic, may conflict
    font-size: 10px;
}
```

### 2. Don't override base styles

```scss
// ✅ Good
.geocoding-map-container[data-provider="mapbox"] {
    // Add provider-specific styles
    .mapboxgl-ctrl {
        border-radius: 4px;
    }
}

// ❌ Bad
.geocoding-map-container {
    height: 600px !important;  // Overrides base height for all providers
}
```

---

## Configuration Best Practices

### 1. Use the After directive

```yaml
# ✅ Good
---
Name: mymodule-map-provider
After:
  - '#geocoding-config'
---
Clesson\Silverstripe\Geocoding\Services\MapProviderRegistry:
  providers:
    bing: 'MyVendor\MyModule\Providers\BingMapProvider'

# ❌ Bad
---
Name: mymodule-map-provider
---
# Missing After directive — load order not guaranteed
```

### 2. Expose your assets

```json
// ✅ Good (in composer.json)
{
    "extra": {
        "expose": [
            "client/admin/dist"
        ]
    }
}

// ❌ Bad
// No expose configuration — users must manually copy files
```

---

## Documentation Best Practices

### 1. Provide usage examples

```markdown
✅ Include code examples in your README
✅ Document all configuration options
✅ Show both CMS usage and code usage
✅ Link to official API documentation
```

### 2. Document prerequisites

```markdown
## Prerequisites

- PHP 8.1+
- Silverstripe Framework 6+
- clesson-de/silverstripe-geocoding ^1.0
- Bing Maps account and API key
```

### 3. Include screenshots

Show users what to expect in the CMS:
- Service configuration form
- Map display in Address edit form
- Example of marker placement

---

## Testing Best Practices

### 1. Test matrix

Test your provider with:
- ✅ New record (no coordinates set)
- ✅ Existing record (coordinates already set)
- ✅ Invalid API key
- ✅ Network error
- ✅ Service disabled
- ✅ Single-click (should pan map, not place marker)
- ✅ Double-click (should place/move marker)
- ✅ Drag marker

### 2. Browser testing

Test in:
- ✅ Chrome
- ✅ Firefox
- ✅ Safari
- ✅ Edge

### 3. Unit tests

```php
public function testProviderSupportsCorrectServiceType()
{
    $provider = new BingMapProvider();
    $bingService = BingService::create();
    $googleService = GoogleService::create();

    $this->assertTrue($provider->supports($bingService));
    $this->assertFalse($provider->supports($googleService));
}
```

---

## Common pitfalls

### ❌ Don't hard-code coordinates

```javascript
// ❌ Bad
const map = L.map(container).setView([52.5163, 13.3777], 10);

// ✅ Good
const params = Utils.getMapParameters(container);
const map = L.map(container).setView([params.latitude, params.longitude], params.zoom);
```

### ❌ Don't forget error states

```javascript
// ❌ Bad
const map = new google.maps.Map(container, options);
// What if google is undefined?

// ✅ Good
if (typeof google === 'undefined' || !google.maps) {
    Utils.showError(container, 'Google Maps not loaded.');
    return;
}
```

### ❌ Don't dispatch events manually

```javascript
// ❌ Bad
latField.value = lat.toFixed(7);
window.dispatchEvent(new CustomEvent('geocoding:coordinates-updated', { ... }));

// ✅ Good
Utils.updateCoordinateFields(latId, lngId, lat, lng);
// ↑ Handles both input update AND event dispatch
```

---

## Performance considerations

### 1. Lazy loading

Load map libraries only when needed:

```php
// ✅ Good — only loaded if service is selected
public function getJavaScriptResources(GeocodingService $service): array {
    return ['https://cdn.example.com/heavy-library.js'];
}
```

### 2. Resource bundling

Combine your JS files:

```javascript
// ✅ Good — use rollup to bundle
export default [{
    input: 'src/bing-map.js',
    output: { file: 'dist/bing-map.js', format: 'iife' }
}];
```

### 3. CDN vs self-hosted

Consider trade-offs:

| Approach | Pros | Cons |
|---|---|---|
| **CDN** | Fast, cached, no hosting | Privacy concerns, external dependency |
| **Self-hosted** | Full control, privacy | Larger bundle, slower initial load |

---

## Questions?

See the [adding custom providers guide](adding-custom-providers.md) for a complete tutorial.

