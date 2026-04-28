<div class="address-field"
     id="$ID"
     data-suggest-url="$SuggestUrl"
     data-create-url="$CreateUrl"
     data-provider="$Provider"
     data-provider-config="$ProviderConfig.ATT"
     data-default-lat="$DefaultLatitude"
     data-default-lng="$DefaultLongitude">

    <%-- Hidden input stores the Address ID --%>
    <input type="hidden"
           name="$Name"
           id="{$ID}_input"
           value="$Value"
           class="address-field__id-input" />

    <%-- Preview row --%>
    <div class="address-field__preview">
        <span class="address-field__title<% if not AddressTitle %> address-field__title--empty<% end_if %>">
            <% if AddressTitle %>$AddressTitle<% else %>–<% end_if %>
        </span>
        <div class="address-field__actions">
            <button type="button" class="address-field__btn address-field__btn--change btn btn-outline-secondary btn-sm">
                $LabelChange
            </button>
            <% if AddressTitle %>
            <button type="button" class="address-field__btn address-field__btn--clear btn btn-outline-danger btn-sm">
                $LabelClear
            </button>
            <% end_if %>
        </div>
    </div>

    <%-- Modal (hidden by default, moved to <body> on init) --%>
    <div class="address-field-modal" data-field-id="$ID" style="display:none" aria-modal="true" role="dialog">
        <div class="address-field-modal__overlay"></div>
        <div class="address-field-modal__dialog">

            <div class="address-field-modal__header">
                <span>$Title</span>
                <button type="button" class="address-field-modal__close" aria-label="$LabelCancel">✕</button>
            </div>

            <div class="address-field-modal__body">

                <%-- Search --%>
                <div class="address-field-search">
                    <input type="text"
                           class="address-field-search__input form-control"
                           placeholder="$LabelSearch"
                           autocomplete="off" />
                    <ul class="address-field-search__suggestions" style="display:none"></ul>
                </div>

                <%-- Map hint --%>
                <p class="address-field-map-hint">$LabelMapHint</p>

                <%-- Map --%>
                <div class="address-field-map-wrap">
                    <% if Provider != 'none' %>
                    <div class="geocoding-map-container address-field-map"
                         data-provider="$Provider"
                         data-provider-config="$ProviderConfig.ATT"
                         data-latitude="<% if HasAddressMarker %>$AddressLatitude<% else %>$DefaultLatitude<% end_if %>"
                         data-longitude="<% if HasAddressMarker %>$AddressLongitude<% else %>$DefaultLongitude<% end_if %>"
                         data-has-marker="<% if HasAddressMarker %>true<% else %>false<% end_if %>"
                         data-zoom="6"
                         data-zoom-with-marker="15"
                         data-latitude-field-id="{$ID}_modal_lat"
                         data-longitude-field-id="{$ID}_modal_lng"
                         data-readonly="false"
                         data-allow-place-marker="true"
                         data-allow-clear="false"
                         data-allow-fullscreen="false"
                         data-active-layer=""
                         data-primary-marker-icon=""
                         data-primary-marker-icon-width="0"
                         data-primary-marker-icon-height="0"
                         data-primary-marker-info-window="<% if AddressTitle %>{$AddressTitle.ATT}<% end_if %>"
                         data-additional-markers="[]"
                         data-route="null"
                         data-fit-bounds="false">
                    </div>
                    <input type="hidden" id="{$ID}_modal_lat" class="address-field-modal__lat" value="" />
                    <input type="hidden" id="{$ID}_modal_lng" class="address-field-modal__lng" value="" />
                    <% else %>
                    <p class="message notice">$LabelNoProvider</p>
                    <% end_if %>
                </div>

            </div><%-- /.address-field-modal__body --%>

            <div class="address-field-modal__footer">
                <button type="button" class="address-field-modal__accept btn btn-primary" disabled>
                    $LabelAccept
                </button>
                <button type="button" class="address-field-modal__cancel btn btn-secondary">
                    $LabelCancel
                </button>
            </div>

        </div><%-- /.address-field-modal__dialog --%>
    </div><%-- /.address-field-modal --%>

</div><%-- /.address-field --%>

