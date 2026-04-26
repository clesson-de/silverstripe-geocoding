<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Join table for SiteConfig → GeocodingService (reverseGeocode method).
 *
 * Links a GeocodingService to SiteConfig for the reverseGeocode method,
 * storing the daily quota limit and sort order for priority.
 *
 * @property int $Quota
 * @property int $Sort
 * @property int $SiteConfigID
 * @property int $GeocodingServiceID
 * @method SiteConfig SiteConfig()
 * @method GeocodingService GeocodingService()
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Models
 */
class SiteConfigReverseGeocodeService extends DataObject
{

    /**
     * @inheritdoc
     */
    private static string $table_name = 'Geocoding_SiteConfig_ReverseGeocodeService';

    /**
     * @inheritdoc
     */
    private static array $db = [
        'Quota' => 'Int',
        'Sort'  => 'Int',
    ];

    /**
     * @inheritdoc
     */
    private static array $has_one = [
        'SiteConfig'       => SiteConfig::class,
        'GeocodingService' => GeocodingService::class,
    ];

    /**
     * @inheritdoc
     */
    private static string $default_sort = '"Geocoding_SiteConfig_ReverseGeocodeService"."Sort" ASC';


    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);

        $labels['Quota']            = _t(__CLASS__ . '.QUOTA', 'Quota (calls per day)');
        $labels['Sort']             = _t(__CLASS__ . '.SORT', 'Priority');
        $labels['GeocodingService'] = _t(__CLASS__ . '.GEOCODING_SERVICE', 'Service');

        return $labels;
    }

    /**
     * Only administrators may manage service assignments.
     *
     * @param Member|null $member
     * @param array       $context
     * @return bool
     */
    public function canCreate($member = null, $context = []): bool
    {
        return Permission::check('ADMIN', 'any', $member);
    }

    /**
     * @inheritdoc
     */
    public function canEdit($member = null): bool
    {
        return Permission::check('ADMIN', 'any', $member);
    }

    /**
     * @inheritdoc
     */
    public function canDelete($member = null): bool
    {
        return Permission::check('ADMIN', 'any', $member);
    }

    /**
     * @inheritdoc
     */
    public function canView($member = null): bool
    {
        return Permission::check('ADMIN', 'any', $member);
    }

}

