(function () {
    'use strict';

    /**
     * AddressField — jQuery Entwine integration for the geocoding address picker.
     *
     * Behaviour:
     *  - On "Adresse wählen" click: opens a modal with a search input and a map.
     *  - While typing: debounced AJAX request to /geocoding-api/address/suggest.
     *  - On suggestion click: POST to /geocoding-api/address/create, link address to field.
     *  - On map double-click: place marker, enable "Accept" button.
     *  - On "Accept" click: POST with coordinates → create address via reverse geocode.
     *  - On "Cancel" / overlay click: close modal, discard pending selection.
     */
    (function ($) {

        /* ------------------------------------------------------------------ */
        /*  Helpers                                                             */
        /* ------------------------------------------------------------------ */

        /**
         * Simple debounce.
         * @param {Function} fn
         * @param {number}   delay ms
         * @returns {Function}
         */
        function debounce(fn, delay) {
            var timer = null;
            return function () {
                var args = arguments;
                var ctx  = this;
                clearTimeout(timer);
                timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
            };
        }

        /**
         * Posts JSON to a URL and returns a Promise resolving with the parsed response.
         * @param {string} url
         * @param {object} data
         * @returns {Promise<object>}
         */
        function postJSON(url, data) {
            return fetch(url, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body:    JSON.stringify(data),
            }).then(function (r) { return r.json(); });
        }

        /**
         * Fetches address suggestions for a query string.
         * @param {string} suggestUrl
         * @param {string} query
         * @returns {Promise<Array>}
         */
        function fetchSuggestions(suggestUrl, query) {
            return fetch(suggestUrl + '?q=' + encodeURIComponent(query) + '&limit=6', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function (r) { return r.json(); });
        }

        /* ------------------------------------------------------------------ */
        /*  Modal management                                                    */
        /* ------------------------------------------------------------------ */

        /**
         * Moves the modal element to <body> so it is not clipped by overflow:hidden parents.
         * Stores the originating field ID on the modal for reconnection.
         * @param {jQuery} $field
         * @returns {jQuery} the detached-and-appended modal
         */
        function attachModalToBody($field) {
            var $modal = $field.find('.address-field-modal');
            if ($modal.length === 0) { return $(); }

            $modal.data('address-field-id', $field.attr('id'));
            $('body').append($modal.detach());
            return $modal;
        }

        /**
         * Fires geocoding:resize repeatedly until the map container has a real size.
         * Leaflet needs this when the container was hidden (display:none) during init.
         * @param {HTMLElement} mapEl
         */
        function ensureMapSize(mapEl) {
            var attempts = 0;
            var maxAttempts = 10;
            function check() {
                attempts++;
                mapEl.dispatchEvent(new CustomEvent('geocoding:resize', { bubbles: false }));
                if (attempts < maxAttempts && (mapEl.offsetWidth === 0 || mapEl.offsetHeight === 0)) {
                    setTimeout(check, 100);
                }
            }
            // First attempt after a short delay to let CSS kick in
            setTimeout(check, 50);
        }

        /**
         * Opens the modal, initialises the map (if needed) and wires all controls.
         * @param {jQuery} $field
         * @param {jQuery} $modal
         */
        function openModal($field, $modal) {
            $modal.show();
            document.body.classList.add('address-field-modal--open');

            // Initialise map once
            var $mapContainer = $modal.find('.geocoding-map-container');
            if ($mapContainer.length) {
                var mapEl   = $mapContainer[0];
                var provider = mapEl.dataset.provider;

                if (mapEl.dataset.initialized !== 'true') {
                    // Wait one frame so the modal is laid out, then init
                    requestAnimationFrame(function () {
                        window.GeocodingMapUtils.initializeMap(mapEl, provider);

                        // Mirror coordinate inputs when marker moves or is placed
                        mapEl.addEventListener('geocoding:marker-moved', function (e) {
                            var d = e.detail || {};
                            $modal.find('.address-field-modal__lat').val(d.lat || '');
                            $modal.find('.address-field-modal__lng').val(d.lng || '');
                            $modal.find('.address-field-modal__accept').prop('disabled', false);
                        });

                        mapEl.addEventListener('geocoding:marker-placed', function (e) {
                            var d = e.detail || {};
                            $modal.find('.address-field-modal__lat').val(d.lat || '');
                            $modal.find('.address-field-modal__lng').val(d.lng || '');
                            $modal.find('.address-field-modal__accept').prop('disabled', false);
                        });

                        // Force repeated tile reload until container has real dimensions
                        ensureMapSize(mapEl);
                    });
                } else {
                    // Map already initialised — invalidate size after modal becomes visible
                    ensureMapSize(mapEl);

                    // Pan to existing address marker if present
                    var lat = parseFloat(mapEl.dataset.latitude);
                    var lng = parseFloat(mapEl.dataset.longitude);
                    var hasMarker = mapEl.dataset.hasMarker === 'true';
                    if (hasMarker && !isNaN(lat) && !isNaN(lng)) {
                        var infoLabel = mapEl.dataset.primaryMarkerInfoWindow || '';
                        mapEl.dispatchEvent(new CustomEvent('geocoding:pan-to', {
                            bubbles: false,
                            detail: { lat: lat, lng: lng, zoom: 15, placeMarker: true, label: infoLabel },
                        }));
                        $modal.find('.address-field-modal__lat').val(lat);
                        $modal.find('.address-field-modal__lng').val(lng);
                    }
                }
            }

            // Focus the search input
            setTimeout(function () { $modal.find('.address-field-search__input').trigger('focus'); }, 150);
        }

        /**
         * Closes and hides the modal. Clears pending state.
         * @param {jQuery} $modal
         */
        function closeModal($modal) {
            $modal.hide();
            document.body.classList.remove('address-field-modal--open');
            $modal.find('.address-field-search__input').val('');
            $modal.find('.address-field-search__suggestions').hide().empty();
            $modal.find('.address-field-modal__lat').val('');
            $modal.find('.address-field-modal__lng').val('');
            $modal.find('.address-field-modal__accept').prop('disabled', true);
            $modal.removeData('pending');
        }

        /**
         * Applies a confirmed address selection back to the originating field.
         * @param {string} fieldId
         * @param {object} result  { id, title, lat, lng }
         */
        function applySelection(fieldId, result) {
            var $field = $('#' + fieldId);
            if (!$field.length) { return; }

            $field.find('.address-field__id-input').val(result.id);
            $field.find('.address-field__title').text(result.title).removeClass('address-field__title--empty');

            // Show the clear button if not yet visible
            if (!$field.find('.address-field__btn--clear').length) {
                $field.find('.address-field__actions').append(
                    $('<button type="button" class="address-field__btn address-field__btn--clear btn btn-outline-danger btn-sm">')
                        .text($field.data('label-clear') || 'Adresse entfernen')
                );
            }
        }

        /* ------------------------------------------------------------------ */
        /*  Suggestions dropdown                                               */
        /* ------------------------------------------------------------------ */

        /**
         * Renders suggestion items into the dropdown list.
         * @param {jQuery} $list
         * @param {Array}  suggestions
         * @param {object} opts  { onPick }
         */
        function renderSuggestions($list, suggestions, opts) {
            $list.empty();

            if (!suggestions || !suggestions.length) {
                $list.append($('<li class="address-field-search__no-results">').text('Keine Ergebnisse'));
                $list.show();
                return;
            }

            suggestions.forEach(function (s) {
                var $li = $('<li class="address-field-search__suggestion" role="option" tabindex="0">').text(s.label);
                $li.on('click keydown', function (e) {
                    if (e.type === 'click' || e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        $list.hide().empty();
                        opts.onPick(s);
                    }
                });
                $list.append($li);
            });

            $list.show();
        }

        /* ------------------------------------------------------------------ */
        /*  Entwine                                                             */
        /* ------------------------------------------------------------------ */

        $.entwine('ss', function ($) {

            /**
             * Root field element.
             */
            $('.address-field').entwine({

                onmatch: function () {
                    this._super();
                    this._modal = attachModalToBody(this);
                    this._bindModal(this._modal);
                },

                onunmatch: function () {
                    // Remove modal from body when the field is removed
                    if (this._modal && this._modal.length) {
                        this._modal.remove();
                    }
                    this._super();
                },

                /**
                 * Wires all modal controls.
                 * @param {jQuery} $modal
                 */
                _bindModal: function ($modal) {
                    if (!$modal || !$modal.length) { return; }

                    var self     = this;
                    var fieldId  = self.attr('id');
                    var suggestUrl = self.data('suggest-url');
                    var createUrl  = self.data('create-url');

                    /* --- Close triggers ------------------------------------ */
                    $modal.on('click', '.address-field-modal__overlay, .address-field-modal__close, .address-field-modal__cancel', function () {
                        closeModal($modal);
                    });

                    /* --- Search input -------------------------------------- */
                    var $searchInput = $modal.find('.address-field-search__input');
                    var $suggestions = $modal.find('.address-field-search__suggestions');

                    $searchInput.on('input', debounce(function () {
                        var query = $searchInput.val().trim();
                        if (query.length < 2) {
                            $suggestions.hide().empty();
                            return;
                        }
                        fetchSuggestions(suggestUrl, query).then(function (data) {
                            renderSuggestions($suggestions, data, {
                                onPick: function (suggestion) {
                                    $searchInput.val(suggestion.label);
                                    $modal.data('pending', { source: 'suggestion', suggestion: suggestion });
                                    $modal.find('.address-field-modal__accept').prop('disabled', false);

                                    // Pan map to suggestion coordinates
                                    var $mapContainer = $modal.find('.geocoding-map-container');
                                    if ($mapContainer.length && suggestion.lat && suggestion.lng) {
                                        $mapContainer[0].dispatchEvent(new CustomEvent('geocoding:pan-to', {
                                            bubbles: false,
                                            detail: { lat: suggestion.lat, lng: suggestion.lng, zoom: 15, placeMarker: true, label: suggestion.label },
                                        }));
                                        $modal.find('.address-field-modal__lat').val(suggestion.lat);
                                        $modal.find('.address-field-modal__lng').val(suggestion.lng);
                                    }
                                },
                            });
                        });
                    }, 300));

                    // Close suggestions when clicking outside
                    $(document).on('click.address-field-' + fieldId, function (e) {
                        if (!$(e.target).closest('.address-field-search').length) {
                            $suggestions.hide();
                        }
                    });

                    /* --- Accept button ------------------------------------- */
                    $modal.on('click', '.address-field-modal__accept', function () {
                        var pending    = $modal.data('pending');
                        var lat        = parseFloat($modal.find('.address-field-modal__lat').val());
                        var lng        = parseFloat($modal.find('.address-field-modal__lng').val());
                        var hasCoords  = !isNaN(lat) && !isNaN(lng);

                        var payload;

                        if (pending && pending.source === 'suggestion') {
                            var s = pending.suggestion;
                            payload = {
                                source: 'suggestion',
                                label: s.label, street: s.street, city: s.city,
                                postalCode: s.postalCode, country: s.country,
                                lat: s.lat, lng: s.lng,
                            };
                        } else if (hasCoords) {
                            payload = { source: 'coordinates', lat: lat, lng: lng };
                        } else {
                            return; // nothing to save
                        }

                        postJSON(createUrl, payload).then(function (result) {
                            if (result && result.id) {
                                applySelection(fieldId, result);
                                closeModal($modal);
                            }
                        });
                    });
                },
            });

            /**
             * "Adresse wählen" button.
             */
            $('.address-field .address-field__btn--change').entwine({
                onclick: function (e) {
                    e.preventDefault();
                    var $field  = this.closest('.address-field');
                    var fieldId = $field.attr('id');
                    var $modal  = $('.address-field-modal[data-field-id="' + fieldId + '"]');
                    if ($modal.length) { openModal($field, $modal); }
                },
            });

            /**
             * "Adresse entfernen" button.
             */
            $('.address-field .address-field__btn--clear').entwine({
                onclick: function (e) {
                    e.preventDefault();
                    var $field = this.closest('.address-field');
                    $field.find('.address-field__id-input').val('');
                    $field.find('.address-field__title').text('–').addClass('address-field__title--empty');

                    // Reset map state so next modal open shows no marker
                    var fieldId = $field.attr('id');
                    var $modal  = $('.address-field-modal[data-field-id="' + fieldId + '"]');
                    if ($modal.length) {
                        var mapEl = $modal.find('.geocoding-map-container')[0];
                        if (mapEl) {
                            mapEl.dataset.hasMarker = 'false';
                            mapEl.dataset.latitude  = mapEl.dataset.latitude; // keep center
                            mapEl.dataset.primaryMarkerInfoWindow = '';
                            // Tell provider to remove the marker
                            mapEl.dispatchEvent(new CustomEvent('geocoding:clear', { bubbles: false }));
                        }
                        $modal.find('.address-field-modal__lat').val('');
                        $modal.find('.address-field-modal__lng').val('');
                        $modal.find('.address-field-modal__accept').prop('disabled', true);
                        $modal.removeData('pending');
                    }

                    this.remove();
                },
            });

        });

    })(jQuery);

})();
