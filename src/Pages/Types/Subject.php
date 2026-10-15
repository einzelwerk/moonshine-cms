<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Types;

use Illuminate\Database\Eloquent\Model;

/**
 * The entity behind a template page type, declared in one place and read in
 * both directions: the class, the column the address carries, and the criterion
 * of public availability.
 *
 * The visibility criterion is a query scope named by string and called by
 * name, never a hardcoded one: every entity of the catalog has its own, and
 * the resolver must not know which.
 */
final readonly class Subject
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, string>  $context  placeholder => accessor path on the subject, e.g. ['tour' => 'tour.slug']
     * @param  class-string<SubjectPresenter>|null  $presenterClass  how the entity reaches the answer of the resolver
     */
    private function __construct(
        public string $modelClass,
        public string $slugColumn = 'slug',
        public ?string $visibilityScope = null,
        public array $context = [],
        public ?string $presenterClass = null,
    ) {
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function model(string $modelClass): self
    {
        return new self($modelClass);
    }

    public function by(string $slugColumn): self
    {
        return new self($this->modelClass, $slugColumn, $this->visibilityScope, $this->context, $this->presenterClass);
    }

    public function visible(string $scope): self
    {
        return new self($this->modelClass, $this->slugColumn, $scope, $this->context, $this->presenterClass);
    }

    /**
     * The presenter of the entity, read back without an argument and declared
     * with one. It is named by class and resolved out of the container at the
     * moment of the answer: a presenter of an application reuses its public
     * resources, which a package may not know about.
     *
     * @param  class-string<SubjectPresenter>|null  $presenter
     * @return ($presenter is null ? class-string<SubjectPresenter>|null : self)
     */
    public function presenter(?string $presenter = null): self|string|null
    {
        if ($presenter === null) {
            return $this->presenterClass;
        }

        return new self($this->modelClass, $this->slugColumn, $this->visibilityScope, $this->context, $presenter);
    }

    /**
     * How the remaining placeholders of the path template are read back from
     * the subject, so that the canonical address can be checked: an event
     * opened under the wrong tour is a different address and not the event.
     *
     * A placeholder left undeclared is simply not verified.
     *
     * @param  array<string, string>  $context  placeholder => accessor path, e.g. ['tour' => 'tour.slug']
     */
    public function context(array $context): self
    {
        return new self($this->modelClass, $this->slugColumn, $this->visibilityScope, $context, $this->presenterClass);
    }
}
