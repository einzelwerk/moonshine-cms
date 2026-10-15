<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Chrome;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;

/**
 * The stored and the delivered shape of the content of a header or a footer,
 * both derived from its declared fields and from nothing else.
 *
 * Write: only declared keys survive and each value passes through the
 * `normalize()` of its own field type, the same filtering blocks apply in
 * {@see \Ewk\Cms\Blocks\Support\ContentFilter}.
 *
 * Read: every declared field converts its stored value into the frontend
 * value, exactly as {@see \Ewk\Cms\Blocks\Http\Resources\SchemaBlockResource}
 * does for a block. A key the schema no longer declares is ignored when
 * reading and never deleted behind the editor's back.
 */
final class ChromeContent
{
    /**
     * @param  array<array-key, mixed>  $content
     * @return array<string, mixed>
     */
    public function normalize(array $content, ChromeSchema $schema): array
    {
        $normalized = [];

        foreach ($schema->fields() as $field) {
            if (array_key_exists($field->name(), $content)) {
                $normalized[$field->name()] = $field->normalize($content[$field->name()]);
            }
        }

        return $normalized;
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @return array<string, mixed>
     */
    public function toPayload(
        array $content,
        ChromeSchema $schema,
        string $locale,
        string $fallbackLocale,
        ?LinkResolverRegistry $linkResolvers = null,
    ): array {
        $payload = [];

        foreach ($schema->fields() as $field) {
            $payload[$field->name()] = $field->toPayload(
                $content[$field->name()] ?? null,
                $locale,
                $fallbackLocale,
                $linkResolvers,
            );
        }

        return $payload;
    }
}
