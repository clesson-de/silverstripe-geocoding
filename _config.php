<?php

use SilverStripe\Forms\HTMLEditor\HTMLEditorConfig;

/**
 * HTMLEditorConfig for ElementMapViewAddress.InfoWindowText
 *
 * A minimal editor for map marker info windows — only basic formatting,
 * links, and lists are allowed. No images, tables or media.
 */
$infoWindowConfig = HTMLEditorConfig::get('ElementMapViewAddress_InfoWindowText_EditorConfig');
$infoWindowConfig->setOptions([
    'friendly_name'            => 'Map Info Window',
    'body_class'               => 'typography',
    'contextmenu'              => false,
    'valid_elements'           => 'p,br,strong/b,em/i,a[href|target|title],ul,ol,li,span[class]',
    'valid_styles'             => [],
]);
$infoWindowConfig->setButtonsForLine(1, [
    'bold',
    'italic',
    'bullist',
    'numlist',
    'sslink',
    'unlink',
    'removeformat',
]);
$infoWindowConfig->setButtonsForLine(2, []);
$infoWindowConfig->setButtonsForLine(3, []);
