<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links;

/**
 * What the frontend receives for a link. An unreachable target keeps its kind
 * and carries `href: null` — a broken address is never handed out silently.
 */
final readonly class ResolvedLink
{
    /**
     * @param  array<string, mixed>|null  $action  hint about the state opened at this address
     */
    public function __construct(
        public LinkKind $kind,
        public ?string $href,
        public bool $external,
        public bool $newTab,
        public ?array $action = null,
    ) {
    }

    /**
     * @return array{kind: string, href: string|null, external: bool, new_tab: bool, action: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'href' => $this->href,
            'external' => $this->external,
            'new_tab' => $this->newTab,
            'action' => $this->action,
        ];
    }
}
