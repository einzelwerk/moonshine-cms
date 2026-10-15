<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The guard of a command that writes. MoonShine routes an async method with
 * `Route::any`, so a method that rewrites rows is reachable with a GET unless
 * it says otherwise — and a GET address that changes data is one `<img src>`
 * away from being triggered by any page the editor opens, with no token asked.
 *
 * A POST goes through the CSRF middleware of the panel, so refusing anything
 * else is the whole of the fix.
 */
final class WriteRequest
{
    /**
     * The refusal to answer, or null when the request may proceed.
     */
    public static function refuse(Request $request): ?JsonResponse
    {
        if ($request->isMethod('POST')) {
            return null;
        }

        return new JsonResponse(
            ['message' => (string) __('moonshine-cms::editor.post_required')],
            Response::HTTP_METHOD_NOT_ALLOWED,
        );
    }
}
