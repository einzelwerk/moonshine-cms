<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Closure;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;

/**
 * A reference to a media record: `{"id": "<media uuid>", "metadata": {..}}`.
 * The file identifier stays shared, localizable properties such as `alt` live
 * inside `metadata`.
 *
 * This field validates the value shape only and deliberately does not depend
 * on the media package: hydrating the reference into a full media object with
 * URL, MIME, dimensions, placeholder and named variants is the job of the
 * resource layer of the consuming application, not of this field. The payload
 * therefore carries the stored reference unchanged.
 */
final class MediaField extends Field
{
    private const UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    /** @var list<string> */
    private array $kinds = [];

    /**
     * The kinds of file this place takes, as the media library names them:
     * `image`, `vector`, `video`, `audio`, `document`. Plain strings, because
     * this package does not depend on the media one; the admin narrows its
     * dialog to them, an unknown name is dropped there.
     *
     * @param  array<array-key, string>  $kinds  e.g. ['video']
     */
    public function kinds(array $kinds): static
    {
        $this->kinds = array_values(array_unique(array_values($kinds)));

        return $this;
    }

    /**
     * @return list<string>  empty means the default of the admin: a picture or an icon
     */
    public function getKinds(): array
    {
        return $this->kinds;
    }

    public function normalize(mixed $raw): mixed
    {
        if (! is_array($raw)) {
            return null;
        }

        $id = self::stringOrNull($raw['id'] ?? null);

        if ($id === null) {
            return null;
        }

        $metadata = $raw['metadata'] ?? null;
        $value = ['id' => $id];

        if (is_array($metadata)) {
            $value['metadata'] = $metadata;
        }

        return $value;
    }

    public function rules(): array
    {
        return [
            'array',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value)) {
                    return;
                }

                $id = $value['id'] ?? null;

                if (! is_string($id) || preg_match(self::UUID_PATTERN, $id) !== 1) {
                    $fail((string) __('cms-blocks::validation.media.uuid'));

                    return;
                }

                if (array_key_exists('metadata', $value) && ! is_array($value['metadata'])) {
                    $fail((string) __('cms-blocks::validation.media.metadata'));
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
