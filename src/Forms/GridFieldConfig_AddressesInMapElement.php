<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Forms;

use Clesson\Silverstripe\Geocoding\Models\ElementMapViewAddress;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * GridField configuration for managing address entries within an ElementMapView.
 *
 * Supports drag-and-drop ordering, add, edit and delete actions.
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Forms
 */
class GridFieldConfig_AddressesInMapElement extends GridFieldConfig
{
    /**
     * Sets up all components for the addresses GridField.
     */
    public function __construct()
    {
        parent::__construct();

        $this->addComponent(GridFieldButtonRow::create('before'));
        $this->addComponent(GridFieldToolbarHeader::create());
        $this->addComponent(GridFieldSortableHeader::create());
        $this->addComponent(GridFieldOrderableRows::create('Sort'));
        $this->addComponent($this->buildEditableColumns());
        /** @var GridFieldAddNewButton $addNewButton */
        $addNewButton = GridFieldAddNewButton::create('toolbar-header-right');
        $addNewButton->setButtonName(_t(ElementMapViewAddress::class . '.ADD_ADDRESS', 'Add address'));
        $this->addComponent($addNewButton);
        $this->addComponent(GridFieldEditButton::create());
        $this->addComponent(GridFieldDeleteAction::create());
        $this->addComponent(GridFieldDetailForm::create());
    }

    /**
     * Builds the editable columns component for inline editing.
     *
     * @return GridFieldEditableColumns
     */
    private function buildEditableColumns(): GridFieldEditableColumns
    {
        $editableColumns = GridFieldEditableColumns::create();
        $editableColumns->setDisplayFields([
            'AddressID' => [
                'title'    => _t(ElementMapViewAddress::class . '.ADDRESS', 'Address'),
                'callback' => static function ($record, $column, $grid): AddressField {
                    return AddressField::create('AddressID', '');
                },
            ],
            'ShowMarker' => [
                'title'    => _t(ElementMapViewAddress::class . '.SHOW_MARKER', 'Show marker'),
                'callback' => static function ($record, $column, $grid): CheckboxField {
                    return CheckboxField::create('ShowMarker', '');
                },
            ],
            'Active' => [
                'title'    => _t(ElementMapViewAddress::class . '.ACTIVE', 'Active'),
                'callback' => static function ($record, $column, $grid): CheckboxField {
                    return CheckboxField::create('Active', '');
                },
            ],
        ]);

        return $editableColumns;
    }

}

