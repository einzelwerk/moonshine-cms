<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;

final class NumberField extends Field
{
    private int|float|null $min = null;

    private int|float|null $max = null;

    private bool $integer = true;

    public function min(int|float $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(int|float $max): static
    {
        $this->max = $max;

        return $this;
    }

    /**
     * Whole numbers unless floats are allowed explicitly.
     */
    public function integer(bool $integer = true): static
    {
        $this->integer = $integer;

        return $this;
    }

    public function getMin(): int|float|null
    {
        return $this->min;
    }

    public function getMax(): int|float|null
    {
        return $this->max;
    }

    public function isInteger(): bool
    {
        return $this->integer;
    }

    public function normalize(mixed $raw): mixed
    {
        if (! is_scalar($raw) || (is_string($raw) && trim($raw) === '')) {
            return null;
        }

        return $this->integer ? (int) $raw : (float) $raw;
    }

    public function rules(): array
    {
        $rules = ['numeric'];

        if ($this->integer) {
            $rules[] = 'integer';
        }

        if ($this->min !== null) {
            $rules[] = 'min:'.$this->min;
        }

        if ($this->max !== null) {
            $rules[] = 'max:'.$this->max;
        }

        return $rules;
    }

    public function toPayload(
        mixed $storedValue,
        string $locale,
        string $fallbackLocale,
        ?LinkResolverRegistry $linkResolver = null,
    ): mixed {
        return $storedValue === null ? $this->default : $this->normalize($storedValue);
    }
}
