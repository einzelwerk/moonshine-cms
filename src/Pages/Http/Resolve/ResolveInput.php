<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Http\Resolve;

use Ewk\Cms\Pages\Support\PageLimits;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * What the two endpoints read out of the request, and the rules the address
 * has to pass.
 *
 * It is deliberately not a `FormRequest`. A form request is a *second* request
 * object, built by `createFrom()` and injected into the controller alone,
 * while everything downstream — the options of an `EnumParam`, a block reading
 * the subject of the page — asks the container for the request and gets the
 * original one. Attaching the subject to the controller's copy would leave the
 * rest of the request looking at a page without a subject, and a declared
 * filter would then fail on a value it should merely have refused. One request
 * object per request, so the subject is where everyone looks for it.
 */
final class ResolveInput
{
    /**
     * The address is mandatory: there is no default page, and guessing one
     * would hide a broken integration behind the home page.
     *
     * On the endpoint of one block it is mandatory for a stronger reason — the
     * block is not looked up by its uuid alone.
     *
     * @throws ValidationException
     */
    public static function path(Request $request): string
    {
        $request->validate([
            'path' => ['required', 'string', 'max:'.PageLimits::PATH_LENGTH],
        ]);

        return $request->string('path')->value();
    }

    /**
     * The query of one block: everything but the address of its page. There is
     * no anchor namespace here, because the request carries one block; what
     * the schema does not declare is dropped by the resolution.
     *
     * @return array<array-key, mixed>
     */
    public static function blockQuery(Request $request): array
    {
        $query = $request->query();

        unset($query['path']);

        return $query;
    }
}
