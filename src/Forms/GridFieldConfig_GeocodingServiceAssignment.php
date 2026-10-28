<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Forms;

use Clesson\Silverstripe\Geocoding\Models\GeocodingService;
use Clesson\Silverstripe\Forms\GridField\GridFieldAddExistingSelect;
use Clesson\Silverstripe\Forms\GridField\GridFieldAddNewDropdown;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\ORM\FieldType\DBField;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * GridField configuration for managing GeocodingService assignments in SiteConfig.
 *
 * Uses GridFieldAddNewDropdown to select service types (OpenStreetMap, Google)
 * and GridFieldOrderableRows for drag-and-drop priority sorting.
 *
 * Designed for use with ManyManyThroughList relations where the join table
 * contains a 'Sort' field.
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Forms
 */
class GridFieldConfig_GeocodingServiceAssignment extends GridFieldConfig
{

    /**
     * Builds the GridField configuration with all required components.
     *
     * GridFieldOrderableRows works automatically with ManyManyThroughList
     * when the join table has a 'Sort' field. No explicit field name needed.
     */
    public function __construct()
    {
        parent::__construct();

        /** @var GridFieldAddNewDropdown $addNewMultiClassButton */
        $addNewMultiClassButton = GridFieldAddNewDropdown::create();

        $classes = [];
        foreach (ClassInfo::subclassesFor(GeocodingService::class, false) as $class) {
            $classes[$class] = singleton($class)->i18n_singular_name();
        }
        $addNewMultiClassButton->setClasses($classes);

        /** @var GridFieldDataColumns $dataColumns */
        $dataColumns = GridFieldDataColumns::create();
        $dataColumns->setDisplayFields([
            'Name'       => [
                'title'    => _t(GeocodingService::class . '.NAME', 'Name'),
                'callback' => function (GeocodingService $record): DBField {
                    return DBField::create_field('Varchar', $record->Name);
                },
            ],
            'Created'    => [
                'title'    => _t('Clesson\\Silverstripe\\Geocoding\\Common.CREATED', 'Created'),
                'callback' => function (GeocodingService $record): DBField {
                    return DBField::create_field('DBDatetime', $record->Created);
                },
            ],
            'LastEdited' => [
                'title'    => _t('Clesson\\Silverstripe\\Geocoding\\Common.LAST_EDITED', 'Last edited'),
                'callback' => function (GeocodingService $record): DBField {
                    return DBField::create_field('DBDatetime', $record->LastEdited);
                },
            ],
        ]);

        /** @var GridFieldAddExistingSelect $addExisting */
        $addExisting = GridFieldAddExistingSelect::create();
        $addExisting->setSearchFields(['Name']);
        $addExisting->setResultsFormat('$Name');

        $this->addComponent($addExisting);
        $this->addComponent($addNewMultiClassButton);
        $this->addComponent(GridFieldOrderableRows::create('Sort'));
        $this->addComponent($dataColumns);
        $this->addComponent(GridFieldEditButton::create());
        $this->addComponent(GridFieldDeleteAction::create());
        $this->addComponent(GridFieldDetailForm::create());
    }

}
