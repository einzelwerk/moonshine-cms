<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\OpenApi;

use Ewk\Cms\Blocks\OpenApi\CommonSchemas;
use Ewk\Cms\Pages\Types\DescribesSubjectSchema;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use OpenApi\Undefined;

final readonly class SubjectSchemaGenerator
{
    public const string SUBJECT = 'BlockSubject';

    public const string SUBJECT_DATA = 'CmsSubjectData';

    public function __construct(private PageTypeRegistry $types)
    {
    }

    /**
     * @return list<OA\Schema>
     */
    public function subjectSchemas(): array
    {
        $types = $this->subjectTypes();

        if ($types === []) {
            return [self::openSubject()];
        }

        $variants = [];
        $mapping = [];
        $refs = [];

        foreach ($types as $type) {
            $variant = self::variant($type);
            $ref = CommonSchemas::ref((string) $variant->schema);

            $variants[] = $variant;
            $mapping[$type->key()] = $ref;
            $refs[] = new OA\Schema(ref: $ref);
        }

        return [
            new OA\Schema(
                schema: self::SUBJECT,
                description: 'Сущность шаблонной страницы; тип сужается по `type`',
                discriminator: new OA\Discriminator(propertyName: 'type', mapping: $mapping),
                oneOf: $refs,
            ),
            ...$variants,
        ];
    }

    public function dataSchema(): OA\Schema
    {
        $members = [];
        $open = false;
        $empty = false;

        foreach ($this->subjectTypes() as $type) {
            $component = self::describedComponent($type);

            if ($component !== null) {
                $members[$component] = new OA\Schema(ref: CommonSchemas::ref($component));
            } elseif (self::hasPresenter($type)) {
                $open = true;
            } else {
                $empty = true;
            }
        }

        if ($members === []) {
            return new OA\Schema(
                schema: self::SUBJECT_DATA,
                description: 'Данные сущности шаблонной страницы от презентера её типа',
                type: 'object',
                additionalProperties: true,
            );
        }

        if ($open) {
            $members[] = new OA\Schema(type: 'object', additionalProperties: true);
        }

        if ($empty) {
            $members[] = new OA\Schema(type: 'object', additionalProperties: false);
        }

        return new OA\Schema(
            schema: self::SUBJECT_DATA,
            description: 'Данные сущности шаблонной страницы от презентера её типа',
            anyOf: array_values($members),
        );
    }

    /**
     * @return list<PageType>
     */
    private function subjectTypes(): array
    {
        return array_values(array_filter(
            $this->types->all(),
            static fn (PageType $type): bool => $type->subject() !== null,
        ));
    }

    private static function variant(PageType $type): OA\Schema
    {
        $component = self::describedComponent($type);
        $caption = $type->subjectTitle() !== '' ? $type->subjectTitle() : $type->title();

        return new OA\Schema(
            schema: self::SUBJECT.Str::studly($type->key()),
            description: $caption === '' ? Undefined::UNDEFINED : $caption,
            required: ['type', 'data'],
            properties: [
                new OA\Property(property: 'type', description: 'Ключ типа страницы', type: 'string', enum: [$type->key()]),
                self::dataProperty($type, $component),
            ],
            type: 'object',
        );
    }

    private static function dataProperty(PageType $type, ?string $component): OA\Property
    {
        if ($component !== null) {
            return new OA\Property(
                property: 'data',
                description: 'Данные сущности',
                allOf: [new OA\Schema(ref: CommonSchemas::ref($component))],
            );
        }

        if (! self::hasPresenter($type)) {
            return new OA\Property(
                property: 'data',
                description: 'Пустой объект: у типа нет презентера',
                type: 'object',
                additionalProperties: false,
            );
        }

        return new OA\Property(
            property: 'data',
            description: 'Данные сущности от презентера типа',
            type: 'object',
            additionalProperties: true,
        );
    }

    private static function hasPresenter(PageType $type): bool
    {
        return $type->subject()?->presenterClass !== null;
    }

    private static function openSubject(): OA\Schema
    {
        return new OA\Schema(
            schema: self::SUBJECT,
            description: 'Сущность шаблонной страницы',
            required: ['type', 'data'],
            properties: [
                new OA\Property(property: 'type', description: 'Ключ типа страницы', type: 'string'),
                new OA\Property(
                    property: 'data',
                    description: 'Данные сущности',
                    allOf: [new OA\Schema(ref: CommonSchemas::ref(self::SUBJECT_DATA))],
                ),
            ],
            type: 'object',
        );
    }

    private static function describedComponent(PageType $type): ?string
    {
        $presenter = $type->subject()?->presenterClass;

        if ($presenter === null || ! is_subclass_of($presenter, DescribesSubjectSchema::class)) {
            return null;
        }

        return $presenter::subjectSchema();
    }
}
