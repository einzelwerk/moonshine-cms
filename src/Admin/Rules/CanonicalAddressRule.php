<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The manual canonical address: a field for exceptions, so the editor may
 * either point at a path of this site or at an address of another one, and
 * nothing else.
 *
 * A bare word such as `afisha` is refused rather than guessed at: the package
 * makes a path absolute and would turn the typo into a canonical tag pointing
 * at a page that does not exist, which is worse than no tag at all.
 */
final readonly class CanonicalAddressRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $value = trim($value);

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return;
        }

        if (preg_match('#^https?://#i', $value) === 1 && filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return;
        }

        $fail((string) __('moonshine-cms::seo.canonical.invalid'));
    }
}
