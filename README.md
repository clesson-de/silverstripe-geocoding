# silverstripe-geocoding

Geocoding, reverse geocoding and routing for Silverstripe CMS 6.1, backed by **OpenStreetMap (Nominatim / OSRM)** and **Google Maps**.

## Features

- **Geocoding** — resolve a postal address to geographic coordinates (latitude / longitude)
- **Reverse geocoding** — resolve coordinates back to a postal address
- **Routing** — calculate a driving route from an origin through optional waypoints to a destination
- **Two built-in service backends** — OpenStreetMap (free, no API key required) and Google Maps
- **`Address` model** — standalone address records with international formatting via `commerceguys/addressing`
- **`AddressType` model** — configurable address type taxonomy (e.g. invoice address, delivery address)
- **Region dropdown API** — AJAX endpoint for dynamic region/state dropdowns based on the selected country
- **Automatic geocoding** — `Address` records are geocoded automatically after each write (via `AddressExtension`)
- **SiteConfig tab** — manage geocoding service records directly from the CMS Settings panel
- **Custom `DBGeoCoordinate` composite field** — store latitude + longitude in a single `$db` declaration
- **Rate limiting** — configurable per-service delay between API requests (required by Nominatim's usage policy)

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.1` |
| silverstripe/framework | `^6` |
| commerceguys/addressing | `^2.1` |
| guzzlehttp/guzzle | `^7` |

---

## Installation

```bash
composer require clesson-de/silverstripe-geocoding
```

After installation, run a database build:

```
/dev/build?flush=all
```

---

## Address and AddressType models

This module provides two standalone models for address management that can be used independently of — or in combination with — the `clesson-de/silverstripe-contacts` module.

### `Address`

Represents a physical address. Fields: `Name`, `AddressLine1`, `AddressLine2`, `PostalCode`, `City`, `Region`, `CountryCode`.

A formatted `Summary` string (localised using `commerceguys/addressing`) is generated automatically `onBeforeWrite`. An interactive map field showing the geocoded coordinates is added via `AddressExtension`.

### `AddressType`

A configurable taxonomy for address types (e.g. invoice address, delivery address). Default types can be seeded in project config:

```yml
Clesson\Silverstripe\Geocoding\Models\AddressType:
  default_tags:
    invoice-address: 'Invoice address'
    delivery-address: 'Delivery address'
```

### Region dropdown API

When displaying an `Address` edit form, the region field uses a dynamic dropdown. The module exposes a JSON endpoint that returns subdivision options for a given country:

```
GET /geocoding-api/address/regions?country=DE&locale=de
```

Requires `CMS_ACCESS` permission.

---

## Configuration

### Managing geocoding services in the CMS

1. Log in to the CMS.
2. Navigate to **Settings** (SiteConfig).
3. Open the **Geocoding** tab.
4. **Select a map display service** — choose which service provider (Google or OpenStreetMap) should render interactive maps in the CMS.
5. Add geocoding services for each method (geocode, reverse geocode, route):
   - Use the **Add** button to link existing services or **Add new** to create a service inline.
   - Set the **Quota** (maximum calls per day; 0 = unlimited).
   - **Drag** services to change their priority order — the first service with available quota is used.
6. Save the Settings page.

#### Creating a new geocoding service

1. In the **Geocoding** tab, click **Add new** under any method section.
2. Choose the service type: **OpenStreetMap service** or **Google service**.
3. Fill in the form:
   - **Name** — a descriptive label for this service record.
   - **Active** — enables or disables the service.
   - **API key** — required for Google Maps only.
   - **Base URL** — overrides the default Nominatim URL (OpenStreetMap only; leave empty to use the public API).
   - **OSRM base URL** — overrides the default OSRM routing URL (OpenStreetMap only).
   - **Rate limit (ms)** — minimum wait time in milliseconds between two requests. Set to at least **1100** for Nominatim to comply with the [Nominatim usage policy](https://operations.osmfoundation.org/policies/nominatim/).
4. Save the service.

---

## User guide

### Interactive map field

When editing an Address record in the CMS, you'll see an interactive map that displays the current coordinates (if set) and allows you to update them by clicking on the map:

- **Marker** — appears if coordinates are already set. Drag the marker to update the coordinates.
- **Double-click** — double-click anywhere on the map to place or move the marker.
- **Single-click** — single-clicks allow you to pan the map without accidentally moving the marker.
- **Service** — the map provider (Google Maps or OpenStreetMap) is determined by the **Map display service** setting in SiteConfig → Geocoding.

---

## Frontend assets

This module includes JavaScript and CSS assets for the interactive map field. After installation, run:

```bash
composer vendor-expose
```

This exposes the compiled assets from `client/admin/dist/` to `public/_resources/silverstripe-geocoding/`.

### Developing frontend assets

If you need to modify the JavaScript or SCSS:

1. Navigate to the module directory:
   ```bash
   cd silverstripe-geocoding
   ```

2. Install Node.js dependencies (using the version specified in `.nvmrc`):
   ```bash
   nvm use
   npm install
   ```

3. Build the assets:
   ```bash
   npm run build
   ```

4. Or watch for changes during development:
   ```bash
   npm run watch
   ```

The `.nvmrc` file specifies the required Node.js version (22). Use [nvm](https://github.com/nvm-sh/nvm) to switch to the correct version automatically.

---

## Troubleshooting

### Map shows as grey box / tiles don't load initially

**Symptom:** When you open a form with a MapField (especially in GridField detail forms or tabs), you see a grey box. After reloading the page, the map tiles load correctly.

**Cause:** The map container is loaded via AJAX, but the JavaScript initialization ran before the container appeared in the DOM.

**Solution:** This is automatically handled by the Entwine integration (`map-entwine.js`). Make sure you have:

1. ✅ Run `composer vendor-expose` after installation
2. ✅ Cleared the Silverstripe cache (`?flush=all`)
3. ✅ Hard-refreshed your browser (Ctrl+Shift+R / Cmd+Shift+R)
4. ✅ Checked the browser console for JavaScript errors

**How it works:** The module uses jQuery Entwine to watch for `.geocoding-map-container` elements. When a container appears in the DOM (including via AJAX), Entwine automatically initializes the map after a short delay (100ms) to ensure all assets are loaded.

**Debug:** Open the browser console and check:
```javascript
// Should return 'object'
typeof window.GeocodingMapUtils

// Should show registered providers
console.log(window.GeocodingMapUtils)

// Check if Leaflet loaded (for OSM)
typeof L

// Check if Google Maps loaded
typeof google !== 'undefined' && google.maps
```

See [Asset loading documentation](docs/en/asset-loading.md) for detailed debugging instructions.

---

### Map doesn't appear at all

**✓ Checklist:**
- [ ] Map display service selected in Settings → Geocoding?
- [ ] Service is marked as Active?
- [ ] API key configured (for Google Maps)?
- [ ] `composer vendor-expose` executed?
- [ ] Browser console shows no JavaScript errors?

### Coordinates don't save

**✓ Checklist:**
- [ ] Field name matches DB field name (e.g. 'GeoCoordinates')?
- [ ] Hidden input fields are present in the DOM?
- [ ] Form submitted successfully (check for validation errors)?

### Coordinates are rounded / have fewer decimal places

**Symptom:** When you copy coordinates from Google Maps (e.g. `48.65129546574825, 9.28476328298257`), they are saved with only 7 decimal places (e.g. `48.6512955, 9.2847633`).

**This is normal and intentional.** The database uses `DECIMAL(10,7)` which provides 7 decimal places = ~1.1 cm accuracy. This is the **industry standard** for GPS coordinates and more than sufficient for address geocoding.

Google's 14+ decimal places (sub-micrometer precision) are only relevant for scientific/surveying applications. The difference is invisible on maps and has zero practical impact.

See the [Coordinate precision guide](docs/en/coordinate-precision.md) for detailed explanation.

---

## Developer documentation

### Extensibility

This module is designed to be **modular and extensible**. You can add support for additional map providers (e.g. Bing Maps, Mapbox, HERE Maps) in your own module without modifying the core geocoding module.

**How it works:**

```
External Module                    Geocoding Module
┌─────────────────┐               ┌──────────────────────┐
│ BingService     │               │ GoogleService        │
│ (DataObject)    │               │ OpenStreetMapService │
└────────┬────────┘               └──────────┬───────────┘
         │                                   │
         │ extends                           │ extends
         ▼                                   ▼
┌─────────────────────────────────────────────────────────┐
│              GeocodingService                            │
│              (Abstract base model)                       │
└─────────────────────────────────────────────────────────┘

External Module                    Geocoding Module
┌─────────────────┐               ┌──────────────────────┐
│ BingMapProvider │               │ GoogleMapProvider    │
│                 │               │ OSMMapProvider       │
└────────┬────────┘               └──────────┬───────────┘
         │                                   │
         │ implements                        │ implements
         ▼                                   ▼
┌─────────────────────────────────────────────────────────┐
│           MapProviderInterface                           │
│  - getProviderKey()                                      │
│  - supports(GeocodingService)                            │
│  - getConfig() / getCSSResources() / getJSResources()    │
└───────────────────┬─────────────────────────────────────┘
                    │
                    │ registered via YAML
                    ▼
┌─────────────────────────────────────────────────────────┐
│           MapProviderRegistry                            │
│  (Central registry for all providers)                    │
└───────────────────┬─────────────────────────────────────┘
                    │
                    │ used by
                    ▼
┌─────────────────────────────────────────────────────────┐
│               MapField                                   │
│  (FormField that renders the interactive map)            │
└─────────────────────────────────────────────────────────┘
```

**Documentation:**

- [Adding custom map providers guide](docs/en/adding-custom-providers.md) — Step-by-step tutorial
- [Provider best practices](docs/en/provider-best-practices.md) — Guidelines for high-quality implementations
- [Architecture overview](docs/en/architecture.md) — Technical documentation of the provider system

---

### Using `DBGeoCoordinate` in your own DataObjects

Declare the field in `$db` using the `GeoCoordinate` type alias:

```php
private static array $db = [
    'Location' => 'GeoCoordinate',
];
```

This creates two database columns: `LocationLatitude DECIMAL(10,7)` and `LocationLongitude DECIMAL(10,7)`.

**Note on precision:** The 7 decimal places provide ~1.1 cm accuracy, which is the industry standard for GPS coordinates and more than sufficient for address geocoding. Services like Google Maps may return coordinates with 14+ decimal places, but the additional precision (sub-micrometer) is only relevant for scientific/surveying applications and will be automatically rounded when saved.

Add the MapField to your getCMSFields():

```php
use Clesson\Silverstripe\Geocoding\Forms\MapField;

public function getCMSFields(): FieldList
{
    $fields = parent::getCMSFields();
    
    $fields->removeByName(['LocationLatitude', 'LocationLongitude']);
    
    /** @var MapField $locationField */
    $locationField = MapField::create('Location', $this->fieldLabel('Location'));
    $locationField->setValue($this->dbObject('Location'));
    
    // Optional: configure zoom levels
    $locationField->setZoomLevel(8);             // Default zoom when no marker (1-20)
    $locationField->setZoomLevelWithMarker(16);  // Zoom when marker exists (street-level)
    
    $fields->addFieldToTab('Root.Main', $locationField);
    
    return $fields;
}
```

Access the coordinate via the composite field object:

```php
$location = $myRecord->dbObject('Location');

if ($location->exists()) {
    echo $location->getLatitude();   // float, e.g. 52.5163
    echo $location->getLongitude();  // float, e.g. 13.3777
    echo $location->Nice();          // "52.5163, 13.3777"
}

// Set values
$location->setLatitude(52.5163);
$location->setLongitude(13.3777);
$myRecord->write();
```

---

### Using the service classes in code

Retrieve a configured service record and instantiate the matching service class:

```php
use Clesson\Geocoding\Constants\GeocodingServiceType;
use Clesson\Geocoding\Models\GeocodingService;
use Clesson\Geocoding\Services\OpenStreetMapGeocodingService;
use Clesson\Geocoding\Services\GoogleGeocodingService;

// Load the first active OpenStreetMap service
$serviceRecord = GeocodingService::get()
    ->filter(['ServiceType' => GeocodingServiceType::OPEN_STREET_MAP, 'Active' => true])
    ->first();

$service = new OpenStreetMapGeocodingService($serviceRecord);
```

#### Geocoding (address → coordinates)

```php
$coordinate = $service->geocode([
    'street'     => 'Unter den Linden 1',
    'city'       => 'Berlin',
    'postalCode' => '10117',
    'country'    => 'DE',
]);

if ($coordinate !== null) {
    echo $coordinate->getLatitude();   // 52.5163...
    echo $coordinate->getLongitude();  // 13.3777...
}
```

#### Reverse geocoding (coordinates → address)

```php
$address = $service->reverseGeocode(52.5163, 13.3777);

if ($address !== null) {
    echo $address['street'];     // "Unter den Linden"
    echo $address['city'];       // "Berlin"
    echo $address['postalCode']; // "10117"
    echo $address['country'];    // "de"
}
```

#### Routing (start + waypoints + destination → route)

```php
$result = $service->route(
    ['lat' => 52.5163, 'lng' => 13.3777],  // origin: Berlin
    ['lat' => 48.1351, 'lng' => 11.5820],  // destination: Munich
    [
        ['lat' => 50.9333, 'lng' => 6.9500], // waypoint: Cologne
    ]
);

if ($result !== null) {
    echo $result['distanceMeters'];   // total distance in meters
    echo $result['durationSeconds'];  // total duration in seconds
    print_r($result['steps']);        // array of route steps
}
```

---

## License

BSD 3-Clause License — see [LICENSE](LICENSE).

