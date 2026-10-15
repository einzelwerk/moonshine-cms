<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Chrome;

use Ewk\Cms\Blocks\Fields\Field;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * What a header or a footer holds. The fields are the very {@see Field} objects
 * of the blocks: the CMS has exactly one way of describing fields, so the
 * layouts declare no field types of its own.
 *
 * What it does not carry is the part of {@see BlockSchema} that only makes
 * sense inside the block tree — placement, variants and shareability. A
 * header is a content bag with a code, not a node of a tree, and a schema
 * claiming otherwise would be a lie the admin has to work around.
 *
 * Everything the blocks package derives from a schema — validation rules,
 * the stored shape, the payload — is derived from these same fields;
 * {@see self::toBlockSchema()} hands them to machinery typed on
 * {@see BlockSchema}, such as
 * {@see \Ewk\Cms\Blocks\Schema\ValidationRuleBuilder}.
 */
final class ChromeSchema
{
    private string $title = '';

    /** @var list<Field> */
    private array $fields = [];

    public static function make(): self
    {
        return new self();
    }

    /**
     * @return ($title is null ? string : $this)
     */
    public function title(?string $title = null): static|string
    {
        if ($title === null) {
            return $this->title;
        }

        $this->title = $title;

        return $this;
    }

    /**
     * @param  array<array-key, Field>|null  $fields
     * @return ($fields is null ? list<Field> : $this)
     */
    public function fields(?array $fields = null): static|array
    {
        if ($fields === null) {
            return $this->fields;
        }

        $this->fields = array_values($fields);

        return $this;
    }

    public function hasField(string $name): bool
    {
        return $this->field($name) instanceof Field;
    }

    public function field(string $name): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->name() === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * The same declaration in the shape the blocks package speaks, so its
     * rule builder works on a header without a second implementation. The
     * placement of the produced schema is meaningless and never read: nothing
     * ever registers a header as a block type.
     */
    public function toBlockSchema(): BlockSchema
    {
        return BlockSchema::make()
            ->title($this->title)
            ->fields($this->fields);
    }
}
