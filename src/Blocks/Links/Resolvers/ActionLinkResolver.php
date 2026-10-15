<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links\Resolvers;

use Ewk\Cms\Blocks\Links\Actions\ActionRegistry;
use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverContract;
use Ewk\Cms\Blocks\Links\ResolvedLink;
use Ewk\Cms\Blocks\Links\ValidatesLinkTarget;

/**
 * A named state of the frontend. The address is built by the declaration of the
 * action, and the `action` object rides along so that the component knows not
 * only where to lead but what happens there: an anchor cannot say that, which
 * is why this is a kind of its own.
 */
final readonly class ActionLinkResolver implements LinkResolverContract, ValidatesLinkTarget
{
    public function __construct(private ActionRegistry $actions)
    {
    }

    public function supports(LinkKind $kind): bool
    {
        return $kind === LinkKind::ACTION;
    }

    public function resolve(Link $link, string $locale): ResolvedLink
    {
        $name = self::name($link->attribute('action'));
        $params = self::params($link->attribute('params'));
        $action = $name === null ? null : $this->actions->get($name);

        return new ResolvedLink(
            kind: LinkKind::ACTION,
            href: $action?->href($params, $locale),
            external: false,
            newTab: $link->newTab,
            // The hint survives an address that could not be built: the block
            // then knows which button it is holding and can hide it knowingly.
            action: $name === null ? null : ['name' => $name, 'params' => $params],
        );
    }

    public function problem(array $attributes): ?string
    {
        $name = self::name($attributes['action'] ?? null);

        if ($name === null || $this->actions->has($name)) {
            return null;
        }

        return (string) __('cms-blocks::validation.link.unknown_action', ['action' => $name]);
    }

    private static function name(mixed $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return trim($raw);
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
