<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Links;

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverContract;
use Ewk\Cms\Blocks\Links\ResolvedLink;
use Ewk\Cms\Blocks\Links\ValidatesLinkTarget;
use Ewk\Cms\Pages\Support\PathPattern;
use Illuminate\Contracts\Config\Repository;

/**
 * A route of the frontend behind which there is neither a page nor an entity:
 * login, the cabinet, an order. Without this kind such an address would have to
 * be stored as an external one, which is the very string typed links exist to
 * avoid.
 *
 * The names and the templates come from the non content half of the route
 * manifest, and that half is a configuration of this package: the content half
 * is already here, in the declarations of the page types, and keeping the two
 * halves of one manifest in two packages would be keeping two lists.
 *
 * The package ships an empty list: it knows neither the frontend of an
 * installation nor its deployment, exactly as with the reserved segments right
 * above it in the same file.
 */
final readonly class RouteLinkResolver implements LinkResolverContract, ValidatesLinkTarget
{
    public function __construct(private Repository $config)
    {
    }

    public function supports(LinkKind $kind): bool
    {
        return $kind === LinkKind::ROUTE;
    }

    public function resolve(Link $link, string $locale): ResolvedLink
    {
        return new ResolvedLink(
            kind: LinkKind::ROUTE,
            href: $this->href(
                self::name($link->attribute('route')),
                self::params($link->attribute('params')),
            ),
            external: false,
            newTab: $link->newTab,
        );
    }

    public function problem(array $attributes): ?string
    {
        $name = self::name($attributes['route'] ?? null);

        if ($name === null) {
            return null;
        }

        $pattern = $this->pattern($name);

        if ($pattern === null) {
            return (string) __('cms-pages::links.unknown_route', ['route' => $name]);
        }

        $missing = self::missing($pattern, self::params($attributes['params'] ?? null));

        return $missing === []
            ? null
            : (string) __('cms-pages::links.missing_route_params', [
                'route' => $name,
                'params' => implode(', ', $missing),
            ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function href(?string $name, array $params): ?string
    {
        if ($name === null) {
            return null;
        }

        $pattern = $this->pattern($name);

        if ($pattern === null) {
            return null;
        }

        $values = [];

        foreach ($pattern->placeholders() as $placeholder) {
            $value = $params[$placeholder] ?? null;

            if (! is_scalar($value) || (string) $value === '') {
                return null;
            }

            $values[$placeholder] = (string) $value;
        }

        return $pattern->build($values);
    }

    private function pattern(string $name): ?PathPattern
    {
        $routes = $this->config->get('cms-pages.frontend_routes', []);

        if (! is_array($routes)) {
            return null;
        }

        $path = $routes[$name] ?? null;

        return is_string($path) && $path !== '' ? PathPattern::fromString($path) : null;
    }

    /**
     * The placeholders of the template the link carries no value for. An
     * optional route parameter does not exist: a template names a segment, and
     * a segment is either there or the address is a different one.
     *
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    private static function missing(PathPattern $pattern, array $params): array
    {
        $missing = [];

        foreach ($pattern->placeholders() as $placeholder) {
            $value = $params[$placeholder] ?? null;

            if (! is_scalar($value) || (string) $value === '') {
                $missing[] = $placeholder;
            }
        }

        return $missing;
    }

    private static function name(mixed $raw): ?string
    {
        return is_string($raw) && trim($raw) !== '' ? trim($raw) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function params(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $params = [];

        foreach ($raw as $name => $value) {
            $params[(string) $name] = $value;
        }

        return $params;
    }
}
