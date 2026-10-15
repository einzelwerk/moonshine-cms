<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Fields;

use Closure;
use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Links\ResolvedLink;
use Ewk\Cms\Blocks\Links\ValidatesLinkTarget;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Validator;

/**
 * A typed link. The stored value carries the kind of the target and its
 * identifiers; the payload carries the resolved address, or `href: null` when
 * the target is unreachable.
 *
 * The schema may narrow the kinds a specific field accepts — a hero button
 * wants an entity, a page or an external address, but not an action. On top of
 * that narrowing the field refuses a kind no resolver stands behind: such a
 * link could never produce an address, and letting an editor store one turns a
 * configuration mistake into content that looks fine until a visitor clicks it.
 */
final class LinkField extends Field
{
    /**
     * Lengths of the stored targets. The address gets the ceiling the whole
     * stack agrees on; every other target is an identifier, a name or a number
     * and has no business being longer than a column of an ordinary table.
     */
    public const int URL_LIMIT = 2048;

    public const int TARGET_LIMIT = 255;

    private const string E164_PATTERN = '/^\+?[1-9]\d{6,14}$/';

    /**
     * The target every kind is addressed by. Every kind has one: a catalog
     * link is a page with filters chosen, and the filters are what may be
     * left out, never the page — without it the link could not produce an
     * address at all and would be stored already dead.
     *
     * @var array<string, string>
     */
    private const array TARGETS = [
        'entity' => 'id',
        'page' => 'page',
        'list' => 'page',
        'anchor' => 'anchor',
        'file' => 'file',
        'route' => 'route',
        'url' => 'url',
        'email' => 'email',
        'phone' => 'phone',
        'action' => 'action',
    ];

    /** @var list<string> */
    private array $allowedKinds = [];

    /**
     * @param  array<array-key, string>  $kinds  e.g. ['entity', 'page', 'url']
     */
    public function allowedKinds(array $kinds): static
    {
        $this->allowedKinds = array_values($kinds);

        return $this;
    }

    /**
     * @return list<string>  empty means every registered kind is allowed
     */
    public function getAllowedKinds(): array
    {
        return $this->allowedKinds;
    }

    /**
     * @return array{kind: string, attributes: array<string, mixed>, new_tab: bool}|null
     */
    public function normalize(mixed $raw): ?array
    {
        $link = $this->linkFrom($raw);

        return $link?->toArray();
    }

    public function rules(): array
    {
        $allowed = $this->allowedKinds;

        return [
            'array',
            function (string $attribute, mixed $value, Closure $fail) use ($allowed): void {
                if (! is_array($value)) {
                    return;
                }

                $rawKind = $value['kind'] ?? null;
                $kind = is_string($rawKind) ? LinkKind::tryFrom($rawKind) : null;

                if ($kind === null) {
                    $fail((string) __('cms-blocks::validation.link.unknown_kind'));

                    return;
                }

                if ($allowed !== [] && ! in_array($kind->value, $allowed, true)) {
                    $fail((string) __('cms-blocks::validation.link.kind_not_allowed', ['kind' => $kind->value]));

                    return;
                }

                if (! self::isResolvable($kind)) {
                    $fail((string) __('cms-blocks::validation.link.unsupported_kind', ['kind' => $kind->value]));

                    return;
                }

                $attributes = $value['attributes'] ?? [];

                if (! is_array($attributes)) {
                    $fail((string) __('cms-blocks::validation.link.attributes_object'));

                    return;
                }

                self::validateTarget($kind, $attributes, $fail);
            },
        ];
    }

    public function toPayload(
        mixed $storedValue,
        string $locale,
        string $fallbackLocale,
        ?LinkResolverRegistry $linkResolver = null,
    ): mixed {
        $link = $this->linkFrom($storedValue);

        if ($link === null) {
            return null;
        }

        if ($linkResolver === null) {
            // No registry at hand: the kind survives, the address does not. A
            // public answer is never dropped over a link — the broken one is
            // visible in the payload instead.
            return (new ResolvedLink(
                kind: $link->kind,
                href: null,
                external: false,
                newTab: $link->newTab,
            ))->toArray();
        }

        return $linkResolver->resolve($link, $locale)->toArray();
    }

    /**
     * A kind is storable while something can build an address out of it. The
     * registry is asked where there is one; outside a booted application every
     * kind passes, because the model layer of this package works without a
     * container on purpose.
     */
    private static function isResolvable(LinkKind $kind): bool
    {
        $container = Container::getInstance();

        if (! $container->bound(LinkResolverRegistry::class)) {
            return true;
        }

        return $container->make(LinkResolverRegistry::class)->has($kind);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @param  Closure(string): void  $fail
     */
    private static function validateTarget(LinkKind $kind, array $attributes, Closure $fail): void
    {
        if (! self::hasTarget($kind, $attributes)) {
            $fail((string) __('cms-blocks::validation.link.target_required', [
                'target' => self::TARGETS[$kind->value],
                'kind' => $kind->value,
            ]));

            return;
        }

        if (! self::withinLimits($attributes, $fail)) {
            return;
        }

        switch ($kind) {
            case LinkKind::URL:
                $url = self::text($attributes['url'] ?? null);

                // `javascript:` goes to the navigation branch of the frontend and
                // `//host` leaves as an external link without protective attributes.
                if (preg_match('#^\s*(javascript:|//)#i', $url) === 1) {
                    $fail((string) __('cms-blocks::validation.link.url_scheme_rejected'));

                    return;
                }

                if (preg_match('#^https?://#i', $url) !== 1) {
                    $fail((string) __('cms-blocks::validation.link.url_scheme'));

                    return;
                }

                break;

            case LinkKind::EMAIL:
                $email = self::text($attributes['email'] ?? null);

                if (Validator::make(['email' => $email], ['email' => 'email'])->fails()) {
                    $fail((string) __('cms-blocks::validation.link.email'));

                    return;
                }

                break;

            case LinkKind::PHONE:
                if (preg_match(self::E164_PATTERN, self::text($attributes['phone'] ?? null)) !== 1) {
                    $fail((string) __('cms-blocks::validation.link.phone'));

                    return;
                }

                break;

            case LinkKind::ENTITY:
                if (! self::filled($attributes['type'] ?? null)) {
                    $fail((string) __('cms-blocks::validation.link.target_required', [
                        'target' => 'type',
                        'kind' => $kind->value,
                    ]));

                    return;
                }

                break;

            default:
                break;
        }

        self::validateAgainstResolver($kind, $attributes, $fail);
    }

    /**
     * What only the owner of the kind can answer: whether the route exists in
     * the manifest and whether the action is registered. A resolver that does
     * not care is not asked.
     *
     * A target that exists today and is deleted tomorrow is a different story
     * and stays out of the form: the link is then delivered with `href: null`,
     * because content must not break over a record that went away.
     *
     * @param  array<array-key, mixed>  $attributes
     * @param  Closure(string): void  $fail
     */
    private static function validateAgainstResolver(LinkKind $kind, array $attributes, Closure $fail): void
    {
        $container = Container::getInstance();

        if (! $container->bound(LinkResolverRegistry::class)) {
            return;
        }

        $resolver = $container->make(LinkResolverRegistry::class)->for($kind);

        if (! $resolver instanceof ValidatesLinkTarget) {
            return;
        }

        $problem = $resolver->problem(self::stringKeyed($attributes));

        if ($problem !== null) {
            $fail($problem);
        }
    }

    /**
     * Every stored string target of a link, with the address held to its own
     * ceiling: a link is a value of the content, and an unbounded one is a
     * value nothing downstream can hold.
     *
     * @param  array<array-key, mixed>  $attributes
     * @param  Closure(string): void  $fail
     */
    private static function withinLimits(array $attributes, Closure $fail): bool
    {
        foreach ($attributes as $name => $value) {
            if (! is_string($value)) {
                continue;
            }

            $limit = $name === 'url' ? self::URL_LIMIT : self::TARGET_LIMIT;

            if (mb_strlen($value) > $limit) {
                $fail((string) __('cms-blocks::validation.link.too_long', [
                    'target' => (string) $name,
                    'max' => $limit,
                ]));

                return false;
            }
        }

        $params = $attributes['params'] ?? null;

        if ($params !== null && ! is_array($params)) {
            $fail((string) __('cms-blocks::validation.link.params_object'));

            return false;
        }

        foreach (is_array($params) ? $params : [] as $name => $value) {
            if (is_string($value) && mb_strlen($value) > self::TARGET_LIMIT) {
                $fail((string) __('cms-blocks::validation.link.too_long', [
                    'target' => 'params.'.$name,
                    'max' => self::TARGET_LIMIT,
                ]));

                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private static function hasTarget(LinkKind $kind, array $attributes): bool
    {
        return self::filled($attributes[self::TARGETS[$kind->value]] ?? null);
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function filled(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return is_int($value) || is_float($value);
    }

    /**
     * A link whose target is empty is no link at all: an optional field left
     * untouched in the form arrives with a kind and nothing else, and storing
     * that would be storing an address that leads nowhere.
     */
    private function linkFrom(mixed $raw): ?Link
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $rawKind = $raw['kind'] ?? null;
        $kind = is_string($rawKind) ? LinkKind::tryFrom($rawKind) : null;

        if ($kind === null) {
            return null;
        }

        $attributes = $raw['attributes'] ?? [];

        if (! is_array($attributes) || ! self::hasTarget($kind, $attributes)) {
            return null;
        }

        return Link::fromArray(['kind' => $kind] + $raw);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $attributes): array
    {
        $keyed = [];

        foreach ($attributes as $name => $value) {
            $keyed[(string) $name] = $value;
        }

        return $keyed;
    }
}
