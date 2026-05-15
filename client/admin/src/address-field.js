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
    'use strict';

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
     * Fetches geocoding suggestions for a query string.
     * @param {string} suggestUrl
     * @param {string} query
     * @returns {Promise<Array>}
     */
    function fetchSuggestions(suggestUrl, query) {
        return fetch(suggestUrl + '?q=' + encodeURIComponent(query) + '&limit=6', {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) { return r.json(); }).catch(function () { return []; });
    }

    /**
     * Searches existing Address records in the database.
     * @param {string} searchUrl
     * @param {string} query
     * @returns {Promise<Array>}
     */
    function fetchAddressRecords(searchUrl, query) {
        return fetch(searchUrl + '?q=' + encodeURIComponent(query) + '&limit=5', {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) { return r.json(); }).catch(function () { return []; });
    }

    /* ------------------------------------------------------------------ */
    /*  Modal management                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Moves the modal element to <body> so it is not clipped by overflow:hidden parents.
     * Removes any stale modal already present in <body> for this field ID (can occur
     * when Silverstripe CMS replaces a form via AJAX without triggering onunmatch).
     * @param {jQuery} $field
     * @returns {jQuery} the detached-and-appended modal
     */
    function attachModalToBody($field) {
        var $modal = $field.find('.address-field-modal');
        if ($modal.length === 0) { return $(); }

        var fieldId = $field.attr('id');

        // Remove any leftover modal that was not cleaned up by onunmatch
        $('body').children('.address-field-modal[data-field-id="' + fieldId + '"]').remove();

        $modal.data('address-field-id', fieldId);
        $('body').append($modal.detach());
        return $modal;
    }

    /**
     * Fires geocoding:resize several times after the modal opens so Leaflet
     * can recalculate tile coverage once the container has real dimensions.
     * @param {HTMLElement} mapEl
     */
    function ensureMapSize(mapEl) {
        // Fire immediately and again after short intervals to handle render timing
        var delays = [50, 150, 350, 700];
        delays.forEach(function (ms) {
            setTimeout(function () {
                mapEl.dispatchEvent(new CustomEvent('geocoding:resize', { bubbles: false }));
            }, ms);
        });
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
        $modal.find('.address-field-search__spinner').hide();
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
            var $clearBtn = $('<button type="button" class="address-field__btn address-field__btn--clear btn btn-outline-danger btn-sm">');
            $clearBtn.attr('title', $field.data('label-clear') || 'Adresse entfernen');
            $clearBtn.attr('aria-label', $field.data('label-clear') || 'Adresse entfernen');
            $clearBtn.append($('<span class="font-icon-cancel" aria-hidden="true">'));
            $field.find('.address-field__actions').append($clearBtn);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Suggestions dropdown                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Renders a group header into the suggestions list.
     * @param {jQuery} $list
     * @param {string} label
     */
    function renderGroupHeader($list, label) {
        $list.append(
            $('<li class="address-field-search__group-header" aria-disabled="true">').text(label)
        );
    }

    /**
     * Renders combined results from DB records and geocoding suggestions.
     * DB records are shown first (different styling), geocoding suggestions second.
     *
     * @param {jQuery} $list
     * @param {Array}  records       Existing Address records: { id, title, summary, lat, lng }
     * @param {Array}  suggestions   Geocoding suggestions:    { label, street, city, ... lat, lng }
     * @param {object} opts          { onPickRecord, onPickSuggestion }
     */
    function renderCombinedSuggestions($list, records, suggestions, opts) {
        $list.empty();

        var hasRecords     = records && records.length > 0;
        var hasSuggestions = suggestions && suggestions.length > 0;

        if (!hasRecords && !hasSuggestions) {
            $list.append($('<li class="address-field-search__no-results">').text('Keine Ergebnisse'));
            $list.show();
            return;
        }

        // --- Existing Address records ------------------------------------
        if (hasRecords) {
            renderGroupHeader($list, 'Gespeicherte Adressen');
            records.forEach(function (record) {
                var $li = $('<li class="address-field-search__suggestion address-field-search__suggestion--record" role="option" tabindex="0">');
                var $title   = $('<span class="address-field-search__suggestion-title">').text(record.title || record.summary || '–');
                var $summary = $('<span class="address-field-search__suggestion-summary">').text(record.title ? record.summary : '');
                $li.append($title);
                if (record.title && record.summary && record.summary !== record.title) {
                    $li.append($summary);
                }
                $li.on('click keydown', function (e) {
                    if (e.type === 'click' || e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        $list.hide().empty();
                        opts.onPickRecord(record);
                    }
                });
                $list.append($li);
            });
        }

        // --- Geocoding suggestions ---------------------------------------
        if (hasSuggestions) {
            renderGroupHeader($list, 'Geocoding-Vorschläge');
            suggestions.forEach(function (s) {
                var $li = $('<li class="address-field-search__suggestion address-field-search__suggestion--geocoding" role="option" tabindex="0">').text(s.label);
                $li.on('click keydown', function (e) {
                    if (e.type === 'click' || e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        $list.hide().empty();
                        opts.onPickSuggestion(s);
                    }
                });
                $list.append($li);
            });
        }

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

                var self       = this;
                var fieldId    = self.attr('id');
                var suggestUrl = self.data('suggest-url');
                var searchUrl  = self.data('search-url');
                var createUrl  = self.data('create-url');

                /* --- Close triggers ------------------------------------ */
                $modal.on('click', '.address-field-modal__overlay, .address-field-modal__close, .address-field-modal__cancel', function () {
                    closeModal($modal);
                });

                /* --- Search input -------------------------------------- */
                var $searchInput = $modal.find('.address-field-search__input');
                var $suggestions = $modal.find('.address-field-search__suggestions');
                var $spinner     = $modal.find('.address-field-search__spinner');

                $searchInput.on('input', debounce(function () {
                    var query = $searchInput.val().trim();
                    if (query.length < 2) {
                        $suggestions.hide().empty();
                        $spinner.hide();
                        return;
                    }

                    $spinner.show();
                    $suggestions.hide().empty();

                    Promise.all([
                        fetchAddressRecords(searchUrl, query),
                        fetchSuggestions(suggestUrl, query),
                    ]).then(function (results) {
                        $spinner.hide();
                        var records     = Array.isArray(results[0]) ? results[0] : [];
                        var geocodings  = Array.isArray(results[1]) ? results[1] : [];

                        renderCombinedSuggestions($suggestions, records, geocodings, {

                            onPickRecord: function (record) {
                                // Existing address — apply directly, no need to create
                                $searchInput.val(record.title || record.summary || '');
                                applySelection(fieldId, {
                                    id:    record.id,
                                    title: record.title || record.summary || '',
                                    lat:   record.lat,
                                    lng:   record.lng,
                                });

                                // Show lock icon
                                var $modal2 = $('body').children('.address-field-modal[data-field-id="' + fieldId + '"]');
                                $modal2.find('.address-field-modal__locked').removeClass('address-field-modal__locked--hidden');

                                // Pan map if coordinates are present
                                var $mapContainer = $modal.find('.geocoding-map-container');
                                if ($mapContainer.length && record.lat && record.lng) {
                                    $mapContainer[0].dispatchEvent(new CustomEvent('geocoding:pan-to', {
                                        bubbles: false,
                                        detail: { lat: record.lat, lng: record.lng, zoom: 15, placeMarker: true, label: record.title || record.summary || '' },
                                    }));
                                }

                                closeModal($modal);
                            },

                            onPickSuggestion: function (suggestion) {
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
                // Use .first() as a safety net — there should only ever be one modal per field
                var $modal  = $('.address-field-modal[data-field-id="' + fieldId + '"]').first();
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

