# GPS Coordinate Precision Guide

Understanding decimal precision in latitude/longitude coordinates.

---

## Decimal places and accuracy

| Decimal Places | Degrees | Distance | Use Case | Example |
|---|---|---|---|---|
| 0 | 1.0 | 111 km | Country/Continent | `48°, 9°` |
| 1 | 0.1 | 11.1 km | Large city | `48.7°, 9.3°` |
| 2 | 0.01 | 1.11 km | District | `48.65°, 9.28°` |
| 3 | 0.001 | 111 m | Neighborhood | `48.651°, 9.285°` |
| 4 | 0.0001 | 11.1 m | Parcel/Plot | `48.6513°, 9.2848°` |
| 5 | 0.00001 | 1.11 m | Tree/Building | `48.65129°, 9.28476°` |
| 6 | 0.000001 | 11.1 cm | Person | `48.651295°, 9.284763°` |
| **7** | **0.0000001** | **1.11 cm** | **✅ Module default** | **`48.6512955°, 9.2847633°`** |
| 8 | 0.00000001 | 1.11 mm | Survey equipment | `48.65129547°, 9.28476328°` |
| 9+ | < 0.00000001 | Sub-mm | Tectonic plates | Google's 14 places |

---

## What this module uses

**Database field:** `DECIMAL(10,7)`
- **Total digits:** 10 (including sign and decimal point)
- **Decimal places:** 7
- **Range:** -999.9999999 to 999.9999999
- **Accuracy:** ~1.1 cm

**Why 7 decimal places?**

1. ✅ **Industry standard** — Used by GPS devices, navigation systems, mapping services
2. ✅ **More than sufficient** — 1 cm accuracy is overkill for address geocoding
3. ✅ **Optimal storage** — Larger precision wastes database space without benefit
4. ✅ **Compatible** — Works with all standard GPS APIs and services

---

## Examples

### Typical address precision needs

| Scenario | Required Precision | Decimal Places Needed |
|---|---|---|
| Street navigation | 10 m | 4 |
| Building entrance | 1-2 m | 5 |
| Delivery/parking spot | 1 m | 5 |
| Room/apartment | 10 cm | 6 |
| **This module provides** | **1 cm** | **7 ✅** |

### What Google Maps returns

When you copy coordinates from Google Maps:
```
48.65129546574825, 9.28476328298257
```

**14 decimal places** = ~0.01 micrometer accuracy = completely unnecessary

This level of precision is used internally by Google for:
- Machine learning models
- Dataset consistency
- Internal calculations

But for **real-world applications**, 7 decimal places is standard.

---

## What happens to extra precision?

### Input (from Google Maps)
```
Latitude:  48.65129546574825  (14 decimal places)
Longitude:  9.28476328298257  (14 decimal places)
```

### Stored in database (DECIMAL(10,7))
```
Latitude:  48.6512955  (7 decimal places)
Longitude:  9.2847633  (7 decimal places)
```

### Accuracy difference
```
Distance between original and rounded: ~0.5 cm
Visual difference on map: None (invisible to human eye)
Practical impact: Zero
```

---

## Should you increase precision?

### ❌ No, if you're building:
- Address database
- Store locator
- Delivery routing
- Property management
- Tourism/POI app
- Social check-ins

### ✅ Maybe, if you're building:
- GPS surveying tool (use 8-9 decimal places)
- Seismology application (use 8-9 decimal places)
- Construction planning (use 6-8 decimal places)
- Self-driving car system (use 7-8 decimal places)

### 🤔 Considerations for increasing to DECIMAL(11,8)

**Pros:**
- Future-proof for edge cases
- Matches some scientific applications

**Cons:**
- Increases database size (every coordinate: +2 bytes)
- Slower index performance
- No practical benefit for 99.9% of use cases
- Still need to round at some point

---

## Database field definition

### Current (recommended)
```php
private static array $composite_db = [
    'Latitude'  => 'Decimal(10,7)',
    'Longitude' => 'Decimal(10,7)',
];
```

### If you need more precision
```php
// 8 decimal places = 1.1 mm (for surveying)
private static array $composite_db = [
    'Latitude'  => 'Decimal(11,8)',
    'Longitude' => 'Decimal(11,8)',
];
```

### If you need less precision
```php
// 5 decimal places = 1.1 m (saves space)
private static array $composite_db = [
    'Latitude'  => 'Decimal(8,5)',
    'Longitude' => 'Decimal(8,5)',
];
```

---

## Technical notes

### Valid latitude range
```
-90.0000000 to +90.0000000
```
- Negative = South of Equator
- Positive = North of Equator

### Valid longitude range
```
-180.0000000 to +180.0000000
```
- Negative = West of Prime Meridian
- Positive = East of Prime Meridian

### Storage requirements (per coordinate pair)

| Decimal Places | MySQL DECIMAL | Bytes per pair | 1M records |
|---|---|---|---|
| 5 | DECIMAL(8,5) | 8 bytes | 8 MB |
| **7** | **DECIMAL(10,7)** | **10 bytes** | **10 MB** |
| 8 | DECIMAL(11,8) | 12 bytes | 12 MB |
| 14 | DECIMAL(17,14) | 18 bytes | 18 MB |

**Conclusion:** The difference is negligible for most applications.

---

## References

- [Decimal Degrees Wikipedia](https://en.wikipedia.org/wiki/Decimal_degrees)
- [GPS Accuracy](https://en.wikipedia.org/wiki/Geographic_coordinate_system#Precision)
- [MySQL DECIMAL Documentation](https://dev.mysql.com/doc/refman/8.0/en/precision-math-decimal-characteristics.html)

---

**Bottom line:** This module's default of **7 decimal places** is the sweet spot for GPS coordinates — precise enough for any real-world address application, widely compatible, and optimally efficient.

