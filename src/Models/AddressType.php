<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Models;

use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\View\Parsers\URLSegmentFilter;

/**
 * Represents a type/category for addresses (e.g. home, work, billing).
 *
 * @property string $Name
 * @property string $Ukey
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Models
 */
class AddressType extends DataObject
{
    /**
     * @inheritdoc
     */
    private static string $table_name = 'Geocoding_AddressType';

    /**
     * @inheritdoc
     */
    private static string $default_sort = 'Name ASC';

    /**
     * @inheritdoc
     */
    private static string $general_search_field = 'Name';

    /**
     * Default address types to create during dev/build.
     *
     * @config
     */
    private static array $default_tags = [];

    /**
     * @inheritdoc
     */
    private static array $db = [
        'Name' => 'Varchar(100)',
        'Ukey' => 'Varchar(100)',
    ];

    /**
     * @inheritdoc
     */
    private static array $summary_fields = [
        'Name',
        'Created',
        'LastEdited',
    ];

    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Name']       = _t(__CLASS__ . '.NAME', 'Name');
        $labels['Ukey']       = _t(__CLASS__ . '.UKEY', 'Unique key');
        $labels['Addresses']  = _t(__CLASS__ . '.ADDRESSES', 'Assigned addresses');
        $labels['Created']    = _t('Clesson\Silverstripe\Geocoding\Common.CREATED', 'Created');
        $labels['LastEdited'] = _t('Clesson\Silverstripe\Geocoding\Common.LAST_EDITED', 'Last edited');
        $labels['ID']         = _t('Clesson\Silverstripe\Geocoding\Common.ID', 'ID');

        return $labels;
    }

    /**
     * @inheritdoc
     */
    public function getCMSFields(): FieldList
    {
        $fields = FieldList::create();

        /** @var TextField $nameField */
        $nameField = TextField::create('Name', $this->fieldLabel('Name'));
        $fields->add($nameField);

        /** @var TextField $ukeyField */
        $ukeyField = TextField::create('Ukey', $this->fieldLabel('Ukey'));
        $fields->add($ukeyField);

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if (trim((string) $this->Name) === '') {
            $result->addError(_t(
                Form::class . '.FIELDISREQUIRED',
                '{name} is required',
                ['name' => $this->fieldLabel('Name')]
            ));
        }

        $filteredUkey = static::normalize_ukey($this->Ukey);

        if ($filteredUkey === '') {
            $result->addError(_t(
                Form::class . '.FIELDISREQUIRED',
                '{name} is required',
                ['name' => $this->fieldLabel('Ukey')]
            ));
        } else {
            $existingTag = static::get_by_ukey($filteredUkey);
            if ($existingTag && $existingTag->ID !== $this->ID) {
                $result->addError(_t(
                    Form::class . '.FIELDMUSTBEUNIQUE',
                    '{name} must be unique',
                    ['name' => $this->fieldLabel('Ukey')]
                ));
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();
        $this->Ukey = static::normalize_ukey($this->Ukey);
    }

    /**
     * Creates default records during dev/build.
     *
     * @inheritdoc
     */
    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();

        if (static::class !== AddressType::class) {
            return;
        }

        $defaultRecords = $this->config()->get('default_tags');

        foreach ($defaultRecords as $ukey => $name) {
            $ukey = static::normalize_ukey($ukey);
            $tag = static::get_by_ukey($ukey);

            if ($tag) {
                continue;
            }

            $tag = AddressType::create();
            $tag->Ukey = $ukey;
            $tag->Name = $name;
            $validationResult = $tag->validate();

            if (!$validationResult->isValid()) {
                DB::alteration_message('Could not create default address type ' . $ukey, 'error');
                continue;
            }

            $tag->write();
            DB::alteration_message('AddressType ' . $tag->Ukey . ' created', 'created');
        }
    }

    /**
     * Returns an AddressType by its unique key.
     *
     * @param string $ukey
     * @return AddressType|null
     */
    public static function get_by_ukey(string $ukey): ?AddressType
    {
        $filteredUkey = static::normalize_ukey($ukey);

        return AddressType::get()->filter('Ukey', $filteredUkey)->first() ?: null;
    }

    /**
     * Normalises a Ukey by filtering and uppercasing it.
     *
     * @param string $ukey
     * @return string
     */
    public static function normalize_ukey(string $ukey): string
    {
        $filter = URLSegmentFilter::create();
        $sanitizedUkey = $filter->filter($ukey);

        return strtoupper(trim($sanitizedUkey, '_-'));
    }
}

