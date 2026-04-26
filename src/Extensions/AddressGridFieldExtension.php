<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Extensions;

use Clesson\Silverstripe\Geocoding\Helpers\MapThumbnailHelper;
use Clesson\Silverstripe\Geocoding\ORM\DBGeoCoordinate;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Adds a static map thumbnail column to Address GridField configurations.
 *
 * Applied via YAML to both GridFieldConfig_AddressesInContactManager and
 * GridFieldConfig_AddressesInContact. The thumbnail is only shown when the
 * Address record has geo coordinates — otherwise the cell is left empty.
 *
 * The thumbnail is rendered by StaticMapController using OSM tiles + PHP GD,
 * so no external static map service is required.
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Extensions
 */
class AddressGridFieldExtension extends Extension
{
    use Configurable;

    /**
     * Thumbnail width in pixels.
     *
     * @config
     */
    private static int $thumbnail_width = 120;

    /**
     * Thumbnail height in pixels.
     *
     * @config
     */
    private static int $thumbnail_height = 80;

    /**
     * Thumbnail zoom level.
     *
     * @config
     */
    private static int $thumbnail_zoom = 14;

    /**
     * Tile layer key used for the thumbnail.
     * Must match a key in StaticMapController.layers config.
     * Leave empty to use the default tile layer.
     *
     * @config
     */
    private static string $thumbnail_layer = 'topo';

    /**
     * Injects a map thumbnail column into the GridFieldDataColumns component.
     *
     * Note: GridFieldEditableColumns extends GridFieldDataColumns, so
     * getComponentByType(GridFieldDataColumns::class) would match it too.
     * We explicitly exclude GridFieldEditableColumns and handle it separately —
     * its callback MUST return a FormField, so we use a LiteralField wrapper.
     *
     * Called automatically by GridFieldConfig::extend('updateConfig').
     *
     * @return void
     */
    public function updateConfig(): void
    {
        /** @var \SilverStripe\Forms\GridField\GridFieldConfig $owner */
        $owner = $this->getOwner();

        $width  = (int) self::config()->get('thumbnail_width');
        $height = (int) self::config()->get('thumbnail_height');
        $zoom   = (int) self::config()->get('thumbnail_zoom');
        $layer  = (string) self::config()->get('thumbnail_layer');

        // Build the raw HTML for a given record
        $buildHtml = function ($record) use ($width, $height, $zoom, $layer): string {
            /** @var DBGeoCoordinate $coord */
            $coord = $record->dbObject('GeoCoordinates');

            if (!$coord || !$coord->exists()) {
                return '';
            }

            $url     = MapThumbnailHelper::urlFromCoordinate($coord, $zoom, $width, $height, $layer);
            $safeUrl = htmlspecialchars($url, ENT_QUOTES);

            return '<img src="' . $safeUrl . '" width="' . $width . '" height="' . $height
                . '" alt="" style="display:block;border-radius:3px;" loading="lazy" />';
        };

        // --- GridFieldDataColumns (pure, not editable) ---
        // Find the component by iterating manually so we skip GridFieldEditableColumns
        $dataColumns = null;
        foreach ($owner->getComponents() as $component) {
            if (get_class($component) === GridFieldDataColumns::class) {
                $dataColumns = $component;
                break;
            }
        }

        if ($dataColumns) {
            $existing = $dataColumns->getDisplayFields(null) ?? [];

            $dataColumns->setDisplayFields(array_merge(
                [
                    'GeoCoordinates' => [
                        'title'    => '',
                        'callback' => function ($record) use ($buildHtml) {
                            return DBField::create_field('HTMLText', $buildHtml($record));
                        },
                    ],
                ],
                $existing
            ));
        }

        // --- GridFieldEditableColumns ---
        // Do NOT add GeoCoordinates here. GridFieldAddNewInlineButton calls
        // $fields->dataFieldByName($column) for every column registered in
        // GridFieldEditableColumns. LiteralField is not a data field, so
        // dataFieldByName() returns null → setName() on null → Exception.
        // The thumbnail is shown via GridFieldDataColumns only (see above).
    }

}

