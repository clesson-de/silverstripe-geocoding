<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Models;

use LeKoala\CmsActions\CustomAction;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * Abstract base model for a configured geocoding service backend.
 *
 * Concrete subclasses (e.g. OpenStreetMapService, GoogleService) extend this
 * class and add their service-specific fields. Silverstripe uses single-table
 * inheritance to store all subclass records in the same table.
 *
 * Services are assigned to specific methods (geocode, reverseGeocode, route)
 * via many_many relations on SiteConfig. Priority is determined by sort order,
 * and quota limits are stored in the many_many join table.
 *
 * @property string $Name
 * @property bool   $Active
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Models
 */
class GeocodingService extends DataObject
{

    /**
     * @inheritdoc
     */
    private static string $table_name = 'Geocoding_GeocodingService';

    /**
     * @inheritdoc
     */
    private static string $default_sort = 'Name ASC';

    /**
     * @inheritdoc
     */
    private static string $general_search_field = 'Name';

    /**
     * @inheritdoc
     */
    private static array $db = [
        'Name'   => 'Varchar',
        'Active' => 'Boolean',
    ];

    /**
     * Sets default values before the record is first saved.
     *
     * @return void
     */
    public function populateDefaults(): void
    {
        parent::populateDefaults();
        $this->Active = true;
    }

    /**
     * Validates required fields before writing.
     *
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if (!$this->Name) {
            $result->addError(
                _t(
                    Form::class . '.FIELDISREQUIRED',
                    '{name} is required',
                    ['name' => $this->fieldLabel('Name')]
                )
            );
        }

        return $result;
    }

    /**
     * Returns translated labels for all fields and relations.
     *
     * @param bool $includerelations
     * @return array<string, string>
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);

        $labels['ID']         = _t('Clesson\\Silverstripe\\Geocoding\\Common.ID', 'ID');
        $labels['Created']    = _t('Clesson\\Silverstripe\\Geocoding\\Common.CREATED', 'Created');
        $labels['LastEdited'] = _t('Clesson\\Silverstripe\\Geocoding\\Common.LAST_EDITED', 'Last edited');
        $labels['Name']       = _t(__CLASS__ . '.NAME', 'Name');
        $labels['Active']     = _t(__CLASS__ . '.ACTIVE', 'Active');

        return $labels;
    }

    /**
     * Returns the CMS edit form fields.
     *
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->removeByName(['Name', 'Active']);

        /** @var TextField $nameField */
        $nameField = TextField::create('Name', $this->fieldLabel('Name'));

        /** @var CheckboxField $activeField */
        $activeField = CheckboxField::create('Active', $this->fieldLabel('Active'));

        $fields->addFieldsToTab('Root.Main', [$nameField, $activeField]);

        return $fields;
    }

    /**
     * Adds a "Test connection" button to the CMS actions bar.
     *
     * The button is only shown for saved records (ID > 0).
     * The action is handled directly on this DataObject via doTestConnection().
     *
     * @return FieldList
     */
    public function getCMSActions(): FieldList
    {
        $actions = parent::getCMSActions();

        if ($this->isInDB()) {
            /** @var CustomAction $testAction */
            $testAction = CustomAction::create(
                'doTestConnection',
                _t(__CLASS__ . '.TEST_CONNECTION', 'Test connection')
            );
            $testAction->setButtonIcon('check-mark');
            $actions->push($testAction);
        }

        return $actions;
    }

    /**
     * CMS action handler: runs testConnection() and returns the result as a CMS toast message.
     *
     * Called by ActionsGridFieldItemRequest::doCustomAction() from lekoala/silverstripe-cms-actions.
     *
     * Returns a string message on success (green toast) or false on failure (red toast).
     *
     * @return string|false
     */
    public function doTestConnection(): string|false
    {
        $result = $this->testConnection();

        if ($result['success']) {
            return $result['message'];
        }

        // Throwing an exception shows the message as a red error toast
        throw new \Exception($result['message']);
    }

    /**
     * Tests whether this service's connection / credentials are working.
     *
     * Subclasses must override this method and perform a lightweight check
     * (e.g. a test geocode request) to verify that the service is reachable
     * and the API key, if any, is valid.
     *
     * Return array keys:
     * - success (bool)   — true if the connection test passed
     * - message (string) — human-readable result message
     *
     * Example:
     * ```php
     * return ['success' => true,  'message' => 'API key is valid.'];
     * return ['success' => false, 'message' => 'Invalid API key (HTTP 403).'];
     * ```
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        return [
            'success' => false,
            'message' => _t(__CLASS__ . '.TEST_NOT_IMPLEMENTED', 'Connection test is not implemented for this service type.'),
        ];
    }

    /**
     * Only administrators may create geocoding service records.
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
     * Only administrators may edit geocoding service records.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canEdit($member = null): bool
    {
        return Permission::check('ADMIN', 'any', $member);
    }

    /**
     * Only administrators may delete geocoding service records.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canDelete($member = null): bool
    {
        return Permission::check('ADMIN', 'any', $member);
    }

    /**
     * Only administrators may view geocoding service records in the CMS.
     *
     * @param Member|null $member
     * @return bool
     */
    public function canView($member = null): bool
    {
        return Permission::check('ADMIN', 'any', $member);
    }

}

