<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

/**
 * Neutral description of one query parameter of a dynamic block. A parameter is
 * not a field: fields describe what the editor stores, parameters describe the
 * input of a dynamic request and yield its validation, its OpenAPI description
 * and its normalization.
 *
 * Names are written the way the rest of the public API is written, in
 * snake_case (`per_page`); named arguments of the declaration stay camelCase by
 * the convention of the language.
 *
 * Parameters of the pagination mode itself (`page`, `per_page`, `shown`,
 * `cursor`) are never declared here: the mode adds them, see
 * {@see Pagination}.
 *
 * Fluent setters carry the bare name of the property; the matching readers use
 * the `get`/`is` prefix, so a setter call is never mistaken for a read.
 */
abstract class Param
{
    protected ?string $label = null;

    protected bool $nullable = false;

    protected mixed $default = null;

    protected bool $filter = false;

    final protected function __construct(private readonly string $name)
    {
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * A filtering parameter is one the visitor is allowed to change himself.
     * The distinction is not cosmetic: the filtering keys of a page decide its
     * canonical address and its crawling rules, while the positional ones do
     * not.
     */
    public function filter(bool $filter = true): static
    {
        $this->filter = $filter;

        return $this;
    }

    public function isFilter(): bool
    {
        return $this->filter;
    }

    /**
     * Validation rules of the value, without a presence rule: an absent
     * parameter means "use the default", and only a present one is checked.
     *
     * @return list<mixed>
     */
    abstract public function rules(): array;

    /**
     * Coerce a raw query value into the declared type. An empty value is
     * treated as absent by the resolution, so it never reaches this method.
     */
    abstract public function normalize(mixed $raw): mixed;

    /**
     * The value the rules are checked against. It is not the raw one wherever
     * normalization cannot reject anything by itself: a trimmed string matching
     * the declared pattern must not become a 422 over the spaces around it.
     */
    public function valueForValidation(mixed $raw): mixed
    {
        return $raw;
    }

    /**
     * A regular expression of the declaration carries no delimiters, because
     * it also has to describe the parameter in OpenAPI. The delimiter is
     * picked here out of the characters the pattern does not use.
     */
    protected static function delimited(string $pattern): string
    {
        foreach (['/', '#', '~', '%'] as $delimiter) {
            if (! str_contains($pattern, $delimiter)) {
                return $delimiter.$pattern.$delimiter;
            }
        }

        return '/'.str_replace('/', '\/', $pattern).'/';
    }
}
