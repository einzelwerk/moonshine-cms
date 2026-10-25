<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\OpenApi;

use Ewk\Cms\Blocks\Fields\BooleanField;
use Ewk\Cms\Blocks\Fields\ColorField;
use Ewk\Cms\Blocks\Fields\CoordinatesField;
use Ewk\Cms\Blocks\Fields\DateField;
use Ewk\Cms\Blocks\Fields\DateTimeField;
use Ewk\Cms\Blocks\Fields\EntityField;
use Ewk\Cms\Blocks\Fields\Field;
use Ewk\Cms\Blocks\Fields\GroupField;
use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Blocks\Fields\MediaField;
use Ewk\Cms\Blocks\Fields\MultiSelectField;
use Ewk\Cms\Blocks\Fields\NumberField;
use Ewk\Cms\Blocks\Fields\RepeaterField;
use Ewk\Cms\Blocks\Fields\RichTextField;
use Ewk\Cms\Blocks\Fields\SelectField;
use Ewk\Cms\Blocks\Fields\TextareaField;
use Ewk\Cms\Blocks\Fields\TextField;
use OpenApi\Attributes as OA;
use OpenApi\Undefined;

/**
 * One declared field of a block → one property of its payload schema.
 *
 * The mapping describes what actually leaves `Field::toPayload()`, which is why
 * it is not a table of `Field::rules()`: the rules guard what the editor saves,
 * this describes what the visitor reads. A translatable field has no trace here
 * either — the dictionary of languages never leaves the backend, the resolved
 * string does.
 */
final class FieldSchemaMapper
{
    public function property(Field $field): OA\Property
    {
        $property = $this->describe($field, $field->name());

        $help = $field->getHelp();

        if ($help !== null && Undefined::isDefault($property->description)) {
            $property->description = $help;
        }

        return $property;
    }

    private function describe(Field $field, string $name): OA\Property
    {
        return match (true) {
            // A text-like value is resolved through the fallback to the default
            // language and comes out a string every time, empty at worst; there
            // is no null to declare.
            $field instanceof RichTextField => new OA\Property(
                property: $name,
                description: 'HTML в локали запроса',
                type: 'string',
            ),
            $field instanceof TextField, $field instanceof TextareaField => new OA\Property(
                property: $name,
                type: 'string',
            ),
            $field instanceof ColorField => new OA\Property(
                property: $name,
                type: 'string',
                nullable: $this->nullable($field),
            ),
            $field instanceof DateField => new OA\Property(
                property: $name,
                type: 'string',
                format: 'date',
                nullable: $this->nullable($field),
            ),
            $field instanceof DateTimeField => new OA\Property(
                property: $name,
                type: 'string',
                format: 'date-time',
                nullable: $this->nullable($field),
            ),
            $field instanceof NumberField => $this->number($field, $name),
            $field instanceof BooleanField => new OA\Property(
                property: $name,
                type: 'boolean',
                nullable: $this->nullable($field),
            ),
            $field instanceof SelectField => new OA\Property(
                property: $name,
                type: 'string',
                enum: self::optionValues($field->getOptions(), $this->nullable($field)),
                nullable: $this->nullable($field),
            ),
            $field instanceof MultiSelectField => new OA\Property(
                property: $name,
                type: 'array',
                items: new OA\Items(type: 'string', enum: self::optionValues($field->getOptions(), false)),
                nullable: $this->nullable($field),
            ),
            // The core returns the stored reference, not a hydrated picture:
            // a resource that resolves it into `CmsMedia` describes its own
            // payload, see DescribesPayloadSchema.
            $field instanceof MediaField => $this->reference($name, CommonSchemas::MEDIA_REF),
            $field instanceof LinkField => $this->reference($name, CommonSchemas::LINK),
            $field instanceof RepeaterField => new OA\Property(
                property: $name,
                type: 'array',
                items: new OA\Items(
                    required: self::required($field->getFields()),
                    properties: $this->properties($field->getFields()) ?: null,
                    type: 'object',
                ),
            ),
            $field instanceof GroupField => new OA\Property(
                property: $name,
                required: self::required($field->getFields()),
                properties: $this->properties($field->getFields()) ?: null,
                type: 'object',
            ),
            $field instanceof CoordinatesField => new OA\Property(
                property: $name,
                required: ['lat', 'lng'],
                properties: [
                    new OA\Property(property: 'lat', type: 'number', format: 'float'),
                    new OA\Property(property: 'lng', type: 'number', format: 'float'),
                ],
                type: 'object',
                nullable: $this->nullable($field),
            ),
            $field instanceof EntityField => $this->entity($field, $name),
            // An unknown field type of a consuming application: a free object
            // beats a wrong type, and the generator must not refuse to
            // describe a contract because of one field it has not met.
            default => new OA\Property(
                property: $name,
                type: 'object',
                additionalProperties: true,
                nullable: true,
            ),
        };
    }

    /**
     * @param  list<Field>  $fields
     * @return list<OA\Property>
     */
    public function properties(array $fields): array
    {
        $properties = [];

        foreach ($fields as $field) {
            $properties[] = $this->property($field);
        }

        return $properties;
    }

    /**
     * Every declared field is in the payload: the resource walks the whole
     * declaration and writes a key for each of them, so an absent key would
     * mean a block type that no longer declares the field.
     *
     * @param  list<Field>  $fields
     * @return list<string>
     */
    public static function names(array $fields): array
    {
        $names = [];

        foreach ($fields as $field) {
            $names[] = $field->name();
        }

        return $names;
    }

    /**
     * An empty `required` is not valid OpenAPI, so a nested structure without
     * fields declares nothing instead of declaring nothing required.
     *
     * @param  list<Field>  $fields
     * @return list<string>|null
     */
    private static function required(array $fields): ?array
    {
        $names = self::names($fields);

        return $names === [] ? null : $names;
    }

    private function number(NumberField $field, string $name): OA\Property
    {
        $min = $field->getMin();
        $max = $field->getMax();

        return new OA\Property(
            property: $name,
            type: $field->isInteger() ? 'integer' : 'number',
            format: $field->isInteger() ? null : 'float',
            nullable: $this->nullable($field),
            maximum: $max,
            minimum: $min,
        );
    }

    private function entity(EntityField $field, string $name): OA\Property
    {
        $reference = new OA\Items(
            description: 'Ссылка на запись: тип и идентификатор',
            type: 'object',
            additionalProperties: true,
        );

        if ($field->isMultiple()) {
            return new OA\Property(property: $name, type: 'array', items: $reference);
        }

        return new OA\Property(
            property: $name,
            description: 'Ссылка на запись: тип и идентификатор',
            type: 'object',
            additionalProperties: true,
            nullable: $this->nullable($field),
        );
    }

    /**
     * A `$ref` cannot carry siblings in OpenAPI 3.0, so a nullable reference
     * is wrapped in `allOf` — the same shape the handwritten schemas used.
     */
    private function reference(string $name, string $component): OA\Property
    {
        return new OA\Property(
            property: $name,
            type: 'object',
            nullable: true,
            allOf: [new OA\Schema(ref: CommonSchemas::ref($component))],
        );
    }

    /**
     * A value that falls back to the declared default is null only while
     * there is no default to fall back to; a field declared non-nullable
     * stays non-nullable whatever the default.
     */
    private function nullable(Field $field): bool
    {
        return $field->isNullable() && $field->getDefault() === null;
    }

    /**
     * An empty set is not an enumeration of nothing: a declaration without
     * options leaves the type open instead of forbidding every value.
     *
     * A nullable enumeration lists `null` among its values: in OpenAPI 3.0
     * `nullable` alone does not widen an `enum`, so a field that answers null
     * would contradict its own set.
     *
     * @param  array<array-key, string>  $options
     * @return list<string|null>|null
     */
    private static function optionValues(array $options, bool $nullable): ?array
    {
        if ($options === []) {
            return null;
        }

        $values = array_map(static fn (int|string $key): string => (string) $key, array_keys($options));

        return $nullable ? [...$values, null] : $values;
    }
}
