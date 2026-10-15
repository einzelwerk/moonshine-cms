<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * The parameters of one dynamic block after normalization: values cast to the
 * declared types, defaults substituted explicitly, undeclared parameters
 * dropped, the position clamped instead of rejected.
 *
 * Normalization is mandatory with or without a cache, because equal requests
 * have to behave equally; when caching returns, this is also what the key is
 * built from.
 */
final readonly class NormalizedParameters
{
    /**
     * @param  string  $anchor  the namespace key of the block within its owner
     * @param  array<string, mixed>  $values  declared parameter name => typed value
     * @param  int  $page  numbered position, one based
     * @param  int  $perPage  effective size of one portion
     * @param  int  $defaultPerPage  the size in force without a query parameter: schema, or the setting of the block
     * @param  int|null  $shown  the growing slice, only in `more` over the page address
     * @param  string|null  $cursor  opaque position, only in `more` over the block endpoint
     * @param  int|null  $maxShown  effective ceiling of the growing slice
     * @param  PaginationMode|null  $effectiveMode  the mode of this block, which the editor may have picked himself
     */
    public function __construct(
        public string $anchor,
        public array $values,
        public int $page,
        public int $perPage,
        public ?int $shown,
        public ?string $cursor,
        public BlockSchema $schema,
        public ?int $maxShown = null,
        public ?int $defaultPerPage = null,
        public ?PaginationMode $effectiveMode = null,
    ) {
    }

    public function get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    /**
     * Only the parameters the visitor is allowed to change himself: they decide
     * the canonical address of the page, the positional ones do not.
     *
     * @return array<string, mixed>
     */
    public function filterValues(): array
    {
        $values = [];

        foreach ($this->schema->params() as $param) {
            if ($param->isFilter()) {
                $values[$param->name()] = $this->values[$param->name()] ?? null;
            }
        }

        return $values;
    }

    /**
     * The mode in force for this very block: the one the editor picked where
     * the schema allows it, the declared one otherwise.
     */
    public function mode(): PaginationMode
    {
        return $this->effectiveMode ?? $this->schema->pagination()->mode ?? PaginationMode::NONE;
    }

    public function continuation(): Continuation
    {
        return $this->schema->pagination()->continuation ?? Continuation::URL;
    }
}
