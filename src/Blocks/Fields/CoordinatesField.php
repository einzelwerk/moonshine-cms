<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Closure;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;

/**
 * A latitude/longitude pair, stored as `{"lat": 50.08, "lng": 14.44}`.
 */
final class CoordinatesField extends Field
{
    public function normalize(mixed $raw): mixed
    {
        if (! is_array($raw)) {
            return null;
        }

        $lat = $raw['lat'] ?? null;
        $lng = $raw['lng'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    public function rules(): array
    {
        return [
            'array',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value)) {
                    return;
                }

                $lat = $value['lat'] ?? null;
                $lng = $value['lng'] ?? null;

                if (! is_numeric($lat) || ! is_numeric($lng)) {
                    $fail((string) __('cms-blocks::validation.coordinates.numeric'));

                    return;
                }

                if (abs((float) $lat) > 90.0 || abs((float) $lng) > 180.0) {
                    $fail((string) __('cms-blocks::validation.coordinates.range'));
                }
            },
        ];
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
