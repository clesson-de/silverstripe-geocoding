<?php

namespace Clesson\Silverstripe\Geocoding\Models;

use CommerceGuys\Addressing\Address as CommerceAddress;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use Clesson\Silverstripe\Autocomplete\Forms\AutocompleteField;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;
use Throwable;
use Psr\Log\LoggerInterface;

/**
 * Represents a physical address.
 *
 * @property string $Name
 * @property string $AddressLine1
 * @property string $AddressLine2
 * @property string $PostalCode
 * @property string $City
 * @property string $Region
 * @property string $CountryCode
 * @property string $Summary
 * @property-read string $Country The full localised country name derived from CountryCode.
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Models
 */
class Address extends DataObject
{
    /**
     * @inheritdoc
     */
    private static $table_name = 'Geocoding_Address';

    /**
     * @inheritdoc
     */
    private static $default_sort = 'CountryCode ASC, Region ASC, City ASC, PostalCode ASC, AddressLine1 ASC';

    /**
     * @inheritdoc
     */
    private static $general_search_field = 'Name';

    /**
     * @inheritdoc
     */
    private static $db = [
        'Name'         => 'Varchar(255)',
        'AddressLine1' => 'Varchar(150)',
        'AddressLine2' => 'Varchar(150)',
        'PostalCode'   => 'Varchar(20)',
        'City'         => 'Varchar(100)',
        'Region'       => 'Varchar(100)',
        'CountryCode'  => 'Varchar(2)',
        'Summary'      => 'Text',
    ];

    /**
     * @inheritdoc
     */
    private static $summary_fields = [
        'Summary',
        'Created',
        'LastEdited',
    ];

    /**
     * @inheritdoc
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Name']         = _t(__CLASS__ . '.NAME', 'Label');
        $labels['AddressLine1'] = _t(__CLASS__ . '.ADDRESS_LINE_1', 'Address line 1');
        $labels['AddressLine2'] = _t(__CLASS__ . '.ADDRESS_LINE_2', 'Address line 2');
        $labels['City']         = _t(__CLASS__ . '.CITY', 'City');
        $labels['PostalCode']   = _t(__CLASS__ . '.POSTAL_CODE', 'Postal code');
        $labels['Region']       = _t(__CLASS__ . '.REGION', 'Region');
        $labels['CountryCode']  = _t(__CLASS__ . '.COUNTRY_CODE', 'Country');
        $labels['Country']      = _t(__CLASS__ . '.COUNTRY_CODE', 'Country');
        $labels['Summary']      = _t(__CLASS__ . '.SUMMARY', 'Full address');
        $labels['Created']      = _t('Clesson\Silverstripe\Geocoding\Common.CREATED', 'Created');
        $labels['LastEdited']   = _t('Clesson\Silverstripe\Geocoding\Common.LAST_EDITED', 'Last edited');
        $labels['ID']           = _t('Clesson\Silverstripe\Geocoding\Common.ID', 'ID');

        return $labels;
    }

    /**
     * Returns the full country name based on the CountryCode.
     *
     * @return string|null The full country name or null if CountryCode is not set.
     */
    public function getCountry(): ?string
    {
        $countryCode = strtoupper(trim((string) $this->CountryCode));

        if ($countryCode === '') {
            return null;
        }

        $locale = strtolower(explode('_', i18n::get_locale() ?: 'en')[0]);
        $countryRepository = new CountryRepository();
        $country = $countryRepository->get($countryCode, $locale);

        return $country->getName();
    }

    /**
     * @inheritdoc
     */
    public function validate(): ValidationResult
    {
        return parent::validate();
    }

    /**
     * @inheritdoc
     */
    public function populateDefaults(): void
    {
        parent::populateDefaults();
    }

    /**
     * @inheritdoc
     */
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->removeByName([
            'Name',
            'AddressLine1',
            'AddressLine2',
            'PostalCode',
            'City',
            'Region',
            'CountryCode',
            'Summary',
        ]);

        /** @var AutocompleteField $nameField */
        $nameField = AutocompleteField::create('Name', $this->fieldLabel('Name'));
        $nameField->setSourceModel(self::class, 'Name');
        $fields->addFieldToTab('Root.Main', $nameField);

        /** @var AutocompleteField $addressLine1Field */
        $addressLine1Field = AutocompleteField::create('AddressLine1', $this->fieldLabel('AddressLine1'));
        $addressLine1Field->setSourceModel(self::class, 'AddressLine1');
        $fields->addFieldToTab('Root.Main', $addressLine1Field);

        /** @var AutocompleteField $addressLine2Field */
        $addressLine2Field = AutocompleteField::create('AddressLine2', $this->fieldLabel('AddressLine2'));
        $addressLine2Field->setSourceModel(self::class, 'AddressLine2');
        $fields->addFieldToTab('Root.Main', $addressLine2Field);

        /** @var DropdownField $countryCodeField */
        $countryCodeField = DropdownField::create('CountryCode', $this->fieldLabel('CountryCode'), static::getCountryOptions());
        $countryCodeField->setEmptyString('');
        $fields->addFieldToTab('Root.Main', $countryCodeField);

        $subdivisionOptions = static::getSubdivisionOptions($this->CountryCode ?? '');

        if (!empty($subdivisionOptions)) {
            /** @var DropdownField $regionField */
            $regionField = DropdownField::create('Region', $this->fieldLabel('Region'), $subdivisionOptions);
            $regionField->setEmptyString(' ');
            $regionField->addExtraClass('address-region-field');
            $regionField->setAttribute('data-depends-on', 'CountryCode');
            $fields->addFieldToTab('Root.Main', $regionField);
        } else {
            /** @var AutocompleteField $regionField */
            $regionField = AutocompleteField::create('Region', $this->fieldLabel('Region'));
            $regionField->setSourceModel(self::class, 'Region');
            $regionField->addExtraClass('address-region-field-text');
            $regionField->setAttribute('data-depends-on', 'CountryCode');
            $fields->addFieldToTab('Root.Main', $regionField);
        }

        /** @var AutocompleteField $postalCodeField */
        $postalCodeField = AutocompleteField::create('PostalCode', '');
        $postalCodeField->setSourceModel(self::class, 'PostalCode');
        $postalCodeField->setAttribute('placeholder', $this->fieldLabel('PostalCode'));

        /** @var AutocompleteField $cityField */
        $cityField = AutocompleteField::create('City', '');
        $cityField->setSourceModel(self::class, 'City');
        $cityField->setAttribute('placeholder', $this->fieldLabel('City'));

        /** @var FieldGroup $postalCodeCityGroup */
        $postalCodeCityGroup = FieldGroup::create(
            $this->fieldLabel('PostalCode') . ' & ' . $this->fieldLabel('City'),
            $postalCodeField,
            $cityField
        );
        $fields->addFieldToTab('Root.Main', $postalCodeCityGroup);

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        $summary = '';

        if ($this->CountryCode) {
            $addressFormatRepository = new AddressFormatRepository();
            $countryRepository = new CountryRepository();
            $subdivisionRepository = new SubdivisionRepository();
            $formatter = new DefaultFormatter(
                $addressFormatRepository,
                $countryRepository,
                $subdivisionRepository
            );
            $address = new CommerceAddress(
                $this->CountryCode,
                $this->Region ?? '',
                $this->City ?? '',
                '',
                $this->PostalCode ?? '',
                '',
                $this->AddressLine1 ?? '',
                $this->AddressLine2 ?? '',
                '',
                $this->Name ?? ''
            );
            $summary = $formatter->format(
                $address,
                [
                    'html'   => false,
                    'locale' => i18n::get_locale(),
                ]
            );
            $summary = str_replace(PHP_EOL, ', ', trim($summary));
        }

        $this->Summary = $summary;
    }

    /**
     * Returns all countries as an associative array of ISO code → localised name,
     * sorted alphabetically by name.
     *
     * @return array<string, string>
     */
    public static function getCountryOptions(): array
    {
        $repo = new CountryRepository();
        $locale = strtolower(explode('_', i18n::get_locale() ?: 'en')[0]);
        $list = [];

        foreach ($repo->getAll($locale) as $code => $country) {
            $list[$code] = $country->getName() ?: $code;
        }

        natcasesort($list);

        return $list;
    }

    /**
     * Returns subdivisions (states/regions) for a given country as an associative array,
     * sorted alphabetically.
     *
     * @param string      $countryIso ISO 3166-1 alpha-2 country code.
     * @param string|null $locale     Optional locale override.
     * @return array<string, string>
     */
    public static function getSubdivisionOptions(string $countryIso, ?string $locale = null): array
    {
        $countryIso = strtoupper(trim((string) $countryIso));

        if ($countryIso === '') {
            return [];
        }

        if ($locale) {
            $locale = strtolower(str_replace('_', '-', $locale));
            $locale = explode('-', $locale)[0];
        } else {
            $currentLocale = i18n::get_locale() ?: 'en';
            $locale = strtolower(explode('_', $currentLocale)[0]);
        }

        $repo = new SubdivisionRepository();
        $list = [];

        try {
            $list = $repo->getList([$countryIso], $locale);

            if (empty($list)) {
                $list = $repo->getList([$countryIso], 'en');
            }

            if (!empty($list)) {
                natcasesort($list);
            }
        } catch (Throwable $e) {
            Injector::inst()->get(LoggerInterface::class)->error(
                'Failed to load subdivisions for country ' . $countryIso,
                [
                    'exception' => $e->getMessage(),
                    'locale'    => $locale,
                    'trace'     => $e->getTraceAsString(),
                ]
            );
        }

        return $list;
    }

    /**
     * Returns a human-readable title composed of the address components.
     *
     * @return string
     */
    public function getTitle(): string
    {
        $text = [];

        if ($this->Name) {
            $text[] = $this->Name;
        }

        if ($this->AddressLine1) {
            $text[] = $this->AddressLine1;
        }

        if ($this->AddressLine2) {
            $text[] = $this->AddressLine2;
        }

        if ($this->PostalCode && $this->City) {
            $text[] = $this->PostalCode . ' ' . $this->City;
        } elseif ($this->City) {
            $text[] = $this->City;
        }

        if ($this->Region) {
            $text[] = $this->Region;
        }

        if ($country = $this->Country) {
            $text[] = $country;
        }

        return implode(', ', $text);
    }
}

