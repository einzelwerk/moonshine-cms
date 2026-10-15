<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Http\Payload;

use Ewk\Cms\Blocks\Params\Continuation;
use Ewk\Cms\Blocks\Params\NormalizedParameters;
use Ewk\Cms\Blocks\Params\PageQuery;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Params\ResolvedParameters;

/**
 * The `pagination` object inside the `data` of a list block. The shape is
 * marked by the mode, and in `more` also by the transport of the continuation:
 * their fields differ, and the frontend narrows the type by that marking.
 *
 * Addresses arrive ready. The frontend assembles no query string and knows
 * nothing about the namespace by anchor: only the backend knows both the anchor
 * and the subject of a template page.
 */
final class PaginationPayload
{
    /**
     * Numbered pages. The first page is addressed without a number, in `self`
     * and in `prev` alike, so the address matches the canonical one. A missing
     * direction arrives as `null`.
     *
     * @return array{mode: string, per_page: int, current_page: int, last_page: int, total: int, has_more: bool, links: array{self: string, next: string|null, prev: string|null}}
     */
    public static function pages(NormalizedParameters $parameters, int $total, ResolvedParameters $page): array
    {
        $perPage = $parameters->perPage;
        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $current = $parameters->page;

        return [
            'mode' => PaginationMode::PAGES->value,
            'per_page' => $perPage,
            'current_page' => $current,
            'last_page' => $lastPage,
            'total' => $total,
            'has_more' => $current < $lastPage,
            'links' => [
                'self' => self::link($page, $parameters->anchor, $current),
                'next' => $current < $lastPage ? self::link($page, $parameters->anchor, $current + 1) : null,
                'prev' => $current > 1 ? self::link($page, $parameters->anchor, $current - 1) : null,
            ],
        ];
    }

    /**
     * A growing slice continued through the address of the page. `shown` is the
     * actual normalized slice, not what arrived in the address.
     *
     * `has_more: true` together with `next: null` means exactly one thing: the
     * list goes on, but the ceiling of the block is reached. The address of the
     * full catalogue lies next to it, and the frontend shows a link instead of
     * a button.
     *
     * @param  array{href: string}|null  $catalog
     * @return array{mode: string, continuation: string, per_page: int, shown: int, total: int, has_more: bool, next: string|null, catalog?: array{href: string}}
     */
    public static function more(
        NormalizedParameters $parameters,
        int $total,
        ResolvedParameters $page,
        ?array $catalog = null,
    ): array {
        $perPage = $parameters->perPage;
        $shown = $parameters->shown ?? $perPage;
        // The ceiling arrives aligned to the portion, so the next slice is one
        // portion further and the last reachable one equals the ceiling
        // exactly: that is how `has_more: true` meets `next: null`.
        $ceiling = $parameters->maxShown ?? $shown;
        $hasMore = $total > $shown;

        $payload = [
            'mode' => PaginationMode::MORE->value,
            'continuation' => Continuation::URL->value,
            'per_page' => $perPage,
            'shown' => $shown,
            'total' => $total,
            'has_more' => $hasMore,
            'next' => $hasMore && $shown < $ceiling
                ? PageQuery::with($page, $parameters->anchor, ['shown' => min($shown + $perPage, $ceiling)])
                : null,
        ];

        if ($catalog !== null) {
            $payload['catalog'] = $catalog;
        }

        return $payload;
    }

    /**
     * A growing list continued through the endpoint of the block. There is no
     * total in this transport: a cursor selection does not count one, and a
     * separate counting query would undo the point of it.
     *
     * @return array{mode: string, continuation: string, per_page: int, has_more: bool, next: string|null}
     */
    public static function moreByCursor(int $perPage, ?string $nextCursor, string $endpointHref): array
    {
        return [
            'mode' => PaginationMode::MORE->value,
            'continuation' => Continuation::ENDPOINT->value,
            'per_page' => $perPage,
            'has_more' => $nextCursor !== null,
            'next' => $nextCursor === null ? null : self::withCursor($endpointHref, $nextCursor),
        ];
    }

    /**
     * @return array{mode: string}
     */
    public static function none(): array
    {
        return ['mode' => PaginationMode::NONE->value];
    }

    private static function link(ResolvedParameters $page, string $anchor, int $number): string
    {
        return PageQuery::with($page, $anchor, ['page' => $number > 1 ? $number : null]);
    }

    private static function withCursor(string $href, string $cursor): string
    {
        return $href.(str_contains($href, '?') ? '&' : '?').'cursor='.rawurlencode($cursor);
    }
}
