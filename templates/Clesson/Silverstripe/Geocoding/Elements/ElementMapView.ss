$MapAssets
<div class="element-map-view"
     style="height:{$EffectiveMapHeight}px">
    <div class="geocoding-map-container element-map-view__map"
         data-provider="$ProviderKey"
         data-provider-config="$ProviderConfigJson.ATT"
         data-latitude="$CenterLatitude"
         data-longitude="$CenterLongitude"
         data-has-marker="false"
         data-zoom="$EffectiveZoom"
         data-zoom-with-marker="$EffectiveZoom"
         data-latitude-field-id=""
         data-longitude-field-id=""
         data-readonly="true"
         data-allow-place-marker="false"
         data-allow-clear="false"
         data-allow-fullscreen="false"
         data-active-layer=""
         data-primary-marker-icon=""
         data-primary-marker-icon-width="0"
         data-primary-marker-icon-height="0"
         data-primary-marker-info-window=""
         data-additional-markers="$MarkersJson.ATT"
         data-route="null"
         data-fit-bounds="<% if $FitBounds %>true<% else %>false<% end_if %>"
         data-show-zoom-control="<% if $ShowZoomControl %>true<% else %>false<% end_if %>"
         data-show-fullscreen-control="<% if $ShowFullscreenControl %>true<% else %>false<% end_if %>"
         data-show-scale-control="<% if $ShowScaleControl %>true<% else %>false<% end_if %>"
         style="width:100%;height:100%">
    </div>
</div>
<script>
(function () {
    function initElementMap() {
        var containers = document.querySelectorAll('.element-map-view .geocoding-map-container');
        containers.forEach(function (el) {
            if (el.dataset.initialized === 'true') return;
            var provider = el.dataset.provider;
            if (window.GeocodingMapUtils) {
                window.GeocodingMapUtils.initializeMap(el, provider);
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initElementMap);
    } else {
        initElementMap();
    }
})();
</script>

