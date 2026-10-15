<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Schema;

use Ewk\Cms\Blocks\Fields\Field;
use Ewk\Cms\Blocks\Params\Continuation;
use Ewk\Cms\Blocks\Params\Pagination;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Params\Param;
use Ewk\Cms\Blocks\Params\Sort;

/**
 * The single declarative description of a block type. Five things are derived
 * from it: the admin form, the validation rules, the storage shape, the
 * frontend payload and the API description. It holds data only — no UI classes,
 * no closures.
 *
 * Every accessor doubles as its own fluent setter: called with a value it
 * stores it and returns the schema, called without arguments it reads it back.
 */
final class BlockSchema
{
    private string $title = '';

    private ?string $category = null;

    /**
     * One line that says what the type is for, shown on the tile of the
     * insertion library. A type that declares none shows its title alone.
     */
    private ?string $description = null;

    /**
     * Variant code => admin label. A numeric code such as `2` is an ordinary
     * key of a PHP array, hence `array-key` and not `string`.
     *
     * @var array<array-key, string>
     */
    private array $variants = [];

    /** @var list<Field> */
    private array $fields = [];

    /** @var list<Param> */
    private array $params = [];

    private ?Pagination $pagination = null;

    private ?Sort $sort = null;

    /** @var list<string>|string */
    private array|string $placementRoot = ['*'];

    /** @var list<string>|string */
    private array|string $placementParents = [];

    private bool $shared = true;

    public static function make(): self
    {
        return new self();
    }

    /**
     * @return ($title is null ? string : $this)
     */
    public function title(?string $title = null): static|string
    {
        if ($title === null) {
            return $this->title;
        }

        $this->title = $title;

        return $this;
    }

    /**
     * @return ($description is null ? string|null : $this)
     */
    public function description(?string $description = null): static|string|null
    {
        if ($description === null) {
            return $this->description;
        }

        $this->description = $description;

        return $this;
    }

    /**
     * @return ($category is null ? string|null : $this)
     */
    public function category(?string $category = null): static|string|null
    {
        if ($category === null) {
            return $this->category;
        }

        $this->category = $category;

        return $this;
    }

    /**
     * @param  array<array-key, string>|null  $variants  variant code => admin label
     * @return ($variants is null ? array<array-key, string> : $this)
     */
    public function variants(?array $variants = null): static|array
    {
        if ($variants === null) {
            return $this->variants;
        }

        $this->variants = $variants;

        return $this;
    }

    /**
     * @param  array<array-key, Field>|null  $fields
     * @return ($fields is null ? list<Field> : $this)
     */
    public function fields(?array $fields = null): static|array
    {
        if ($fields === null) {
            return $this->fields;
        }

        $this->fields = array_values($fields);

        return $this;
    }

    /**
     * The query parameters of a dynamic block. Fields describe what the editor
     * stores, parameters describe the input of a dynamic request.
     *
     * The parameters of the pagination itself are never listed here: the
     * declared mode adds them. `pages` gives `page` and `per_page`, `more`
     * gives `shown` or `cursor` depending on its transport, `none` gives
     * nothing.
     *
     * @param  array<array-key, Param>|null  $params
     * @return ($params is null ? list<Param> : $this)
     */
    public function params(?array $params = null): static|array
    {
        if ($params === null) {
            return $this->params;
        }

        $this->params = array_values($params);

        return $this;
    }

    public function hasParams(): bool
    {
        return $this->params !== [];
    }

    public function param(string $name): ?Param
    {
        foreach ($this->params as $param) {
            if ($param->name() === $name) {
                return $param;
            }
        }

        return null;
    }

    /**
     * How the list of the block continues past its first screen. Called without
     * a mode it reads the declaration back.
     *
     * The declared mode is the one in force; where `modeEditable` allows it the
     * editor picks another one out of `allowedModes` for his own block.
     *
     * @param  list<PaginationMode>  $allowedModes
     * @return ($mode is null ? Pagination|null : $this)
     */
    public function pagination(
        ?PaginationMode $mode = null,
        int $perPage = 10,
        ?int $maxPerPage = null,
        bool $editable = false,
        ?int $maxShown = null,
        Continuation $continuation = Continuation::URL,
        bool $modeEditable = false,
        array $allowedModes = [],
    ): static|Pagination|null {
        if ($mode === null) {
            return $this->pagination;
        }

        $this->pagination = new Pagination(
            mode: $mode,
            perPage: $perPage,
            maxPerPage: $maxPerPage,
            editable: $editable,
            maxShown: $maxShown,
            continuation: $continuation,
            modeEditable: $modeEditable,
            allowedModes: $allowedModes,
        );

        return $this;
    }

    /**
     * The order of the list, declared once and applied to every portion of it.
     *
     * @return ($field is null ? Sort|null : $this)
     */
    public function sort(?string $field = null, string $direction = 'asc'): static|Sort|null
    {
        if ($field === null) {
            return $this->sort;
        }

        $this->sort = new Sort($field, $direction);

        return $this;
    }

    /**
     * A block is dynamic when it takes parameters or when its list continues:
     * only such a block gets a generated anchor, an entry in the resolution of
     * the parameters and a reachable endpoint of its own.
     */
    public function isDynamic(): bool
    {
        if ($this->hasParams() || $this->pagination === null) {
            return $this->hasParams();
        }

        // A block declared without a continuation is still dynamic while the
        // editor is allowed to turn one on: the anchor and the endpoint have to
        // exist before he does it, not after.
        return $this->pagination->mode !== PaginationMode::NONE || $this->pagination->modeEditable;
    }

    /**
     * Where the block type is allowed, collected in one declaration instead of
     * a handful of flags.
     *
     * @param  list<string>|string  $root  page type codes the block may sit on, or '*'
     * @param  list<string>|string  $parents  container type codes the block may sit in, '*', or [] for root only
     * @param  bool  $shared  may the block be put into the shared block library
     */
    public function placement(array|string $root = ['*'], array|string $parents = [], bool $shared = true): static
    {
        $this->placementRoot = $root;
        $this->placementParents = $parents;
        $this->shared = $shared;

        return $this;
    }

    /**
     * @return list<string>|string
     */
    public function placementRoot(): array|string
    {
        return $this->placementRoot;
    }

    /**
     * @return list<string>|string
     */
    public function placementParents(): array|string
    {
        return $this->placementParents;
    }

    public function isShareable(): bool
    {
        return $this->shared;
    }

    public function hasField(string $name): bool
    {
        foreach ($this->fields as $field) {
            if ($field->name() === $name) {
                return true;
            }
        }

        return false;
    }

    public function field(string $name): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->name() === $name) {
                return $field;
            }
        }

        return null;
    }
}
