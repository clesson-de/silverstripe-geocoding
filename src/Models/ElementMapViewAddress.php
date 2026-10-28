<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Models;

use Clesson\Silverstripe\Geocoding\Elements\ElementMapView;
use Clesson\Silverstripe\Geocoding\Forms\AddressField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Represents a single address marker entry within an ElementMapView.
 *
 * Links an Address to the map element and optionally provides a custom
 * marker image and an info window text shown when the marker is clicked.
 *
 * @property string $InfoWindowText
 * @property bool   $Active
 * @property bool   $ShowMarker
 * @property int    $Sort
 * @property int    $ElementID
 * @property int    $AddressID
 * @property int    $MarkerImageID
 *
 * @method ElementMapView Element()
 * @method Address         Address()
 * @method Image           MarkerImage()
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Models
 */
class ElementMapViewAddress extends DataObject
{
    /**
     * @inheritdoc
     */
    private static string $table_name = 'Geocoding_ElementMapViewAddress';

    /**
     * @inheritdoc
     */
    private static string $default_sort = '"Sort" ASC';

    /**
     * @inheritdoc
     */
    private static array $db = [
        'InfoWindowText' => 'HTMLText',
        'Active'         => 'Boolean(1)',
        'ShowMarker'     => 'Boolean(1)',
        'Sort'           => 'Int',
    ];

    /**
     * @inheritdoc
     */
    private static array $has_one = [
        'Element'     => ElementMapView::class,
        'Address'     => Address::class,
        'MarkerImage' => Image::class,
    ];

    /**
     * @inheritdoc
     */
    private static array $owns = [
        'MarkerImage',
    ];

    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['InfoWindowText'] = _t(__CLASS__ . '.INFO_WINDOW_TEXT', 'Info window text');
        $labels['Active']         = _t(__CLASS__ . '.ACTIVE', 'Active');
        $labels['ShowMarker']     = _t(__CLASS__ . '.SHOW_MARKER', 'Show marker');
        $labels['Sort']           = _t(__CLASS__ . '.SORT', 'Sort order');
        $labels['Address']        = _t(__CLASS__ . '.ADDRESS', 'Address');
        $labels['MarkerImage']    = _t(__CLASS__ . '.MARKER_IMAGE', 'Marker image');
        $labels['Created']        = _t('Clesson\Silverstripe\Geocoding\Common.CREATED', 'Created');
        $labels['LastEdited']     = _t('Clesson\Silverstripe\Geocoding\Common.LAST_EDITED', 'Last edited');
        $labels['ID']             = _t('Clesson\Silverstripe\Geocoding\Common.ID', 'ID');
        return $labels;
    }

    /**
     * @inheritdoc
     */
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['ElementID', 'Sort', 'AddressID']);

        /** @var AddressField $addressField */
        $addressField = AddressField::create('AddressID', $this->fieldLabel('Address'));

        /** @var UploadField $markerImageField */
        $markerImageField = UploadField::create('MarkerImage', $this->fieldLabel('MarkerImage'));
        $markerImageField->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp']);
        $markerImageField->setFolderName('geocoding/marker-images');

        /** @var HTMLEditorField $infoWindowTextField */
        $infoWindowTextField = HTMLEditorField::create('InfoWindowText', $this->fieldLabel('InfoWindowText'));
        $infoWindowTextField->setEditorConfig('ElementMapViewAddress_InfoWindowText_EditorConfig');
        $infoWindowTextField->setRows(6);

        /** @var CheckboxField $activeField */
        $activeField = CheckboxField::create('Active', $this->fieldLabel('Active'));

        /** @var CheckboxField $showMarkerField */
        $showMarkerField = CheckboxField::create('ShowMarker', $this->fieldLabel('ShowMarker'));

        $fields->addFieldsToTab('Root.Main', [$addressField, $activeField, $showMarkerField, $markerImageField, $infoWindowTextField]);

        return $fields;
    }

    /**
     * Returns the address title for display in the GridField.
     *
     * @return string
     */
    public function getTitle(): string
    {
        $address = $this->Address();
        if ($address && $address->exists()) {
            return $address->getTitle() ?: (string) $address->Summary ?: '–';
        }

        return '–';
    }

    /**
     * Delegates create permission to the parent element.
     *
     * @param Member|null $member
     * @param array       $context
     * @return bool
     */
    public function canCreate($member = null, $context = []): bool
    {
        return $this->Element()->canEdit($member);
    }

    /**
     * Delegates edit permission to the parent element.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canEdit($member = null): bool
    {
        return $this->Element()->canEdit($member);
    }

    /**
     * Delegates delete permission to the parent element.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canDelete($member = null): bool
    {
        return $this->Element()->canEdit($member);
    }

    /**
     * Delegates view permission to the parent element.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canView($member = null): bool
    {
        return $this->Element()->canView($member);
    }
}






