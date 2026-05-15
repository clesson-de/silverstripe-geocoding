/**
 * OpenStreetMap (Leaflet) integration for MapField.
 * Initializes an interactive map and allows double-clicking to set coordinates.
 *
 * User interaction:
 * - Single-click: Pan the map (no marker placement)
 * - Double-click: Place or move the marker
 * - Drag marker: Update coordinates
 *
 * This script is designed to be modular — other modules can add their own
 * map providers by following the same pattern and registering them via
 * MapProviderRegistry.
 */
(function() {
    'use strict';

    const Utils = window.GeocodingMapUtils;

    /**
     * Fits the map view to all known coordinate points (route, markers).
     * Calls invalidateSize() beforehand so Leaflet uses the correct container dimensions.
     *
     * @param {L.Map} map
     * @param {Object} params  Return value of Utils.getMapParameters()
     */
    function fitBoundsToParams(map, params) {
        const points = Utils.collectAllLatLngs(params);
        if (points.length > 1) {
            const latLngs = points.map(function(p) { return [p.lat, p.lng]; });
            map.fitBounds(L.latLngBounds(latLngs), { padding: [32, 32] });
        } else if (points.length === 1) {
            map.setView([points[0].lat, points[0].lng], params.markerZoom || 15);
        }
    }

    /**
     * Initializes a single OpenStreetMap container.
     *
     * @param {HTMLElement} container
     */
    function initOSMMap(container) {
        const config = Utils.parseProviderConfig(container);
        const params = Utils.getMapParameters(container);

        if (typeof L === 'undefined') {
            Utils.showError(container, 'Leaflet library not loaded.');
            return;
        }

        // When readonly, disable all Leaflet interaction handlers
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

        const map = L.map(container, mapOptions).setView([params.latitude, params.longitude], params.zoom);

        // Build tile layer — use activeLayer to select the right URL from layerTileUrls
        const layerTileUrls = config.layerTileUrls || {};
        const activeTileUrl = (params.activeLayer && layerTileUrls[params.activeLayer])
            ? layerTileUrls[params.activeLayer]
            : (config.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');

        let tileLayer = L.tileLayer(activeTileUrl, {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(map);

        let marker = null;

        // --- Primary (editable) marker ---
        if (params.hasMarker) {
            const primaryIconData = {
                iconUrl: params.primaryMarkerIconUrl,
                iconWidth: params.primaryMarkerIconWidth,
                iconHeight: params.primaryMarkerIconHeight,
            };
            const primaryIcon = Utils.buildLeafletIcon(primaryIconData);
            const markerOpts = { draggable: params.allowPlaceMarker };
            if (primaryIcon) {
                markerOpts.icon = primaryIcon;
            }

            marker = L.marker([params.latitude, params.longitude], markerOpts).addTo(map);
            Utils.bindLeafletPopup(marker, {
                title: '',
                infoWindow: params.primaryMarkerInfoWindow,
            });

            if (params.allowPlaceMarker) {
                marker.on('dragend', function(event) {
                    const position = event.target.getLatLng();
                    Utils.updateCoordinateFields(
                        params.latFieldId,
                        params.lngFieldId,
                        position.lat,
                        position.lng
                    );
                });
            }
        }

        // --- Additional (read-only) markers ---
        params.additionalMarkers.forEach(function(markerData) {
            const icon = Utils.buildLeafletIcon(markerData);
            const opts = {};
            if (icon) {
                opts.icon = icon;
            }
            const additionalMarker = L.marker([markerData.lat, markerData.lng], opts).addTo(map);
            Utils.bindLeafletPopup(additionalMarker, markerData);
        });

        // --- Route ---
        if (params.route) {
            const r = params.route;
            const allPoints = [r.origin, ...r.waypoints, r.destination];
            const lineStyle = {
                color:   r.color   || '#3388ff',
                weight:  r.weight  || 3,
                opacity: r.opacity !== undefined ? r.opacity : 1.0,
            };

            // Draw waypoint markers (origin, stops, destination)
            allPoints.forEach(function(wp) {
                const icon = Utils.buildLeafletIcon(wp);
                const opts = {};
                if (icon) {
                    opts.icon = icon;
                }
                const wpMarker = L.marker([wp.lat, wp.lng], opts).addTo(map);
                Utils.bindLeafletPopup(wpMarker, wp);
            });

            if (r.straightLine) {
                // Draw straight polyline through all points
                const latlngs = allPoints.map(function(wp) {
                    return [wp.lat, wp.lng];
                });
                L.polyline(latlngs, lineStyle).addTo(map);
            } else {
                // Request routed path from OSRM
                const coords = allPoints.map(function(wp) {
                    return wp.lng + ',' + wp.lat;
                }).join(';');
                fetch('https://router.project-osrm.org/route/v1/driving/' + coords + '?overview=full&geometries=geojson')
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.routes && data.routes[0]) {
                            L.geoJSON(data.routes[0].geometry, { style: lineStyle }).addTo(map);
                        } else {
                            // Fallback: straight line
                            const latlngs = allPoints.map(function(wp) { return [wp.lat, wp.lng]; });
                            L.polyline(latlngs, lineStyle).addTo(map);
                        }
                    })
                    .catch(function() {
                        // Fallback: straight line
                        const latlngs = allPoints.map(function(wp) { return [wp.lat, wp.lng]; });
                        L.polyline(latlngs, lineStyle).addTo(map);
                    });
            }
        }

        // --- Fit bounds after init ---
        // Deferred so Leaflet has a real container size after the tab/modal has settled.
        if (params.fitBounds) {
            setTimeout(function() {
                map.invalidateSize();
                fitBoundsToParams(map, params);
            }, 150);
        }

        // Resize event: invalidate Leaflet's size after the container resizes (e.g. fullscreen)
        container.addEventListener('geocoding:resize', function() {
            setTimeout(function() {
                map.invalidateSize();
            }, 50);
        });

        // Fit-bounds event: re-run invalidateSize + fitBounds (e.g. when a tab becomes visible)
        container.addEventListener('geocoding:fit-bounds', function() {
            setTimeout(function() {
                map.invalidateSize();
                fitBoundsToParams(map, params);
            }, 50);
        });

        // Only attach editing interactions when placing is allowed
        if (params.allowPlaceMarker) {
            // Add double-click listener to place/move marker
            map.on('dblclick', function(event) {
                if (!marker) {
                    marker = L.marker(event.latlng, {
                        draggable: true,
                    }).addTo(map);

                    marker.on('dragend', function(e) {
                        const position = e.target.getLatLng();
                        Utils.updateCoordinateFields(
                            params.latFieldId,
                            params.lngFieldId,
                            position.lat,
                            position.lng
                        );
                    });
                } else {
                    marker.setLatLng(event.latlng);
                }

                Utils.updateCoordinateFields(
                    params.latFieldId,
                    params.lngFieldId,
                    event.latlng.lat,
                    event.latlng.lng
                );
            });
        }

        // Clear event: remove marker and reset coordinates (always available)
        container.addEventListener('geocoding:clear', function() {
            if (marker) {
                map.removeLayer(marker);
                marker = null;
            }
        });


        // Pan-to event: move map to given coordinates, optionally place a marker with popup
        container.addEventListener('geocoding:pan-to', function(event) {
            var d = event.detail || {};
            var lat = parseFloat(d.lat);
            var lng = parseFloat(d.lng);
            if (isNaN(lat) || isNaN(lng)) { return; }

            var zoom = parseInt(d.zoom, 10) || params.markerZoom || 15;
            map.setView([lat, lng], zoom);

            if (d.placeMarker) {
                if (marker) {
                    marker.setLatLng([lat, lng]);
                } else {
                    marker = L.marker([lat, lng], { draggable: params.allowPlaceMarker }).addTo(map);
                    if (params.allowPlaceMarker) {
                        marker.on('dragend', function(e) {
                            var pos = e.target.getLatLng();
                            Utils.updateCoordinateFields(params.latFieldId, params.lngFieldId, pos.lat, pos.lng);
                            container.dispatchEvent(new CustomEvent('geocoding:marker-moved', {
                                bubbles: false, detail: { lat: pos.lat, lng: pos.lng }
                            }));
                        });
                    }
                }

                // Bind popup with address label if provided
                if (d.label) {
                    marker.unbindPopup();
                    marker.bindPopup(d.label).openPopup();
                }

                Utils.updateCoordinateFields(params.latFieldId, params.lngFieldId, lat, lng);
                container.dispatchEvent(new CustomEvent('geocoding:marker-placed', {
                    bubbles: false, detail: { lat: lat, lng: lng }
                }));
            }
        });

        // Layer change: swap the tile layer
        container.addEventListener('geocoding:layer-change', function(event) {
            const newKey = event.detail.layer;
            const newUrl = layerTileUrls[newKey];
            if (newUrl) {
                map.removeLayer(tileLayer);
                tileLayer = L.tileLayer(newUrl, {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19,
                }).addTo(map);
            }
        });
    }

    /**
     * Initializes all OpenStreetMap containers on the page.
     */
    function initOSMMaps() {
        Utils.initializeAllMaps('osm');
    }

    // Register this provider with the utilities
    if (Utils && Utils.registerProvider) {
        Utils.registerProvider('osm', initOSMMap);
    }

    // Initialize maps when DOM is ready and Leaflet is loaded
    if (typeof L !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initOSMMaps);
        } else {
            initOSMMaps();
        }
    } else {
        // Wait for Leaflet to load
        window.addEventListener('load', function() {
            if (typeof L !== 'undefined') {
                initOSMMaps();
            }
        });
    }

})();

