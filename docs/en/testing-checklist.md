# Testing Checklist for MapField Integration

Use this checklist to verify the MapField works correctly in all scenarios.

---

## Prerequisites

- [ ] `composer vendor-expose` executed
- [ ] `dev/build?flush=all` executed
- [ ] Browser cache cleared (Ctrl+Shift+R / Cmd+Shift+R)

---

## Configuration

- [ ] Navigate to Settings → Geocoding in CMS
- [ ] At least one geocoding service created (Google or OSM)
- [ ] Service is marked as **Active**
- [ ] **Map display service** selected in dropdown
- [ ] Settings saved

---

## Test Scenarios

### Scenario 1: Initial page load (Address ModelAdmin)

- [ ] Open Contacts → Addresses
- [ ] Click on an existing address
- [ ] Map container appears
- [ ] Map tiles load immediately (no grey box)
- [ ] If coordinates exist: marker is visible at street-level zoom (16)
- [ ] If no coordinates: map shows country-level zoom (6)

### Scenario 2: New Address (no coordinates)

- [ ] Click "Add Address"
- [ ] Map shows at zoom level 6 (country/region view)
- [ ] No marker visible
- [ ] Single-click on map → map pans (no marker placed)
- [ ] Double-click anywhere on map
- [ ] Marker appears at clicked location
- [ ] Marker is draggable
- [ ] Save address
- [ ] Reopen address
- [ ] Marker is at saved location with zoom 16

### Scenario 3: Edit existing Address (with coordinates)

- [ ] Open address that has coordinates
- [ ] Map shows at zoom level 16 (street view)
- [ ] Marker is visible at correct location
- [ ] Single-click on map → map pans (marker doesn't move)
- [ ] Double-click on new location → marker moves
- [ ] Drag marker to new location
- [ ] Hidden fields update (check DOM inspector)
- [ ] Save address
- [ ] Coordinates persisted correctly

### Scenario 4: AJAX loading (via GridField)

- [ ] Navigate to different section (e.g. Companies)
- [ ] Navigate back to Addresses
- [ ] Click "Edit" on an address
- [ ] Map initializes immediately (no grey box)
- [ ] Map tiles load without requiring page reload
- [ ] Marker appears if coordinates exist

### Scenario 5: Tab switching

- [ ] Open an address
- [ ] Switch to different tab (e.g. "History")
- [ ] Switch back to "Main" tab (where map is)
- [ ] Map re-initializes correctly
- [ ] No double-initialization (check console)

### Scenario 6: Multiple maps on same page

If you have multiple MapFields (e.g. BillingAddress and DeliveryAddress):

- [ ] Both maps render correctly
- [ ] Each map has independent markers
- [ ] Clicking/dragging one doesn't affect the other
- [ ] Both save their coordinates correctly

---

## Provider-Specific Tests

### OpenStreetMap (Leaflet)

- [ ] Tiles load from openstreetmap.org
- [ ] Attribution visible: "© OpenStreetMap contributors"
- [ ] Zoom controls visible (+ / -)
- [ ] Dragging map works
- [ ] Double-click zoom works

### Google Maps

- [ ] Google Maps tiles load
- [ ] Google logo visible
- [ ] Street View control visible (if enabled)
- [ ] Zoom controls visible
- [ ] Pan/zoom gestures work

---

## Browser Compatibility

Test in multiple browsers:

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

---

## Console Checks

### No errors

- [ ] No JavaScript errors in console
- [ ] No 404 errors for assets
- [ ] No CORS errors

### Debug output (optional)

Run in browser console:

```javascript
// Check initialization
document.querySelectorAll('.geocoding-map-container').forEach(c => {
    console.log('Container:', c.id, 'Initialized:', c.dataset.initialized);
});

// Listen for coordinate updates
window.addEventListener('geocoding:coordinates-updated', e => {
    console.log('Coordinates updated:', e.detail);
});
```

---

## Performance

- [ ] Map tiles load within 2 seconds (on good connection)
- [ ] No visible lag when clicking/dragging marker
- [ ] Page load time not significantly impacted
- [ ] Network tab shows assets cached correctly

---

## Common Issues & Solutions

| Issue | Solution |
|---|---|
| Grey box on first load | Clear browser cache, ensure `vendor-expose` ran |
| "Library not loaded" error | Check network tab, verify CDN accessible |
| Map doesn't initialize in GridField | Verify Entwine is working: `typeof jQuery.entwine` |
| Coordinates don't save | Check hidden input fields exist in DOM |
| Double markers | Check `data-initialized` attribute prevents re-init |
| Slow tile loading | Normal for OSM, consider self-hosted tile server |

---

## Next Steps

If all tests pass:
- ✅ MapField is working correctly
- ✅ Ready for production use
- ✅ Can be extended with custom providers

If tests fail:
- Check [Troubleshooting](../../README.md#troubleshooting) in main README
- Review [Asset loading documentation](asset-loading.md)
- Check browser console for errors
- Verify SiteConfig settings

---

## Reporting Issues

If you encounter a bug:

1. Check the browser console for errors
2. Verify asset loading order (F12 → Network tab)
3. Test with both Google and OSM providers
4. Document steps to reproduce
5. Open an issue on GitHub with:
   - Browser version
   - Silverstripe version
   - Provider used (Google/OSM)
   - Console errors (if any)
   - Screenshots

