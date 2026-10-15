<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Chrome;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Localization\LocalizedValueResolver;
use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Layouts\Models\CmsLayout;
use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\Cms\Layouts\Models\CmsMenuItem;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Contracts\Translation\Translator;
use stdClass;

/**
 * Everything around the content in one document: all active layouts, headers,
 * footers and every menu reachable through a slot of one of them.
 *
 * The service is the whole of it; the HTTP route (`GET /api/v1/chrome`) lives
 * in the application, the same way the resolver of the pages package does.
 *
 * **The document is the same for every visitor.** It is built from a language
 * and from the database, and it takes neither a request, nor a user, nor a
 * session — there is no such concept in this package at all. That is not an
 * omission: a personal chrome would turn a response shared by thousands of
 * pages into a response cached per token. Personal entries such as "profile" or
 * "my orders" are frontend components.
 *
 * There is no draft either: an edit of a menu, a header or a footer is visible
 * at once, and the lever is the `is_active` flag.
 *
 * A menu item whose target cannot be resolved is delivered with `href: null`
 * and `broken: true`. It is neither dropped nor thrown: a broken link is a fact
 * for the editor, and silently hiding it is how an editor stops trusting the
 * menu.
 */
final readonly class ChromeDocument
{
    public function __construct(
        private ChromeSchemaRegistry $schemas,
        private ChromeContent $content,
        private LinkResolverRegistry $linkResolvers,
        private LanguageCatalog $languages,
        private MenuSlotCatalog $slots,
        private Translator $translator,
    ) {
    }

    /**
     * @param  string|null  $locale  the requested content language; the application locale by default, because resolving `Accept-Language` is middleware work
     * @return array{
     *     locale: string,
     *     layouts: list<array{code: string, name: string, container: string|null, body_class: string|null, is_default: bool, header: string|null, footer: string|null}>,
     *     headers: list<array{code: string, name: string, content: array<string, mixed>, slots: array<string, list<string>>|stdClass}>,
     *     footers: list<array{code: string, name: string, content: array<string, mixed>, slots: array<string, list<string>>|stdClass}>,
     *     menus: list<array{code: string, name: string, source: string, items: list<array<string, mixed>>}>
     * }
     */
    public function build(?string $locale = null): array
    {
        $locale ??= $this->translator->getLocale();
        $fallbackLocale = $this->languages->defaultLocale();

        $headers = CmsHeader::query()
            ->where('is_active', true)
            ->with('menuSlots')
            ->orderBy('id')
            ->get();

        $footers = CmsFooter::query()
            ->where('is_active', true)
            ->with('menuSlots')
            ->orderBy('id')
            ->get();

        $menus = $this->menusOf(array_values($headers->all()), array_values($footers->all()));
        $this->preloadTargets($menus);

        /** @var array<int, string> $menuCodeById */
        $menuCodeById = [];

        foreach ($menus as $menu) {
            $menuCodeById[$menu->id] = $menu->code;
        }

        $headerCodes = [];
        foreach ($headers as $header) {
            $headerCodes[$header->id] = $header->code;
        }

        $footerCodes = [];
        foreach ($footers as $footer) {
            $footerCodes[$footer->id] = $footer->code;
        }

        $layouts = [];
        foreach ($this->activeLayouts() as $layout) {
            $layouts[] = [
                'code' => $layout->code,
                'name' => LocalizedValueResolver::resolve($layout->name, $locale, $fallbackLocale),
                'container' => $layout->container,
                'body_class' => $layout->body_class,
                'is_default' => $layout->is_default,
                // A header or footer that is switched off is not in the
                // document, so the layout does not point at it either.
                'header' => $layout->cms_header_id === null ? null : ($headerCodes[$layout->cms_header_id] ?? null),
                'footer' => $layout->cms_footer_id === null ? null : ($footerCodes[$layout->cms_footer_id] ?? null),
            ];
        }

        $headerEntries = [];
        foreach ($headers as $header) {
            $headerEntries[] = $this->chromeEntry($header, $menuCodeById, $locale, $fallbackLocale);
        }

        $footerEntries = [];
        foreach ($footers as $footer) {
            $footerEntries[] = $this->chromeEntry($footer, $menuCodeById, $locale, $fallbackLocale);
        }

        $menuEntries = [];
        foreach ($menus as $menu) {
            $menuEntries[] = $this->menuEntry($menu, $locale, $fallbackLocale);
        }

        return [
            'locale' => $locale,
            'layouts' => $layouts,
            'headers' => $headerEntries,
            'footers' => $footerEntries,
            'menus' => $menuEntries,
        ];
    }

    /**
     * One menu by its code, the answer of `GET /api/v1/menus/{code}`. Null
     * means there is no such active menu.
     *
     * @return array{code: string, name: string, source: string, items: list<array<string, mixed>>}|null
     */
    public function menu(string $code, ?string $locale = null): ?array
    {
        $locale ??= $this->translator->getLocale();

        $menu = CmsMenu::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->with('activeItems')
            ->first();

        if (! $menu instanceof CmsMenu) {
            return null;
        }

        $this->preloadTargets([$menu]);

        return $this->menuEntry($menu, $locale, $this->languages->defaultLocale());
    }

    /**
     * @return list<CmsLayout>
     */
    private function activeLayouts(): array
    {
        return array_values(CmsLayout::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all());
    }

    /**
     * The menus a header or a footer actually shows: reached through an
     * enabled slot row, sitting in a place of the chrome this installation
     * knows, and active themselves. A place an installation declared for a
     * record of its own, the footer of a letter, is not a place of the chrome
     * even when a row hangs it on a footer by mistake, see
     * {@see MenuSlotCatalog::chromeCodes()}.
     *
     * @param  list<CmsHeader>  $headers
     * @param  list<CmsFooter>  $footers
     * @return list<CmsMenu>
     */
    private function menusOf(array $headers, array $footers): array
    {
        $menuIds = [];

        foreach ([...$headers, ...$footers] as $owner) {
            foreach ($owner->menuSlots as $assignment) {
                if ($assignment->is_enabled && $this->slots->isChromeSlot($assignment->slot)) {
                    $menuIds[$assignment->cms_menu_id] = true;
                }
            }
        }

        if ($menuIds === []) {
            return [];
        }

        return array_values(CmsMenu::query()
            ->whereIn('id', array_keys($menuIds))
            ->where('is_active', true)
            ->with('activeItems')
            ->orderBy('code')
            ->get()
            ->all());
    }

    /**
     * Every target of every item handed to the resolvers before a single one
     * is resolved, see {@see LinkResolverRegistry::preload()}: the items of a
     * chrome point at a handful of pages, and a resolver able to read them in
     * bulk reads them in one query instead of one per item. The answer is the
     * same either way.
     *
     * @param  list<CmsMenu>  $menus
     */
    private function preloadTargets(array $menus): void
    {
        $links = [];

        foreach ($menus as $menu) {
            foreach ($menu->activeItems as $item) {
                $link = $item->hasTarget() ? $item->toLink() : null;

                if ($link !== null) {
                    $links[] = $link;
                }
            }
        }

        $this->linkResolvers->preload($links);
    }

    /**
     * The content of a header or a footer built exactly the way a block
     * payload is: every declared field converts its own stored value.
     *
     * @param  array<int, string>  $menuCodeById  codes of the menus this document delivers
     * @return array{code: string, name: string, content: array<string, mixed>, slots: array<string, list<string>>|stdClass}
     */
    private function chromeEntry(CmsHeader|CmsFooter $record, array $menuCodeById, string $locale, string $fallbackLocale): array
    {
        $slots = [];

        foreach ($record->menuSlots as $assignment) {
            $code = $menuCodeById[$assignment->cms_menu_id] ?? null;

            if (! $assignment->is_enabled || $code === null || ! $this->slots->isChromeSlot($assignment->slot)) {
                continue;
            }

            $slots[$assignment->slot][] = $code;
        }

        return [
            'code' => $record->code,
            'name' => LocalizedValueResolver::resolve($record->name, $locale, $fallbackLocale),
            'content' => $this->content->toPayload(
                is_array($record->content) ? $record->content : [],
                $this->schemas->schemaFor($record->chromeKind()),
                $locale,
                $fallbackLocale,
                $this->linkResolvers,
            ),
            // An object and not an empty array: the frontend reads `slots` as
            // a map of slot code to menu codes, and an empty PHP array would
            // encode as `[]`.
            'slots' => $slots === [] ? new stdClass() : $slots,
        ];
    }

    /**
     * @return array{code: string, name: string, source: string, items: list<array<string, mixed>>}
     */
    private function menuEntry(CmsMenu $menu, string $locale, string $fallbackLocale): array
    {
        /** @var array<int, list<CmsMenuItem>> $byParent */
        $byParent = [];

        foreach ($menu->activeItems as $item) {
            $byParent[$item->parent_id ?? 0][] = $item;
        }

        $items = [];

        foreach ($byParent[0] ?? [] as $root) {
            $items[] = $this->menuItem($root, $byParent[$root->id] ?? [], $locale, $fallbackLocale);
        }

        return [
            'code' => $menu->code,
            'name' => LocalizedValueResolver::resolve($menu->name, $locale, $fallbackLocale),
            'source' => $menu->source,
            'items' => $items,
        ];
    }

    /**
     * @param  list<CmsMenuItem>  $children  the single level of children a menu has; their own children cannot exist, see {@see CmsMenuItem::MAX_DEPTH}
     * @return array{label: string, link: array<string, mixed>|null, broken: bool, icon: string|null, variant: string|null, children: list<array<string, mixed>>}
     */
    private function menuItem(CmsMenuItem $item, array $children, string $locale, string $fallbackLocale): array
    {
        $childEntries = [];

        foreach ($children as $child) {
            $childEntries[] = $this->menuItem($child, [], $locale, $fallbackLocale);
        }

        [$link, $broken] = $this->resolveTarget($item, $locale);

        return [
            'label' => LocalizedValueResolver::resolve($item->label, $locale, $fallbackLocale),
            'link' => $link,
            'broken' => $broken,
            'icon' => $item->icon,
            'variant' => $item->variant,
            'children' => $childEntries,
        ];
    }

    /**
     * An item carries no target (a group caption of a footer column), or a
     * target that resolves, or a target that does not. Only the last one is
     * broken; the item is delivered in every case.
     *
     * @return array{0: array<string, mixed>|null, 1: bool}
     */
    private function resolveTarget(CmsMenuItem $item, string $locale): array
    {
        if (! $item->hasTarget()) {
            return [null, false];
        }

        $link = $item->toLink();

        if ($link === null) {
            // A stored kind this installation no longer knows: there is a
            // target, it simply leads nowhere any more.
            return [null, true];
        }

        $resolved = $this->linkResolvers->resolve($link, $locale);

        return [$resolved->toArray(), $resolved->href === null];
    }
}
