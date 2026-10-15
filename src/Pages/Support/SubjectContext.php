<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Carries the entity a template page is rendered for.
 *
 * The block renderer of the blocks hands a block only the request, so the
 * subject travels as a request attribute: the resolver attaches it once the
 * address has been resolved, and every block of the template reads it back. One
 * attribute key for the whole CMS, so a block of an application does not have
 * to know which entrance resolved the page.
 */
final class SubjectContext
{
    public const string ATTRIBUTE = 'cms.subject';

    public static function attach(Request $request, Model $subject): void
    {
        $request->attributes->set(self::ATTRIBUTE, $subject);
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public static function subject(Request $request, string $class): ?Model
    {
        $subject = $request->attributes->get(self::ATTRIBUTE);

        return $subject instanceof $class ? $subject : null;
    }

    /**
     * Whatever entity is attached, if any: a page of the tree has none.
     */
    public static function any(Request $request): ?Model
    {
        $subject = $request->attributes->get(self::ATTRIBUTE);

        return $subject instanceof Model ? $subject : null;
    }
}
