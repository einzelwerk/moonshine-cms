<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

/**
 * Every normalized set of one request, by anchor. One request carries the state
 * of the whole page, so the addresses of one block are built out of the
 * parameters of all of them: a link of the schedule keeps the filter of the
 * grid untouched.
 */
final readonly class ResolvedParameters
{
    /**
     * @param  array<string, NormalizedParameters>  $sets  anchor => normalized set
     */
    public function __construct(private array $sets = [])
    {
    }

    public function for(string $anchor): ?NormalizedParameters
    {
        return $this->sets[$anchor] ?? null;
    }

    /**
     * @return list<string>
     */
    public function anchors(): array
    {
        return array_keys($this->sets);
    }

    /**
     * The state of the page as a nested query array, `b[anchor][name]`. Keys
     * are sorted and defaults are omitted, so one state of the page has exactly
     * one query representation: without that, a request differing only in the
     * order of its parameters would look like a different one.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function toQuery(): array
    {
        $namespaced = [];

        foreach ($this->sets as $anchor => $set) {
            $written = $this->queryOf($set);

            if ($written !== []) {
                $namespaced[$anchor] = $written;
            }
        }

        if ($namespaced === []) {
            return [];
        }

        ksort($namespaced);

        return ['b' => $namespaced];
    }

    /**
     * The keys a visitor filters by, for the canonical address and the crawling
     * rules of the SEO part.
     *
     * @return list<string>
     */
    public function filterQueryKeys(): array
    {
        $keys = [];

        foreach ($this->sets as $anchor => $set) {
            foreach ($set->schema->params() as $param) {
                if ($param->isFilter()) {
                    $keys[] = self::key($anchor, $param->name());
                }
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * The keys that only size the list of a block, for the canonical address:
     * a longer slice or a bigger portion shows the same list again, and only
     * the page number makes a page of its own.
     *
     * @return list<string>
     */
    public function sliceQueryKeys(): array
    {
        $keys = [];

        foreach ($this->anchors() as $anchor) {
            $keys[] = self::key($anchor, 'per_page');
            $keys[] = self::key($anchor, 'shown');
        }

        sort($keys);

        return $keys;
    }

    /**
     * What a set writes into the address: the values that differ from the
     * declared defaults, plus the position when it is not the first one, plus a
     * portion size the visitor asked for himself. The cursor is never written
     * here — it belongs to the endpoint of a block, not to the address of a
     * page.
     *
     * @return array<string, mixed>
     */
    private function queryOf(NormalizedParameters $set): array
    {
        $written = [];

        foreach ($set->schema->params() as $param) {
            $value = $set->get($param->name());

            if ($value !== null && $value !== $param->getDefault()) {
                $written[$param->name()] = $value;
            }
        }

        if ($set->mode() === PaginationMode::PAGES) {
            if ($set->page > 1) {
                $written['page'] = $set->page;
            }

            // Without this the next page would be addressed with the default
            // portion size and the list would change size under the visitor.
            if ($set->defaultPerPage !== null && $set->perPage !== $set->defaultPerPage) {
                $written['per_page'] = $set->perPage;
            }
        }

        if ($set->shown !== null && $set->shown !== $set->perPage) {
            $written['shown'] = $set->shown;
        }

        ksort($written);

        return $written;
    }

    private static function key(string $anchor, string $name): string
    {
        return 'b['.$anchor.']['.$name.']';
    }
}
