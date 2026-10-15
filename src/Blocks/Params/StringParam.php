<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

final class StringParam extends Param
{
    private ?string $pattern = null;

    private ?int $maxLength = null;

    /**
     * A regular expression without delimiters and without anchors of its own
     * beyond the ones it spells, such as `^\d{4}-(0[1-9]|1[0-2])$`.
     */
    public function pattern(string $pattern): static
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function maxLength(int $max): static
    {
        $this->maxLength = $max;

        return $this;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function rules(): array
    {
        $rules = ['string'];

        if ($this->pattern !== null) {
            $rules[] = 'regex:'.self::delimited($this->pattern);
        }

        if ($this->maxLength !== null) {
            $rules[] = 'max:'.$this->maxLength;
        }

        return $rules;
    }

    public function valueForValidation(mixed $raw): mixed
    {
        return is_scalar($raw) ? trim((string) $raw) : $raw;
    }

    public function normalize(mixed $raw): mixed
    {
        if (! is_scalar($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        return $value === '' ? null : $value;
    }
}
