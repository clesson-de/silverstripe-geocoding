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
     * Injects the address grid CSS into the CMS.
     *
     * @return void
     */
    public function init(): void
    {
        Requirements::css('silverstripe-geocoding/client/admin/dist/address-grid.css');
    }

}

