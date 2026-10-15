<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Support;

use Ewk\Cms\Pages\Types\PageType;
use Illuminate\Database\Eloquent\Model;

/**
 * The address of an entity, built out of the declaration of its page type and
 * out of nothing else: the template that parses an incoming address is the
 * template that builds one, so there is no second list of routes to keep in
 * sync.
 *
 * This is the building direction. The verifying direction lives in
 * {@see \Ewk\Cms\Pages\Resolving\PathResolver}, and the two are deliberately
 * not the same call: the resolver already holds the values of a requested
 * address and only has to confirm them, while here there is nothing but the
 * entity, so a placeholder the declaration cannot fill leaves no address at
 * all instead of a half built one.
 */
final readonly class SubjectPath
{
    /**
     * The canonical address of the entity, or null when the type declares no
     * template or the declaration cannot fill every placeholder of it.
     */
    public static function of(PageType $type, Model $entity): ?string
    {
        $pattern = $type->pattern();
        $subject = $type->subject();
        $placeholder = $type->subjectPlaceholder();

        if ($pattern === null || $subject === null || $placeholder === null) {
            return null;
        }

        $values = [];

        foreach ($pattern->placeholders() as $name) {
            $accessor = $name === $placeholder ? $subject->slugColumn : ($subject->context[$name] ?? null);

            if ($accessor === null) {
                return null;
            }

            $value = data_get($entity, $accessor);

            if (! is_scalar($value) || (string) $value === '') {
                return null;
            }

            $values[$name] = (string) $value;
        }

        return $pattern->build($values);
    }
}
