<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

final class IntegerParam extends Param
{
    private ?int $min = null;

    private ?int $max = null;

    public function min(int $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(int $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function getMin(): ?int
    {
        return $this->min;
    }

    public function getMax(): ?int
    {
        return $this->max;
    }

    public function rules(): array
    {
        $rules = ['integer'];

        if ($this->min !== null) {
            $rules[] = 'min:'.$this->min;
        }

        if ($this->max !== null) {
            $rules[] = 'max:'.$this->max;
        }

        return $rules;
    }

    public function normalize(mixed $raw): mixed
    {
        return is_scalar($raw) ? (int) $raw : null;
    }
}
