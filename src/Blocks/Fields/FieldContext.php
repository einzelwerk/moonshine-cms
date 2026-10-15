<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Illuminate\Database\Eloquent\Model;

/**
 * Who the block being edited belongs to, as much of it as a field declaration
 * is allowed to know: the owner row and, when that owner renders an entity of
 * the catalog, the class of that entity.
 *
 * It exists because one field of one type offers different values in different
 * places: the source selector of a block has `subject` only where a subject
 * exists. The type of the owner is not asked for here — this package knows
 * nothing about pages, their types or their templates — so whoever builds the
 * form fills the context in.
 */
final readonly class FieldContext
{
    /**
     * @param  class-string<Model>|null  $subjectClass  the entity the owner is rendered for, when it has one
     */
    private function __construct(
        public ?Model $owner = null,
        public ?string $subjectClass = null,
    ) {
    }

    /**
     * Nothing is known about the owner, which is not the same as an owner
     * without a subject: a field asked in this context answers with its own
     * declaration, see {@see SelectField::optionsIn()}. This is what a schema
     * read outside a form gets, and it is deliberately the widest context and
     * not the narrowest — a rule derived from it must never be stricter than
     * the form the editor saw.
     */
    public static function none(): self
    {
        return new self();
    }

    /**
     * @param  class-string<Model>|null  $subjectClass
     */
    public static function of(?Model $owner, ?string $subjectClass = null): self
    {
        return new self($owner, $subjectClass);
    }

    public function hasSubject(): bool
    {
        return $this->subjectClass !== null;
    }

    /**
     * @param  class-string<Model>  $class
     */
    public function subjectIs(string $class): bool
    {
        return $this->subjectClass === $class;
    }
}
