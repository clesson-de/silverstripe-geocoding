<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

/**
 * Loads geocoding admin CSS into every CMS page via LeftAndMain.
 *
 * This ensures that the address thumbnail column in GridFields is styled
 * correctly regardless of which CMS section is active.
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Extensions
 */
class LeftAndMainExtension extends Extension
{

    /**
     * Loads address grid and settings card styles before CMS navigation.
     *
     * @return void
     */
    public function onAfterInit(): void
    {
        Requirements::css('clesson-de/silverstripe-geocoding:client/admin/dist/address-grid.css');
        Requirements::css('clesson-de/silverstripe-geocoding:client/admin/dist/geocoding-config.css');
    }

}
