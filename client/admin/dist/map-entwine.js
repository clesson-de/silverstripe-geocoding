(function () {
    'use strict';

    /**
     * Entwine integration for MapField.
     *
     * Ensures maps are initialized when fields are loaded via AJAX
     * (e.g. in GridField detail forms, tabs, or dynamically loaded content).
     * Also wires up the clear and fullscreen control buttons.
     */
    (function($) {

        /**
         * Initializes or resizes a map container that has just become visible.
         * Skips containers that live inside an AddressField modal (those are
         * initialized by openModal() to avoid the Leaflet 0×0 tiles bug).
         *
         * @param {HTMLElement} container
         */
        function initOrResizeVisibleContainer(container) {
            if (container.closest('.address-field-modal')) { return; }

            var provider = container.dataset.provider;

            if (container.dataset.initialized !== 'true') {
                window.GeocodingMapUtils && window.GeocodingMapUtils.initializeMap(container, provider);
            }

            // Always fire resize + fit-bounds after the container becomes visible so that
            // Leaflet recalculates tile coverage and zooms to the route/bounds.
            setTimeout(function() {
                container.dispatchEvent(new CustomEvent('geocoding:resize', { bubbles: false }));
                container.dispatchEvent(new CustomEvent('geocoding:fit-bounds', { bubbles: false }));
            }, 200);
        }

        // Listen for jQuery UI tabsactivate on the document so every nested TabSet
        // is covered (the main CMS tabs as well as inner TabSets like StatusRouteTabSet).
        $(document).on('tabsactivate', function(event, ui) {
            var $panel = $(ui.newPanel);
            if (!$panel.length) { return; }

            // Give the browser one paint cycle to lay out the panel before measuring
            setTimeout(function() {
                $panel.find('.geocoding-map-container').each(function() {
                    initOrResizeVisibleContainer(this);
                });
            }, 50);
        });

        $.entwine('ss', function($) {

            /**
             * Initialize map containers when they appear in the DOM.
             * Also wires up the clear and fullscreen buttons that belong to this container.
             */
            $('.geocoding-map-container').entwine({

                onmatch: function() {
                    const container = this[0];
                    const provider = container.dataset.provider;
                    const mapId = container.id;

                    // If this map lives inside an AddressField modal, skip initialization here.
                    // openModal() will initialize it on first open, after the modal is visible
                    // and the container has real dimensions — avoiding the Leaflet 0×0 tiles bug.
                    if (container.closest('.address-field-modal')) {
                        this._super();
                        return;
                    }

                    // For all other map containers retry until the container has real dimensions,
                    // then initialize. This handles AJAX-loaded forms and initially hidden tabs.
                    var attempts = 0;
                    var maxAttempts = 20;
                    function tryInit() {
                        if (container.dataset.initialized === 'true') { return; }
                        if (container.offsetWidth > 0 && container.offsetHeight > 0) {
                            window.GeocodingMapUtils.initializeMap(container, provider);
                            return;
                        }
                        attempts++;
                        if (attempts < maxAttempts) {
                            setTimeout(tryInit, 100);
                        }
                    }
                    setTimeout(tryInit, 100);

                    // Wire up the layer switcher for this container
                    const layerSwitcher = document.querySelector('.geocoding-map-layer-switcher[data-map-id="' + mapId + '"]');
                    if (layerSwitcher) {
                        layerSwitcher.addEventListener('change', function() {
                            const layerKey = layerSwitcher.value;
                            // Update data attribute so provider can read the current layer
                            container.dataset.activeLayer = layerKey;
                            container.dispatchEvent(new CustomEvent('geocoding:layer-change', {
                                bubbles: false,
                                detail: { layer: layerKey }
                            }));
                        });
                    }

                    // Wire up the clear button that references this container
                    const clearBtn = document.querySelector('.geocoding-map-btn--clear[data-map-id="' + mapId + '"]');
                    if (clearBtn) {
                        clearBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const Utils = window.GeocodingMapUtils;
                            const params = Utils.getMapParameters(container);
                            Utils.clearCoordinateFields(params.latFieldId, params.lngFieldId);
                            container.dispatchEvent(new CustomEvent('geocoding:clear', { bubbles: false }));
                        });
                    }

                    // Wire up the fullscreen button that references this container
                    const fullscreenBtn = document.querySelector('.geocoding-map-btn--fullscreen[data-map-id="' + mapId + '"]');
                    if (fullscreenBtn) {
                        const labelEnter = fullscreenBtn.dataset.labelFullscreen || 'Fullscreen';
                        const labelExit  = fullscreenBtn.dataset.labelExitFullscreen || 'Exit fullscreen';

                        fullscreenBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const field = container.closest('.geocoding-map-field');
                            const isFullscreen = field.classList.contains('geocoding-map-field--fullscreen');

                            if (isFullscreen) {
                                field.classList.remove('geocoding-map-field--fullscreen');
                                fullscreenBtn.title = labelEnter;
                                fullscreenBtn.childNodes[fullscreenBtn.childNodes.length - 1].textContent = ' ' + labelEnter;
                            } else {
                                field.classList.add('geocoding-map-field--fullscreen');
                                fullscreenBtn.title = labelExit;
                                fullscreenBtn.childNodes[fullscreenBtn.childNodes.length - 1].textContent = ' ' + labelExit;
                            }

                            container.dispatchEvent(new CustomEvent('geocoding:resize', { bubbles: false }));
                        });
                    }

                    this._super();
                },

                onunmatch: function() {
                    this._super();
                }
            });

        });

    })(jQuery);

})();
