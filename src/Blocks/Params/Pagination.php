<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

/**
 * The pagination declaration of a block type.
 *
 * Both ceilings here are the block's own. The package config holds an absolute
 * one that neither the schema, nor a setting of a block, nor a query parameter
 * may exceed; the effective limit is the smaller of the two.
 */
final readonly class Pagination
{
    /**
     * @param  PaginationMode  $mode  the mode in force unless the editor picked another one
     * @param  int  $perPage  default size of one portion
     * @param  int|null  $maxPerPage  the block's own ceiling for `per_page`, null for the config one
     * @param  bool  $editable  may the editor change the size for one block
     * @param  int|null  $maxShown  the block's own ceiling for the growing slice, null for the config one
     * @param  Continuation  $continuation  how a `more` list continues
     * @param  bool  $modeEditable  may the editor change the mode for one block
     * @param  list<PaginationMode>  $allowedModes  what he may pick, empty for all three
     */
    public function __construct(
        public PaginationMode $mode,
        public int $perPage = 10,
        public ?int $maxPerPage = null,
        public bool $editable = false,
        public ?int $maxShown = null,
        public Continuation $continuation = Continuation::URL,
        public bool $modeEditable = false,
        public array $allowedModes = [],
    ) {
    }

    public function allows(PaginationMode $mode): bool
    {
        return $this->allowedModes === [] || in_array($mode, $this->allowedModes, true);
    }
}
