/**
 * Shared utilities for all map providers.
 *
 * This module provides common functionality that all map provider
 * implementations can use to ensure consistent behavior.
 */
window.GeocodingMapUtils = (function() {
    'use strict';

    // Registry of provider initialization functions
    const providerInitializers = {};

    return {
        /**
         * Registers a provider initialization function.
         *
         * @param {string} providerKey Provider identifier (e.g. 'google', 'osm', 'bing')
         * @param {Function} initFunction Function that initializes maps for this provider
         */
        registerProvider: function(providerKey, initFunction) {
            providerInitializers[providerKey] = initFunction;
        },

        /**
         * Initializes a single map container.
         *
         * @param {HTMLElement} container The map container element
         * @param {string} provider The provider key
         */
        initializeMap: function(container, provider) {
            if (!container || container.dataset.initialized === 'true') {
                return; // Already initialized
            }

            const initFunction = providerInitializers[provider];

            if (initFunction) {
                try {
                    initFunction(container);
                    container.dataset.initialized = 'true';
                } catch (error) {
                    console.error('Error initializing map for provider ' + provider + ':', error);
                    this.showError(container, 'Failed to initialize map.');
                }
            } else {
                console.warn('No initializer registered for provider: ' + provider);
            }
        },

        /**
         * Initializes all maps for a specific provider.
         *
         * @param {string} providerKey Provider identifier
         */
        initializeAllMaps: function(providerKey) {
            const containers = document.querySelectorAll('.geocoding-map-container[data-provider="' + providerKey + '"]');
            const self = this;

            containers.forEach(function(container) {
                self.initializeMap(container, providerKey);
            });
        },

        /**
         * Updates the hidden latitude and longitude input fields.
         *
         * @param {string} latFieldId
         * @param {string} lngFieldId
         * @param {number} lat
         * @param {number} lng
         */
        updateCoordinateFields: function(latFieldId, lngFieldId, lat, lng) {
            const latField = document.getElementById(latFieldId);
            const lngField = document.getElementById(lngFieldId);

            if (latField) latField.value = lat.toFixed(7);
            if (lngField) lngField.value = lng.toFixed(7);

            // Dispatch custom event for extensibility
            window.dispatchEvent(new CustomEvent('geocoding:coordinates-updated', {
                detail: {
                    latitude: lat,
                    longitude: lng,
                    latFieldId: latFieldId,
                    lngFieldId: lngFieldId
                }
            }));
        },

        /**
         * Clears the hidden latitude and longitude input fields.
         *
         * @param {string} latFieldId
         * @param {string} lngFieldId
         */
        clearCoordinateFields: function(latFieldId, lngFieldId) {
            const latField = document.getElementById(latFieldId);
            const lngField = document.getElementById(lngFieldId);

            if (latField) latField.value = '';
            if (lngField) lngField.value = '';

            // Dispatch custom event for extensibility
            window.dispatchEvent(new CustomEvent('geocoding:coordinates-cleared', {
                detail: {
                    latFieldId: latFieldId,
                    lngFieldId: lngFieldId
                }
            }));
        },

        /**
         * Shows an error message in the map container.
         *
         * @param {HTMLElement} container
         * @param {string} message
         */
        showError: function(container, message) {
            container.innerHTML = '<p class="message error">' + this.escapeHtml(message) + '</p>';
        },

        /**
         * Escapes HTML special characters.
         *
         * @param {string} text
         * @return {string}
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Parses provider config from data attribute with error handling.
         *
         * @param {HTMLElement} container
         * @return {Object}
         */
        parseProviderConfig: function(container) {
            try {
                return JSON.parse(container.dataset.providerConfig || '{}');
            } catch (error) {
                console.error('Failed to parse provider config:', error);
                return {};
            }
        },

        /**
         * Collects all coordinate pairs from params into a flat array of {lat, lng} objects.
         *
         * Includes: primary marker, additional markers and all route waypoints.
         * Use this to calculate a bounding box for fitBounds.
         *
         * @param {Object} params  Return value of getMapParameters()
         * @return {Array<{lat: number, lng: number}>}
         */
        collectAllLatLngs: function(params) {
            const points = [];

            // Primary marker
            if (params.hasMarker) {
                points.push({ lat: params.latitude, lng: params.longitude });
            }

            // Additional markers
            params.additionalMarkers.forEach(function(m) {
                points.push({ lat: m.lat, lng: m.lng });
            });

            // Route waypoints
            if (params.route) {
                const r = params.route;
                points.push({ lat: r.origin.lat, lng: r.origin.lng });
                r.waypoints.forEach(function(wp) {
                    points.push({ lat: wp.lat, lng: wp.lng });
                });
                points.push({ lat: r.destination.lat, lng: r.destination.lng });
            }

            return points;
        },

        /**
         * Builds a Leaflet icon from a marker data object.
         * Returns null when no custom icon URL is set (use Leaflet default).
         *
         * @param {Object} markerData  Object with iconUrl, iconWidth, iconHeight
         * @return {L.Icon|null}
         */
        buildLeafletIcon: function(markerData) {
            if (typeof L === 'undefined' || !markerData.iconUrl) {
                return null;
            }
            const opts = { iconUrl: markerData.iconUrl };
            if (markerData.iconWidth && markerData.iconHeight) {
                opts.iconSize     = [markerData.iconWidth, markerData.iconHeight];
                opts.iconAnchor   = [Math.round(markerData.iconWidth / 2), markerData.iconHeight];
                opts.popupAnchor  = [0, -markerData.iconHeight];
            }
            return L.icon(opts);
        },

        /**
         * Binds a Leaflet popup and/or tooltip to a marker.
         *
         * @param {L.Marker} leafletMarker
         * @param {Object}   markerData   Object with title and infoWindow
         */
        bindLeafletPopup: function(leafletMarker, markerData) {
            if (markerData.title) {
                leafletMarker.bindTooltip(markerData.title);
            }
            if (markerData.infoWindow) {
                leafletMarker.bindPopup(markerData.infoWindow);
                leafletMarker.on('click', function() {
                    leafletMarker.openPopup();
                });
            }
        },

        /**
         * Gets standard map parameters from container data attributes.
         *
         * @param {HTMLElement} container
         * @return {Object}
         */
        getMapParameters: function(container) {            const hasMarker = container.dataset.hasMarker === 'true' || container.dataset.hasMarker === '1';
            const baseZoom = parseInt(container.dataset.zoom, 10) || 6;
            const markerZoom = parseInt(container.dataset.zoomWithMarker, 10) || 15;
            const isReadonly = container.dataset.readonly === 'true';

            // Parse additional markers JSON
            let additionalMarkers = [];
            try {
                additionalMarkers = JSON.parse(container.dataset.additionalMarkers || '[]');
            } catch (e) { /* ignore */ }

            // Parse route JSON
            let route = null;
            try {
                const raw = container.dataset.route || 'null';
                route = JSON.parse(raw);
            } catch (e) { /* ignore */ }

            return {
                latitude: parseFloat(container.dataset.latitude) || 51.1657,
                longitude: parseFloat(container.dataset.longitude) || 10.4515,
                hasMarker: hasMarker,
                zoom: hasMarker ? markerZoom : baseZoom,
                baseZoom: baseZoom,
                markerZoom: markerZoom,
                isReadonly: isReadonly,
                allowPlaceMarker: !isReadonly && container.dataset.allowPlaceMarker !== 'false',
                allowClear: container.dataset.allowClear === 'true',
                allowFullscreen: container.dataset.allowFullscreen === 'true',
                activeLayer: container.dataset.activeLayer || '',
                // Primary marker customisation
                primaryMarkerIconUrl: container.dataset.primaryMarkerIcon || '',
                primaryMarkerIconWidth: parseInt(container.dataset.primaryMarkerIconWidth, 10) || 0,
                primaryMarkerIconHeight: parseInt(container.dataset.primaryMarkerIconHeight, 10) || 0,
                primaryMarkerInfoWindow: container.dataset.primaryMarkerInfoWindow || '',
                // Additional markers and route
                additionalMarkers: additionalMarkers,
                route: route,
                fitBounds: container.dataset.fitBounds === 'true',
                latFieldId: container.dataset.latitudeFieldId,
                lngFieldId: container.dataset.longitudeFieldId,
            };
        }
    };

})();

