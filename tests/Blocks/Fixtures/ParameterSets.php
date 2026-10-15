<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\Params\DateParam;
use Ewk\Cms\Blocks\Params\EnumParam;
use Ewk\Cms\Blocks\Params\NormalizedParameters;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Params\ResolvedParameters;
use Ewk\Cms\Blocks\Params\StringParam;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * Normalized sets built by hand, so the payload and the addresses can be tested
 * without a database behind them.
 */
final class ParameterSets
{
    /**
     * @param  array<string, mixed>  $values
     */
    public static function pages(
        string $anchor,
        array $values = [],
        int $page = 1,
        int $perPage = 5,
        ?int $defaultPerPage = null,
    ): NormalizedParameters {
        return new NormalizedParameters(
            anchor: $anchor,
            values: ['city' => null, 'month' => null, ...$values],
            page: $page,
            perPage: $perPage,
            shown: null,
            cursor: null,
            schema: BlockSchema::make()
                ->params([
                    EnumParam::make('city')->options(['berlin' => 'Берлин', 'koeln' => 'Кёльн'])->nullable()->filter(),
                    StringParam::make('month')->nullable()->filter(),
                ])
                ->pagination(mode: PaginationMode::PAGES, perPage: $defaultPerPage ?? $perPage),
            defaultPerPage: $defaultPerPage ?? $perPage,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function more(
        string $anchor,
        array $values = [],
        int $shown = 12,
        int $perPage = 12,
        int $maxShown = 48,
    ): NormalizedParameters {
        return new NormalizedParameters(
            anchor: $anchor,
            values: ['date' => null, ...$values],
            page: 1,
            perPage: $perPage,
            shown: $shown,
            cursor: null,
            schema: BlockSchema::make()
                ->params([DateParam::make('date')->nullable()->filter()])
                ->pagination(mode: PaginationMode::MORE, perPage: $perPage, maxShown: $maxShown),
            maxShown: $maxShown,
        );
    }

    public static function resolved(NormalizedParameters ...$sets): ResolvedParameters
    {
        $byAnchor = [];

        foreach ($sets as $set) {
            $byAnchor[$set->anchor] = $set;
        }

        return new ResolvedParameters($byAnchor);
    }
}
