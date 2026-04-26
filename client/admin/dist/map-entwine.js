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

                    // Small delay to ensure all assets are loaded
                    setTimeout(function() {
                        window.GeocodingMapUtils.initializeMap(container, provider);
                    }, 100);

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
