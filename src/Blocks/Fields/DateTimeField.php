<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;

/**
 * An ISO date-time string both in storage and in the payload.
 */
final class DateTimeField extends Field
{
    public function normalize(mixed $raw): mixed
    {
        return self::stringOrNull($raw);
    }

    public function rules(): array
    {
        return ['date'];
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
