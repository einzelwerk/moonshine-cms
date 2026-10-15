<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Localization\LocalizedValueResolver;

/**
 * Neutral description of one value of a block. The declaration carries data
 * only: no admin classes and no closures, so a schema stays introspectable and
 * portable.
 *
 * Fluent setters carry the bare name of the property; the matching readers use
 * the `get`/`is` prefix, so a setter call is never mistaken for a read.
 */
abstract class Field
{
    protected ?string $label = null;

    protected bool $required = false;

    protected mixed $default = null;

    protected bool $nullable = true;

    protected bool $translatable = false;

    protected ?string $help = null;

    /** @var array{0: string, 1: mixed}|null */
    protected ?array $visibleWhen = null;

    protected ?string $group = null;

    protected ?string $tab = null;

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

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
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

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function translatable(bool $translatable = true): static
    {
        $this->translatable = $translatable;

        return $this;
    }

    public function isTranslatable(): bool
    {
        return $this->translatable;
    }

    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function getHelp(): ?string
    {
        return $this->help;
    }

    /**
     * Form-only reactivity hint: show this field while `$field` holds
     * `$value`. Visibility of a block to a visitor is a different mechanism.
     */
    public function visibleWhen(string $field, mixed $value): static
    {
        $this->visibleWhen = [$field, $value];

        return $this;
    }

    /**
     * @return array{0: string, 1: mixed}|null
     */
    public function getVisibleWhen(): ?array
    {
        return $this->visibleWhen;
    }

    public function group(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function tab(string $tab): static
    {
        $this->tab = $tab;

        return $this;
    }

    public function getTab(): ?string
    {
        return $this->tab;
    }

    /**
     * Coerce a raw input value into the shape that goes into the `content`
     * json. A translatable field receives a dictionary of languages.
     */
    abstract public function normalize(mixed $raw): mixed;

    /**
     * Validation rules for this field's own value, relative and without the
     * presence rule: the schema assembler prefixes the key with `content.`,
     * expands translatable fields per language and adds presence itself.
     *
     * @return list<mixed>
     */
    abstract public function rules(): array;

    /**
     * The same rules, narrowed by who the block belongs to. Only a field whose
     * values depend on the owner overrides this, see {@see FieldContext}; for
     * every other one the context changes nothing.
     *
     * @return list<mixed>
     */
    public function rulesIn(FieldContext $context): array
    {
        return $this->rules();
    }

    /**
     * Build the frontend value out of the stored one. Only {@see LinkField}
     * uses the resolver registry; the other types ignore it.
     */
    abstract public function toPayload(
        mixed $storedValue,
        string $locale,
        string $fallbackLocale,
        ?LinkResolverRegistry $linkResolver = null,
    ): mixed;

    /**
     * Apply a cast to every language of a translatable dictionary, or to the
     * bare value of a shared field.
     *
     * @param  callable(mixed): mixed  $cast
     */
    protected function mapTranslatable(mixed $raw, callable $cast): mixed
    {
        if (! $this->translatable || ! is_array($raw)) {
            return $cast($raw);
        }

        $normalized = [];

        foreach ($raw as $locale => $value) {
            $normalized[(string) $locale] = $cast($value);
        }

        return $normalized;
    }

    /**
     * Storage shape of a text-like value: a trimmed string, or a dictionary of
     * trimmed strings when the field is translatable.
     */
    protected function normalizeString(mixed $raw): mixed
    {
        return $this->mapTranslatable($raw, static fn (mixed $value): ?string => self::stringOrNull($value));
    }

    /**
     * Payload shape of a text-like value: the requested language resolved
     * through the single fallback step to the default language.
     */
    protected function resolveString(mixed $stored, string $locale, string $fallbackLocale): string
    {
        if ($this->translatable) {
            return LocalizedValueResolver::resolve(self::localizedSource($stored), $locale, $fallbackLocale);
        }

        return self::stringOrNull($stored) ?? self::stringOrNull($this->default) ?? '';
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    protected static function localizedSource(mixed $stored): array|string|null
    {
        if (is_array($stored)) {
            return $stored;
        }

        return is_string($stored) ? $stored : null;
    }

    protected static function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
