<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Fields;

use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Media\MoonShine\Fields\CompositeValueField;
use Ewk\Cms\Media\MoonShine\Library\MediaAssets;
use Ewk\Cms\Media\MoonShine\Library\PickerModal;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\MoonShineCms\Editor\EditorAssets;
use Ewk\MoonShineCms\Links\LinkChoice;
use Ewk\MoonShineCms\Links\LinkChoices;
use Ewk\MoonShineCms\Links\LinkEndpoints;
use Ewk\MoonShineCms\Links\TargetLookup;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * A typed link as one field with one label.
 *
 * The target is chosen in two steps and never typed. The first select asks
 * where the link leads, in words an editor reads: «Страница сайта», «Тур»,
 * «Каталог с фильтром», «Раздел этой страницы». The second step depends on
 * that answer and is a search over the pages, the records of the chosen class,
 * the sections of the page, the files of the library or the registered
 * actions; only the three plain targets — an external address, an e-mail and a
 * phone number — are typed, and each of them validates what it gets.
 *
 * `entity` is one kind and several entries of the first select: «Тур» and
 * «Событие» is what an editor picks between, see {@see LinkChoices}.
 *
 * The names of the inputs are the ones the storage shape asks for
 * (`kind`, `attributes[...]`, `new_tab`). Only the hidden input carrying the
 * kind is named on the server: inside a row of a repeater MoonShine rewrites
 * that name on the client, and everything else is named after it, so a row
 * renames itself completely by renaming one element.
 */
final class LinkPicker extends CompositeValueField
{
    protected string $view = 'moonshine-cms::fields.link-picker';

    /** @var list<string> */
    protected array $allowedKinds = [];

    /**
     * The page the block being edited belongs to, when it is one: the sections
     * of «this page» are its blocks, and an anchor inside the page itself is
     * stored without a page, exactly as the resolver expects, see
     * {@see \Ewk\Cms\Blocks\Links\Resolvers\AnchorLinkResolver}.
     */
    protected ?string $ownerPage = null;

    /**
     * @param  array<array-key, string>  $kinds  empty means every kind
     */
    public function allowedKinds(array $kinds): static
    {
        $this->allowedKinds = array_values($kinds);

        return $this;
    }

    public function forOwner(?Model $owner): static
    {
        $this->ownerPage = $owner instanceof CmsPage ? $owner->uuid : null;

        return $this;
    }

    /**
     * The kinds this field offers, in the order of the enum.
     *
     * @return list<LinkKind>
     */
    public function getKinds(): array
    {
        return array_values(array_filter(
            LinkKind::cases(),
            fn (LinkKind $kind): bool => $this->allowedKinds === []
                || in_array($kind->value, $this->allowedKinds, true),
        ));
    }

    /**
     * The entries of the first select, with the entity ones spelled out.
     *
     * @return list<LinkChoice>
     */
    public function getChoices(): array
    {
        return LinkChoices::build(array_map(
            static fn (LinkKind $kind): string => $kind->value,
            $this->getKinds(),
        ));
    }

    /**
     * The picker is drawn on the block editor, on the form of a menu item and
     * on the header alike; only the first of those loads the assets of the
     * editor on its own, so the field carries them too, and the library with
     * them: a link to a file is chosen in the same dialog a picture is. The
     * manager keeps one copy: identical inline assets are merged, see
     * {@see \MoonShine\AssetManager\AssetManager::add()}.
     *
     * @return list<\MoonShine\Contracts\AssetManager\AssetElementContract>
     */
    protected function assets(): array
    {
        return [...EditorAssets::inline(), ...MediaAssets::inline()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        $value = $this->getValue();
        $value = is_array($value) ? $value : [];

        $attributes = $value['attributes'] ?? [];
        $attributes = is_array($attributes) ? $attributes : [];

        $choices = $this->getChoices();
        $rawKind = $value['kind'] ?? null;
        $current = LinkChoices::match(
            $choices,
            is_string($rawKind) ? $rawKind : '',
            $attributes['type'] ?? null,
        );

        // A stored kind this field no longer offers falls back to the first
        // entry: the select shows what it will actually save.
        if (! $current instanceof LinkChoice) {
            $current = $choices[0] ?? null;
        }

        $lookup = self::lookup();

        return [
            'picker' => $this,
            'choices' => array_map(static fn (LinkChoice $choice): array => $choice->toArray(), $choices),
            'choice' => $current instanceof LinkChoice ? $current->id : '',
            'kind' => $current instanceof LinkChoice ? $current->kind : '',
            'entityType' => $current instanceof LinkChoice ? $current->entityType : null,
            'entityChoices' => array_values(array_map(
                static fn (LinkChoice $choice): array => $choice->toArray(),
                array_filter(
                    $choices,
                    static fn (LinkChoice $choice): bool => $choice->entityType !== null,
                ),
            )),
            'linkAttributes' => $attributes,
            'newTab' => (bool) ($value['new_tab'] ?? false),
            'ownerPage' => $this->ownerPage,
            'endpoints' => LinkEndpoints::all(),
            'selectedPage' => self::option(
                $attributes['page'] ?? null,
                $lookup?->pageCaption($attributes['page'] ?? null),
            ),
            'selectedEntity' => self::entityOption($lookup, $current, $attributes),
            // A link to a file is chosen in the library and never out of a list
            // of the last fifty uploads. The stored value stays the bare uuid
            // it has always been.
            'file' => self::fileValue($lookup?->file($attributes['file'] ?? null)),
            'mediaPicker' => self::pickerUrl(),
            'modal' => PickerModal::NAME,
            'modalMarkup' => $this->getAttribute('data-level') === null ? PickerModal::once() : '',
            'anchor' => self::text($attributes['anchor'] ?? null),
            'action' => self::text($attributes['action'] ?? null),
            'route' => self::text($attributes['route'] ?? null),
            'actions' => $lookup?->actionOptions() ?? [],
            'routes' => $lookup?->routeOptions() ?? [],
            'params' => self::pairs($attributes['params'] ?? null),
            // A catalogue link is a page plus the values of its filters, and
            // the anchor of the list they belong to, see
            // \Ewk\Cms\Pages\Links\ListLinkResolver.
            'filters' => self::pairs($attributes['filters'] ?? null),
        ];
    }

    /**
     * What the field draws for the file it already holds: nothing at all when
     * it holds none, and never a uuid on the screen.
     *
     * @param  array{value: string, label: string, properties: array{image: string}}|null  $option
     * @return array{value: string, label: string, image: string}
     */
    private static function fileValue(?array $option): array
    {
        return $option === null
            ? ['value' => '', 'label' => '', 'image' => '']
            : [
                'value' => $option['value'],
                'label' => $option['label'],
                'image' => $option['properties']['image'],
            ];
    }

    private static function pickerUrl(): string
    {
        try {
            return route('moonshine.cms.media.picker');
        } catch (Throwable) {
            // Outside a routed panel the picker still draws what it holds and
            // offers no way to change it.
            return '';
        }
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array{value: string, label: string}|null
     */
    private static function entityOption(?TargetLookup $lookup, ?LinkChoice $choice, array $attributes): ?array
    {
        if ($lookup === null || ! $choice instanceof LinkChoice || $choice->entityType === null) {
            return null;
        }

        $id = $attributes['id'] ?? null;

        if (! is_string($id) && ! is_int($id)) {
            return null;
        }

        $caption = $lookup->entityCaption($choice->entityType, $id);

        return $caption === null
            ? null
            : ['value' => EntityPicker::pack($choice->entityType, $id), 'label' => $caption];
    }

    /**
     * @return array{value: string, label: string}|null
     */
    private static function option(mixed $value, ?string $label): ?array
    {
        if (! is_string($value) || trim($value) === '' || $label === null) {
            return null;
        }

        return ['value' => trim($value), 'label' => $label];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * A stored dictionary — the parameters of a route, the filters of a
     * catalogue link — as a flat map the form fills in.
     *
     * @return array<string, string>
     */
    private static function pairs(mixed $pairs): array
    {
        if (! is_array($pairs)) {
            return [];
        }

        $values = [];

        foreach ($pairs as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $values[(string) $key] = (string) $value;
            }
        }

        return $values;
    }

    private static function lookup(): ?TargetLookup
    {
        try {
            return Container::getInstance()->make(TargetLookup::class);
        } catch (Throwable) {
            // Outside a booted installation — a schema read by a console
            // command — the picker still renders, with the stored values and
            // without their captions.
            return null;
        }
    }
}
