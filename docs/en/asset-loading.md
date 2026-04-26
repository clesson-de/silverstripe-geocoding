# Asset Loading Order

Understanding how JavaScript and CSS assets are loaded for the MapField.

---

## Load sequence

### 1. Base assets (always loaded first)

```
map-utils.js         ← Shared utilities, provider registry
map-entwine.js       ← jQuery Entwine integration for AJAX
map-field.css        ← Base styling
```

### 2. Provider-specific libraries (conditionally loaded)

**For Google Maps:**
```
https://maps.googleapis.com/maps/api/js?key=...  ← Google Maps API
google-map.js                                     ← Our Google integration
```

**For OpenStreetMap:**
```
https://unpkg.com/leaflet@1.9.4/dist/leaflet.css ← Leaflet CSS
https://unpkg.com/leaflet@1.9.4/dist/leaflet.js  ← Leaflet library
osm-map.js                                        ← Our OSM integration
```

**For custom providers (example: Bing):**
```
bing-map.css                                      ← Provider CSS
https://www.bing.com/api/maps/mapcontrol?key=...  ← Bing Maps API
bing-map.js                                       ← Provider integration
```

---

## Initialization flow

### Initial page load

```
1. Browser loads HTML
2. Requirements::javascript() includes are added to <head>
3. map-utils.js loads → window.GeocodingMapUtils created
4. map-entwine.js loads → Entwine watches for .geocoding-map-container
5. Provider JS loads (e.g. osm-map.js)
   → Calls Utils.registerProvider('osm', initOSMMap)
   → Calls Utils.initializeAllMaps('osm')
6. Entwine onmatch fires for each container
   → Calls Utils.initializeMap(container, 'osm')
   → Checks data-initialized attribute (prevents double-init)
   → Calls registered initOSMMap(container)
7. Map renders
```

### AJAX-loaded content (GridField, tabs)

```
1. User clicks "Edit" in GridField or switches tab
2. Silverstripe loads form via AJAX
3. New .geocoding-map-container appears in DOM
4. Entwine onmatch fires immediately
   → setTimeout(100ms) to ensure assets are ready
   → Calls Utils.initializeMap(container, 'osm')
   → Checks if provider is registered
   → Calls initOSMMap(container)
5. Map renders without page reload
```

---

## Why this order matters

### ❌ Without proper ordering

```
osm-map.js loads
  → Tries to call Utils.registerProvider()
  → Utils is undefined (map-utils.js not loaded yet)
  → Registration fails
  → Entwine can't find initializer
  → Grey box
```

### ✅ With proper ordering

```
map-utils.js loads FIRST
  → window.GeocodingMapUtils created
map-entwine.js loads SECOND
  → Entwine hook registered
osm-map.js loads THIRD
  → Utils.registerProvider() succeeds
  → Provider is ready for Entwine to call
```

---

## Dependencies

### Required for all providers

- `jQuery` (provided by Silverstripe CMS)
- `jQuery.entwine` (provided by Silverstripe CMS)
- `map-utils.js` (this module)
- `map-entwine.js` (this module)

### Provider-specific

- **Google:** Google Maps JavaScript API
- **OSM:** Leaflet.js library
- **Bing:** Bing Maps Control SDK
- **Mapbox:** Mapbox GL JS library
- **etc.**

---

## Debugging asset loading

### Check if assets are loaded

Open browser console and run:

```javascript
// Check if utilities are available
console.log(typeof window.GeocodingMapUtils);  // Should be 'object'

// Check if providers are registered
console.log(window.GeocodingMapUtils);
// Should show registerProvider, initializeMap, etc.

// Check if specific provider is registered
// (Inspect providerInitializers in the console)

// Check if Entwine is active
console.log(typeof jQuery.entwine);  // Should be 'function'

// Check if Leaflet is loaded (for OSM)
console.log(typeof L);  // Should be 'object' or 'function'

// Check if Google Maps is loaded
console.log(typeof google);  // Should be 'object'
console.log(typeof google.maps);  // Should be 'object'
```

### Check initialization status

```javascript
// Find all map containers
const containers = document.querySelectorAll('.geocoding-map-container');

// Check which are initialized
containers.forEach(c => {
    console.log('Container:', c.id, 
                'Provider:', c.dataset.provider, 
                'Initialized:', c.dataset.initialized);
});
```

### Force re-initialization

If a map fails to initialize:

```javascript
const container = document.querySelector('.geocoding-map-container');
container.dataset.initialized = 'false';  // Reset flag
window.GeocodingMapUtils.initializeMap(container, container.dataset.provider);
```

---

## Performance considerations

### Asset size

| File | Size (approx.) | Notes |
|---|---|---|
| map-utils.js | ~3 KB | Minimal overhead |
| map-entwine.js | ~1 KB | Minimal overhead |
| google-map.js | ~2 KB | Lightweight wrapper |
| osm-map.js | ~2 KB | Lightweight wrapper |
| map-field.css | ~1 KB | Base styling |
| **Total (core)** | **~9 KB** | Before provider libraries |
| Leaflet JS | ~140 KB | External CDN |
| Leaflet CSS | ~15 KB | External CDN |
| Google Maps API | ~500 KB | External CDN, cached |

### Optimization tips

1. **Use CDN** — External libraries are cached by browser
2. **Lazy load** — Provider assets only load when needed
3. **Cache vendor-expose** — Run once, cached until next deploy
4. **Minimize custom providers** — Keep your provider JS < 5 KB

---

## See also

- [Architecture overview](architecture.md) — Technical details
- [Adding custom providers](adding-custom-providers.md) — Tutorial

