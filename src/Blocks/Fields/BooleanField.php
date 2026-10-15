<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;

final class BooleanField extends Field
{
    public function normalize(mixed $raw): mixed
    {
        if ($raw === null || ! is_scalar($raw)) {
            return null;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $raw;
    }

    public function rules(): array
    {
        return ['boolean'];
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
