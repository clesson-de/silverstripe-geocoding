<div class="geocoding-map-field<% if IsReadonly %> geocoding-map-field--readonly<% end_if %>">
    <% if not IsReadonly %>
    <input type="hidden" name="{$Name}[Latitude]" id="{$LatitudeFieldID}" value="{$Latitude}" class="geocoding-map-latitude" />
    <input type="hidden" name="{$Name}[Longitude]" id="{$LongitudeFieldID}" value="{$Longitude}" class="geocoding-map-longitude" />
    <% end_if %>

    <div class="geocoding-map-wrapper">
        <div id="{$ID}_map" class="geocoding-map-container"
             data-provider="{$Provider}"
             data-provider-config="{$ProviderConfig}"
             data-latitude="{$Latitude}"
             data-longitude="{$Longitude}"
             data-has-marker="{$HasMarker}"
             data-zoom="{$ZoomLevel}"
             data-zoom-with-marker="{$ZoomLevelWithMarker}"
             data-latitude-field-id="{$LatitudeFieldID}"
             data-longitude-field-id="{$LongitudeFieldID}"
             data-readonly="<% if IsReadonly %>true<% else %>false<% end_if %>"
             data-allow-place-marker="<% if AllowPlaceMarker %>true<% else %>false<% end_if %>"
             data-allow-clear="<% if AllowClear %>true<% else %>false<% end_if %>"
             data-allow-fullscreen="<% if AllowFullscreen %>true<% else %>false<% end_if %>"
             data-active-layer="{$ActiveLayer}"
             data-primary-marker-icon="{$PrimaryMarkerIconUrl}"
             data-primary-marker-icon-width="{$PrimaryMarkerIconWidth}"
             data-primary-marker-icon-height="{$PrimaryMarkerIconHeight}"
             data-primary-marker-info-window="{$PrimaryMarkerInfoWindow.ATT}"
             data-additional-markers="{$AdditionalMarkers.ATT}"
             data-route="{$Route.ATT}"
             data-fit-bounds="<% if FitBounds %>true<% else %>false<% end_if %>">
        </div>
    </div>

    <div class="geocoding-map-controls">
        <% if ShowLayerSwitcher %>
        <select class="geocoding-map-layer-switcher"
                data-map-id="{$ID}_map"
                aria-label="{$LabelLayer}">
            <% loop LayerOptions %>
            <option value="{$Key}"<% if $Key == $Up.ActiveLayer %> selected="selected"<% end_if %>>{$Value}</option>
            <% end_loop %>
        </select>
        <% end_if %>
        <% if not IsReadonly %>
        <% if AllowClear %>
        <button type="button"
                class="geocoding-map-btn geocoding-map-btn--clear"
                data-map-id="{$ID}_map"
                title="{$LabelRemoveMarker}">
            ✕ {$LabelRemoveMarker}
        </button>
        <% end_if %>
        <% if AllowFullscreen %>
        <button type="button"
                class="geocoding-map-btn geocoding-map-btn--fullscreen"
                data-map-id="{$ID}_map"
                data-label-fullscreen="{$LabelFullscreen}"
                data-label-exit-fullscreen="{$LabelExitFullscreen}"
                title="{$LabelFullscreen}">
            ⛶ {$LabelFullscreen}
        </button>
        <% end_if %>
        <% end_if %>
    </div>
</div>

