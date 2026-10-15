<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Turns the raw query of a request into the normalized set of every dynamic
 * block of the page.
 *
 * Three different rules meet here, and they are different on purpose:
 *
 * - an undeclared parameter, and a whole set under an anchor no page block
 *   carries, is dropped silently: a public address collects foreign labels, and
 *   a renamed anchor has to degrade into the first state of the list instead of
 *   into an error;
 * - a declared parameter with a value it does not accept, `per_page` above the
 *   effective limit included, is a 422: the client has to see that he did not
 *   get what he asked for;
 * - a position, `page` and `shown`, is normalized and never rejected, because
 *   it goes stale through no fault of the client: the list shrank, the ceiling
 *   was lowered, the link arrived from another state of the catalogue.
 */
final readonly class ParameterResolution
{
    public function __construct(
        private BlockRegistryInterface $registry,
        private ValidationFactory $validation,
        private int $defaultPerPage = 10,
        private int $maxPerPage = 100,
        private int $maxShown = 500,
    ) {
    }

    /**
     * A flat walk of the block tree, children of containers and inserted
     * shared blocks included: a list inside a column is addressed exactly like
     * a root one.
     *
     * @param  iterable<array-key, CmsBlock>  $blocks
     *
     * @throws ValidationException
     */
    public function resolve(iterable $blocks, BlockParameters $raw): ResolvedParameters
    {
        $sets = [];

        foreach ($blocks as $block) {
            $anchor = $block->anchor;

            // A block without an anchor has no parameters: the namespace key is
            // mandatory for a dynamic block and generated when it is saved.
            if ($anchor === null || $anchor === '' || isset($sets[$anchor])) {
                continue;
            }

            if (! $this->registry->has($block->type)) {
                continue;
            }

            $schema = $this->registry->schemaFor($block->type);

            if (! $schema->isDynamic()) {
                continue;
            }

            $sets[$anchor] = $this->setOf($schema, $block, $anchor, $raw);
        }

        return new ResolvedParameters($sets);
    }

    /**
     * @throws ValidationException
     */
    private function setOf(
        BlockSchema $schema,
        CmsBlock $block,
        string $anchor,
        BlockParameters $raw,
    ): NormalizedParameters {
        $rawSet = $raw->rawFor($anchor);
        $pagination = $schema->pagination();
        $mode = $this->mode($pagination, $block);

        $perPageLimit = max(1, min($pagination->maxPerPage ?? $this->maxPerPage, $this->maxPerPage));

        $options = $this->optionsOf($schema, $rawSet);

        $this->validate($schema, $anchor, $rawSet, $raw, $mode, $perPageLimit, $options);

        // The size in force without a query parameter, and the one the request
        // actually asked for. The address has to carry the second whenever it
        // differs from the first, or the next page would come back in another
        // size.
        $defaultPerPage = $this->declaredPerPage($pagination, $block, $perPageLimit);
        $perPage = $mode === PaginationMode::PAGES
            ? $this->requestedPerPage($rawSet, $defaultPerPage, $perPageLimit)
            : $defaultPerPage;

        $ceiling = $mode === PaginationMode::MORE && $pagination?->continuation === Continuation::URL
            ? $this->ceiling($pagination, $perPage)
            : null;

        return new NormalizedParameters(
            anchor: $anchor,
            values: $this->values($schema, $rawSet, $options),
            page: $mode === PaginationMode::PAGES ? $this->page($rawSet) : 1,
            perPage: $perPage,
            shown: $ceiling === null ? null : $this->shown($rawSet, $perPage, $ceiling),
            cursor: $mode === PaginationMode::MORE && $ceiling === null ? $this->cursor($rawSet) : null,
            schema: $schema,
            maxShown: $ceiling,
            defaultPerPage: $defaultPerPage,
            effectiveMode: $mode,
        );
    }

    /**
     * The mode in force: the one the editor picked for this block where the
     * schema allows it and the pick is among the allowed ones, the declared one
     * otherwise. An unknown or forbidden value is ignored, not an error — it is
     * a stale setting, and it must not take the page down.
     */
    private function mode(?Pagination $pagination, CmsBlock $block): PaginationMode
    {
        $declared = $pagination->mode ?? PaginationMode::NONE;

        if ($pagination === null || ! $pagination->modeEditable) {
            return $declared;
        }

        $stored = ($block->content ?? [])['pagination_mode'] ?? null;
        $chosen = is_string($stored) ? PaginationMode::tryFrom($stored) : null;

        return $chosen !== null && $pagination->allows($chosen) ? $chosen : $declared;
    }

    /**
     * The sets of the enum parameters the client sent, each computed once for
     * this resolution: the value is checked against the set and then
     * normalized against it, and a set coming out of a provider is a query of
     * the installation — two full reads of the cities for one `city=` would
     * be one too many. Nothing outlives the call, see
     * {@see EnumParam::getOptions()} for why a set must not be kept longer.
     *
     * Only the sent ones: an absent parameter is neither checked nor
     * normalized, so its set is never needed.
     *
     * @param  array<string, mixed>  $rawSet
     * @return array<string, array<string|int, string>>
     */
    private function optionsOf(BlockSchema $schema, array $rawSet): array
    {
        $options = [];

        foreach ($schema->params() as $param) {
            if ($param instanceof EnumParam && array_key_exists($param->name(), $rawSet)) {
                $options[$param->name()] = $param->getOptions();
            }
        }

        return $options;
    }

    /**
     * Only the parameters the client actually sent are checked: an absent one
     * means "use the default", and the default of a schema is not a client
     * input.
     *
     * @param  array<string, mixed>  $rawSet
     * @param  array<string, array<string|int, string>>  $options  see {@see self::optionsOf()}
     *
     * @throws ValidationException
     */
    private function validate(
        BlockSchema $schema,
        string $anchor,
        array $rawSet,
        BlockParameters $raw,
        PaginationMode $mode,
        int $perPageLimit,
        array $options,
    ): void {
        $data = [];
        $rules = [];

        foreach ($schema->params() as $param) {
            $name = $param->name();

            if (! array_key_exists($name, $rawSet)) {
                continue;
            }

            $key = $raw->errorKey($anchor, $name);
            $declared = $param instanceof EnumParam && isset($options[$name])
                ? $param->rulesAgainst($options[$name])
                : $param->rules();
            $rules[$key] = $param->isNullable() ? ['nullable', ...$declared] : $declared;
            Arr::set($data, $key, $param->valueForValidation($rawSet[$name]));
        }

        // `per_page` belongs to the `pages` mode; a `more` list takes its step
        // from the schema, because a free step would break the multiples of the
        // accumulator.
        if ($mode === PaginationMode::PAGES && array_key_exists('per_page', $rawSet)) {
            $key = $raw->errorKey($anchor, 'per_page');
            $rules[$key] = ['integer', 'min:1', 'max:'.$perPageLimit];
            Arr::set($data, $key, $rawSet['per_page']);
        }

        if ($rules === []) {
            return;
        }

        $validator = $this->validation->make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * @param  array<string, mixed>  $rawSet
     * @param  array<string, array<string|int, string>>  $options  see {@see self::optionsOf()}
     * @return array<string, mixed>
     */
    private function values(BlockSchema $schema, array $rawSet, array $options): array
    {
        $values = [];

        foreach ($schema->params() as $param) {
            $name = $param->name();

            if (! array_key_exists($name, $rawSet)) {
                $values[$name] = $param->getDefault();

                continue;
            }

            $normalized = $param instanceof EnumParam && isset($options[$name])
                ? $param->normalizeAgainst($rawSet[$name], $options[$name])
                : $param->normalize($rawSet[$name]);

            $values[$name] = $normalized ?? $param->getDefault();
        }

        return $values;
    }

    /**
     * The size in force without a query parameter: the setting of the block
     * wins over the default of the schema, and the effective limit caps both.
     */
    private function declaredPerPage(?Pagination $pagination, CmsBlock $block, int $limit): int
    {
        $perPage = $pagination->perPage ?? $this->defaultPerPage;

        if ($pagination?->editable === true) {
            $stored = ($block->content ?? [])['per_page'] ?? null;

            if (is_numeric($stored) && (int) $stored > 0) {
                $perPage = (int) $stored;
            }
        }

        return max(1, min($perPage, $limit));
    }

    /**
     * The query wins over both, within the same limit. A value above it is a
     * 422 and never arrives here.
     *
     * @param  array<string, mixed>  $rawSet
     */
    private function requestedPerPage(array $rawSet, int $default, int $limit): int
    {
        if (! isset($rawSet['per_page']) || ! is_numeric($rawSet['per_page'])) {
            return $default;
        }

        return max(1, min((int) $rawSet['per_page'], $limit));
    }

    /**
     * The effective ceiling of the growing slice, aligned to the portion once
     * and for all: a ceiling off the grid of the portion would leave a slice
     * the next click cannot reach, and the button would go round in circles.
     */
    private function ceiling(Pagination $pagination, int $perPage): int
    {
        $declared = min($pagination->maxShown ?? $this->maxShown, $this->maxShown);

        return max($perPage, intdiv($declared, $perPage) * $perPage);
    }

    /**
     * @param  array<string, mixed>  $rawSet
     */
    private function page(array $rawSet): int
    {
        $page = isset($rawSet['page']) && is_numeric($rawSet['page']) ? (int) $rawSet['page'] : 1;

        return max(1, $page);
    }

    /**
     * The accumulator is a position, not a choice of the visitor: it is brought
     * down to a multiple of the portion, raised to one portion and clamped by
     * the ceiling.
     *
     * @param  array<string, mixed>  $rawSet
     */
    private function shown(array $rawSet, int $perPage, int $ceiling): int
    {
        $shown = isset($rawSet['shown']) && is_numeric($rawSet['shown']) ? (int) $rawSet['shown'] : $perPage;

        // The ceiling already sits on the grid of the portion, so clamping
        // cannot move the slice off it.
        return min(intdiv(max($shown, $perPage), $perPage) * $perPage, $ceiling);
    }

    /**
     * @param  array<string, mixed>  $rawSet
     */
    private function cursor(array $rawSet): ?string
    {
        $cursor = $rawSet['cursor'] ?? null;

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }
}
