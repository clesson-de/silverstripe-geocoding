(function () {
    'use strict';

    /**
     * Google Maps integration for MapField.
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

        const Utils = window.GeocodingMapUtils;

        /**
         * Initializes a single Google Maps container.
         *
         * @param {HTMLElement} container
         */
        function initGoogleMap(container) {
            const config = Utils.parseProviderConfig(container);
            const params = Utils.getMapParameters(container);
            const apiKey = config.apiKey;

            if (!apiKey) {
                Utils.showError(container, 'Google Maps API key not configured.');
                return;
            }

            if (typeof google === 'undefined' || !google.maps) {
                Utils.showError(container, 'Google Maps API not loaded.');
                return;
            }

            const center = { lat: params.latitude, lng: params.longitude };
            const map = new google.maps.Map(container, {
                center: center,
                zoom: params.zoom,
                mapTypeId: params.activeLayer || 'roadmap',
                // Disable UI controls and gestures when readonly
                disableDefaultUI: params.isReadonly,
                gestureHandling: params.isReadonly ? 'none' : 'auto',
            });

            let marker = null;
            const infoWindow = new google.maps.InfoWindow();

            /**
             * Builds a Google Maps Icon object from marker data.
             * Returns null if no custom icon URL is set.
             *
             * @param {Object} markerData
             * @return {Object|null}
             */
            function buildGoogleIcon(markerData) {
                if (!markerData.iconUrl) {
                    return null;
                }
                const icon = { url: markerData.iconUrl };
                if (markerData.iconWidth && markerData.iconHeight) {
                    icon.scaledSize = new google.maps.Size(markerData.iconWidth, markerData.iconHeight);
                    icon.anchor     = new google.maps.Point(
                        Math.round(markerData.iconWidth / 2),
                        markerData.iconHeight
                    );
                }
                return icon;
            }

            /**
             * Binds a click listener that opens a shared InfoWindow with the given HTML.
             *
             * @param {google.maps.Marker} gMarker
             * @param {Object}             markerData  Object with title and infoWindow
             */
            function bindGooglePopup(gMarker, markerData) {
                if (markerData.title) {
                    gMarker.setTitle(markerData.title);
                }
                if (markerData.infoWindow) {
                    gMarker.addListener('click', function() {
                        infoWindow.setContent(markerData.infoWindow);
                        infoWindow.open(map, gMarker);
                    });
                }
            }

            // --- Primary (editable) marker ---
            if (params.hasMarker) {
                const primaryIconData = {
                    iconUrl: params.primaryMarkerIconUrl,
                    iconWidth: params.primaryMarkerIconWidth,
                    iconHeight: params.primaryMarkerIconHeight,
                };
                const primaryIcon = buildGoogleIcon(primaryIconData);
                const markerOpts = {
                    position: center,
                    map: map,
                    draggable: params.allowPlaceMarker,
                };
                if (primaryIcon) {
                    markerOpts.icon = primaryIcon;
                }

                marker = new google.maps.Marker(markerOpts);
                bindGooglePopup(marker, { title: '', infoWindow: params.primaryMarkerInfoWindow });

                if (params.allowPlaceMarker) {
                    marker.addListener('dragend', function(event) {
                        Utils.updateCoordinateFields(
                            params.latFieldId,
                            params.lngFieldId,
                            event.latLng.lat(),
                            event.latLng.lng()
                        );
                    });
                }
            }

            // --- Additional (read-only) markers ---
            params.additionalMarkers.forEach(function(markerData) {
                const icon = buildGoogleIcon(markerData);
                const opts = {
                    position: { lat: markerData.lat, lng: markerData.lng },
                    map: map,
                };
                if (icon) {
                    opts.icon = icon;
                }
                const additionalMarker = new google.maps.Marker(opts);
                bindGooglePopup(additionalMarker, markerData);
            });

            // --- Route ---
            if (params.route) {
                const r = params.route;
                const allPoints = [r.origin, ...r.waypoints, r.destination];

                // Draw waypoint markers
                allPoints.forEach(function(wp) {
                    const icon = buildGoogleIcon(wp);
                    const opts = {
                        position: { lat: wp.lat, lng: wp.lng },
                        map: map,
                    };
                    if (icon) {
                        opts.icon = icon;
                    }
                    const wpMarker = new google.maps.Marker(opts);
                    bindGooglePopup(wpMarker, wp);
                });

                const strokeColor   = r.color   || '#3388ff';
                const strokeWeight  = r.weight  || 3;
                const strokeOpacity = r.opacity !== undefined ? r.opacity : 1.0;

                if (r.straightLine) {
                    // Draw a straight polyline through all points
                    const path = allPoints.map(function(wp) {
                        return { lat: wp.lat, lng: wp.lng };
                    });
                    new google.maps.Polyline({
                        path: path,
                        geodesic: true,
                        strokeColor:   strokeColor,
                        strokeWeight:  strokeWeight,
                        strokeOpacity: strokeOpacity,
                        map: map,
                    });
                } else {
                    // Use Google Directions API for routed path
                    const directionsService  = new google.maps.DirectionsService();
                    const directionsRenderer = new google.maps.DirectionsRenderer({
                        suppressMarkers: true, // we draw our own waypoint markers
                        polylineOptions: {
                            strokeColor:   strokeColor,
                            strokeWeight:  strokeWeight,
                            strokeOpacity: strokeOpacity,
                        },
                    });
                    directionsRenderer.setMap(map);

                    const waypointsForApi = r.waypoints.map(function(wp) {
                        return {
                            location: new google.maps.LatLng(wp.lat, wp.lng),
                            stopover: true,
                        };
                    });

                    directionsService.route({
                        origin:      new google.maps.LatLng(r.origin.lat, r.origin.lng),
                        destination: new google.maps.LatLng(r.destination.lat, r.destination.lng),
                        waypoints:   waypointsForApi,
                        travelMode:  google.maps.TravelMode.DRIVING,
                    }, function(result, status) {
                        if (status === google.maps.DirectionsStatus.OK) {
                            directionsRenderer.setDirections(result);
                        } else {
                            // Fallback: straight line
                            const path = allPoints.map(function(wp) {
                                return { lat: wp.lat, lng: wp.lng };
                            });
                            new google.maps.Polyline({
                                path: path,
                                geodesic: true,
                                strokeColor:   strokeColor,
                                strokeWeight:  strokeWeight,
                                strokeOpacity: strokeOpacity,
                                map: map,
                            });
                        }
                    });
                }
            }

            // --- Fit bounds ---
            if (params.fitBounds) {
                const points = Utils.collectAllLatLngs(params);
                if (points.length > 1) {
                    const bounds = new google.maps.LatLngBounds();
                    points.forEach(function(p) { bounds.extend(p); });
                    map.fitBounds(bounds);
                } else if (points.length === 1) {
                    map.setCenter(points[0]);
                    map.setZoom(params.markerZoom);
                }
            }

            // Only attach editing interactions when placing is allowed
            if (params.allowPlaceMarker) {
                // Add double-click listener to place/move marker
                map.addListener('dblclick', function(event) {
                    if (!marker) {
                        marker = new google.maps.Marker({
                            position: event.latLng,
                            map: map,
                            draggable: true,
                        });

                        marker.addListener('dragend', function(e) {
                            Utils.updateCoordinateFields(
                                params.latFieldId,
                                params.lngFieldId,
                                e.latLng.lat(),
                                e.latLng.lng()
                            );
                        });
                    } else {
                        marker.setPosition(event.latLng);
                    }

                    Utils.updateCoordinateFields(
                        params.latFieldId,
                        params.lngFieldId,
                        event.latLng.lat(),
                        event.latLng.lng()
                    );
                });

                // Clear button: remove marker and reset coordinates
                container.addEventListener('geocoding:clear', function() {
                    if (marker) {
                        marker.setMap(null);
                        marker = null;
                    }
                });
            }

            // Resize event: trigger Google Maps resize after container resizes (e.g. fullscreen)
            container.addEventListener('geocoding:resize', function() {
                setTimeout(function() {
                    google.maps.event.trigger(map, 'resize');
                }, 50);
            });

            // Layer change: switch Google Maps map type
            container.addEventListener('geocoding:layer-change', function(event) {
                map.setMapTypeId(event.detail.layer);
            });
        }

        /**
         * Initializes all Google map containers on the page.
         */
        function initGoogleMaps() {
            Utils.initializeAllMaps('google');
        }

        // Register this provider with the utilities
        if (Utils && Utils.registerProvider) {
            Utils.registerProvider('google', initGoogleMap);
        }

        // Initialize maps when Google Maps API is loaded
        if (typeof google !== 'undefined' && google.maps) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initGoogleMaps);
            } else {
                initGoogleMaps();
            }
        } else {
            // Wait for API to load (callback from script URL)
            window.initGoogleMapsCallback = initGoogleMaps;
        }

    })();

})();
