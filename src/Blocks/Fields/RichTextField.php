<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Support\HtmlSanitizer;

/**
 * HTML is allowed as the value of a rich text field and nowhere else. The
 * field declares the policy; sanitizing happens while building the payload,
 * because the frontend inserts the result as it is.
 *
 * The editor itself is not the core's choice: the schema says "rich text",
 * and the UI adapter picks the component.
 */
final class RichTextField extends Field
{
    private bool $sanitize = true;

    public function sanitize(bool $sanitize = true): static
    {
        $this->sanitize = $sanitize;

        return $this;
    }

    public function sanitizes(): bool
    {
        return $this->sanitize;
    }

    public function normalize(mixed $raw): mixed
    {
        return $this->normalizeString($raw);
    }

    public function rules(): array
    {
        return ['string'];
    }

    public function toPayload(
        mixed $storedValue,
        string $locale,
        string $fallbackLocale,
        ?LinkResolverRegistry $linkResolver = null,
    ): mixed {
        $html = $this->resolveString($storedValue, $locale, $fallbackLocale);

        return $this->sanitize ? (new HtmlSanitizer())->sanitize($html) : $html;
    }
}
