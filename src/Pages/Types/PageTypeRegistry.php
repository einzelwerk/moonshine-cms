<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Types;

use Ewk\Cms\Pages\Exceptions\DuplicatePageTypeException;
use Ewk\Cms\Pages\Exceptions\InvalidPageTypeException;
use Ewk\Cms\Pages\Exceptions\UnknownPageTypeException;
use Illuminate\Database\Eloquent\Model;

/**
 * The registry of page type declarations. It is also the content half of the
 * route manifest: the path template a type declares serves both the building
 * and the parsing of addresses, so there is no second list.
 *
 * The package registers nothing by default: the types of an installation name
 * its own models, and those live in the application.
 */
final class PageTypeRegistry
{
    /** @var array<string, PageType> */
    private array $types = [];

    /**
     * @throws DuplicatePageTypeException when the key is taken by another declaration
     * @throws InvalidPageTypeException when the declaration cannot work
     */
    public function register(PageType $type): void
    {
        $key = $type->key();
        $existing = $this->types[$key] ?? null;

        if ($existing !== null && $existing !== $type) {
            throw DuplicatePageTypeException::forKey($key);
        }

        $this->validate($type);

        $this->types[$key] = $type;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /**
     * @throws UnknownPageTypeException
     */
    public function get(string $key): PageType
    {
        return $this->types[$key] ?? throw UnknownPageTypeException::forKey($key);
    }

    /**
     * @return array<string, PageType>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * The template type an entity belongs to, found by the class the
     * declaration names. The lookup lives here and nowhere else: whoever holds
     * an entity and needs its type — the presenter of a block, the preview of
     * the metadata form — asks the registry instead of keeping a second table
     * of classes.
     *
     * The answer is the **first type that declared this model**, in
     * registration order, and `instanceof` is deliberate: a subclass of a
     * declared model is answered by the type of its parent, which is how a
     * model extended by an installation is recognized at all. Two types over
     * one model are therefore a declaration this method cannot tell apart — an
     * entity has exactly one template type, and a second one would mean two
     * addresses for one entity, which the resolver refuses. There is nothing to
     * disambiguate here, so this returns a type rather than a list.
     *
     * `null` for an entity no declared type stands for.
     */
    public function forSubject(Model $subject): ?PageType
    {
        foreach ($this->types as $type) {
            $declared = $type->subject();

            if ($declared !== null && $subject instanceof $declared->modelClass) {
                return $type;
            }
        }

        return null;
    }

    /**
     * A declaration that cannot work is refused here and not at the first
     * request that trips over it.
     */
    private function validate(PageType $type): void
    {
        $key = $type->key();
        $subject = $type->subject();

        if ($subject !== null && $subject->visibilityScope === null) {
            throw InvalidPageTypeException::missingVisibility($key);
        }

        // A presenter that is not one would only be discovered by the first
        // request that resolves an address of this type, which is the worst
        // possible moment to discover it.
        if ($subject?->presenterClass !== null && ! is_a($subject->presenterClass, SubjectPresenter::class, true)) {
            throw InvalidPageTypeException::invalidPresenter($key, $subject->presenterClass);
        }

        if ($type->isTemplate()) {
            if ($subject === null) {
                throw InvalidPageTypeException::missingSubject($key);
            }

            $pattern = $type->pattern();

            if ($pattern === null) {
                throw InvalidPageTypeException::missingPath($key);
            }

            if ($type->subjectPlaceholder() === null) {
                throw InvalidPageTypeException::subjectPlaceholderMissing($key, $pattern->pattern);
            }

            return;
        }

        if ($subject !== null) {
            throw InvalidPageTypeException::unexpectedSubject($key);
        }

        if ($type->cardinality() === Cardinality::SINGLE) {
            $pattern = $type->pattern();

            if ($pattern === null) {
                throw InvalidPageTypeException::missingPath($key);
            }

            if (! $pattern->isStatic()) {
                throw InvalidPageTypeException::unexpectedPlaceholders($key, $pattern->pattern);
            }

            return;
        }

        if ($type->pattern() !== null) {
            throw InvalidPageTypeException::unexpectedPath($key);
        }
    }
}
