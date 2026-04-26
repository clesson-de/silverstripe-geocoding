<?php

declare(strict_types=1);

namespace Clesson\Silverstripe\Geocoding\Forms;

use Clesson\Silverstripe\Geocoding\ORM\DBGeoCoordinate;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\NumericField;
use SilverStripe\ORM\DataObjectInterface;

/**
 * A form field for editing a DBGeoCoordinate composite field.
 *
 * Renders two NumericField inputs — one for latitude and one for longitude.
 * Values are passed as an array with keys 'Latitude' and 'Longitude' and
 * saved back into the composite DB columns.
 *
 * Usage:
 * ```php
 * GeoCoordinateField::create('GeoCoordinates', $this->fieldLabel('GeoCoordinates'))
 * ```
 *
 * @package Clesson\Silverstripe\Geocoding
 * @subpackage Forms
 */
class GeoCoordinateField extends FormField
{

    /**
     * Child field for latitude input.
     */
    protected NumericField $fieldLatitude;

    /**
     * Child field for longitude input.
     */
    protected NumericField $fieldLongitude;

    /**
     * Builds the composite field with two NumericField children.
     *
     * @param string      $name  Field name, must match the composite DB field name.
     * @param string|null $title Optional label. Defaults to the field name.
     * @param mixed       $value Initial value.
     */
    public function __construct(string $name, ?string $title = null, mixed $value = null)
    {
        $this->setName($name);
        $this->buildLatitudeField();
        $this->buildLongitudeField();

        parent::__construct($name, $title, $value);
    }

    /**
     * Creates a deep clone including the child fields.
     *
     * @return void
     */
    public function __clone()
    {
        $this->fieldLatitude  = clone $this->fieldLatitude;
        $this->fieldLongitude = clone $this->fieldLongitude;
    }

    /**
     * Returns the latitude child field.
     *
     * @return NumericField
     */
    public function getLatitudeField(): NumericField
    {
        return $this->fieldLatitude;
    }

    /**
     * Returns the longitude child field.
     *
     * @return NumericField
     */
    public function getLongitudeField(): NumericField
    {
        return $this->fieldLongitude;
    }

    /**
     * Builds the latitude NumericField with 7 decimal places.
     *
     * @return void
     */
    protected function buildLatitudeField(): void
    {
        $this->fieldLatitude = NumericField::create(
            $this->getName() . '[Latitude]',
            _t(__CLASS__ . '.LATITUDE', 'Latitude')
        )->setScale(7);
    }

    /**
     * Builds the longitude NumericField with 7 decimal places.
     *
     * @return void
     */
    protected function buildLongitudeField(): void
    {
        $this->fieldLongitude = NumericField::create(
            $this->getName() . '[Longitude]',
            _t(__CLASS__ . '.LONGITUDE', 'Longitude')
        )->setScale(7);
    }

    /**
     * Sets the value from a submitted form array, a DBGeoCoordinate instance,
     * or an associative array with keys 'Latitude' and 'Longitude'.
     *
     * Example submitted value: ['Latitude' => '52.5163', 'Longitude' => '13.3777']
     *
     * @param mixed      $value
     * @param mixed|null $data
     * @return static
     */
    public function setValue($value, $data = null): static
    {
        if ($value instanceof DBGeoCoordinate) {
            $this->fieldLatitude->setValue($value->getLatitude());
            $this->fieldLongitude->setValue($value->getLongitude());
        } elseif (is_array($value)) {
            $this->fieldLatitude->setValue($value['Latitude'] ?? null);
            $this->fieldLongitude->setValue($value['Longitude'] ?? null);
        } else {
            $this->fieldLatitude->setValue(null);
            $this->fieldLongitude->setValue(null);
        }

        $this->value = $this->dataValue();
        return $this;
    }

    /**
     * Sets the value from a submitted form POST array.
     *
     * Example: ['Latitude' => '52.5163000', 'Longitude' => '13.3777000']
     *
     * @param mixed      $value
     * @param mixed|null $data
     * @return static
     */
    public function setSubmittedValue($value, $data = null): static
    {
        if (is_array($value)) {
            $this->fieldLatitude->setSubmittedValue($value['Latitude'] ?? null, $value);
            $this->fieldLongitude->setSubmittedValue($value['Longitude'] ?? null, $value);
        } else {
            $this->fieldLatitude->setSubmittedValue(null);
            $this->fieldLongitude->setSubmittedValue(null);
        }

        $this->value = $this->dataValue();
        return $this;
    }

    /**
     * Returns the current value as an associative array with Latitude and Longitude keys.
     *
     * @return array{Latitude: float|null, Longitude: float|null}
     */
    public function dataValue(): array
    {
        return [
            'Latitude'  => $this->fieldLatitude->dataValue() !== '' ? (float) $this->fieldLatitude->dataValue() : null,
            'Longitude' => $this->fieldLongitude->dataValue() !== '' ? (float) $this->fieldLongitude->dataValue() : null,
        ];
    }

    /**
     * Saves the latitude and longitude values into the composite DB columns of the data object.
     *
     * Example: sets GeoCoordinatesLatitude and GeoCoordinatesLongitude on the record.
     *
     * @param DataObjectInterface $dataObject
     * @return void
     */
    public function saveInto(DataObjectInterface $record): void
    {
        $name = $this->getName();

        $record->{$name . 'Latitude'}  = $this->fieldLatitude->dataValue() !== ''
            ? (float) $this->fieldLatitude->dataValue()
            : null;

        $record->{$name . 'Longitude'} = $this->fieldLongitude->dataValue() !== ''
            ? (float) $this->fieldLongitude->dataValue()
            : null;
    }

    /**
     * Propagates readonly state to both child fields.
     *
     * @param bool $bool
     * @return static
     */
    public function setReadonly($readonly): static
    {
        parent::setReadonly($readonly);
        $this->fieldLatitude->setReadonly($readonly);
        $this->fieldLongitude->setReadonly($readonly);
        return $this;
    }

    /**
     * Propagates disabled state to both child fields.
     *
     * @param bool $disabled
     * @return static
     */
    public function setDisabled($disabled): static
    {
        parent::setDisabled($disabled);
        $this->fieldLatitude->setDisabled($disabled);
        $this->fieldLongitude->setDisabled($disabled);
        return $this;
    }

    /**
     * Returns a FieldList containing the two child fields for rendering.
     *
     * @return array<NumericField>
     */
    public function getChildren(): array
    {
        return [$this->fieldLatitude, $this->fieldLongitude];
    }

    /**
     * Returns a readonly version of this field.
     *
     * @return static
     */
    public function performReadonlyTransformation(): static
    {
        $clone = clone $this;
        $clone->setReadonly(true);
        return $clone;
    }

    /**
     * Renders the field using the GeoCoordinateField template.
     *
     * Delegates rendering to the two child fields via SmallFieldHolder.
     *
     * @param array $properties
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    public function Field($properties = [])
    {
        return $this->renderWith(
            $this->getTemplates(),
            array_merge($properties, [
                'LatitudeField'  => $this->fieldLatitude,
                'LongitudeField' => $this->fieldLongitude,
            ])
        );
    }

}

