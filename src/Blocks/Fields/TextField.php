<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;

final class TextField extends Field
{
    private ?int $maxLength = null;

    public function maxLength(int $max): static
    {
        $this->maxLength = $max;

        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function normalize(mixed $raw): mixed
    {
        return $this->normalizeString($raw);
    }

    public function rules(): array
    {
        $rules = ['string'];

        if ($this->maxLength !== null) {
            $rules[] = 'max:'.$this->maxLength;
        }

        return $rules;
    }

    public function toPayload(
        mixed $storedValue,
        string $locale,
        string $fallbackLocale,
        ?LinkResolverRegistry $linkResolver = null,
    ): mixed {
        return $this->resolveString($storedValue, $locale, $fallbackLocale);
    }
}
