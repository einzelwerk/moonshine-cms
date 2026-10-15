<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Components;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Fields\FieldContext;
use Ewk\Cms\Blocks\Params\Pagination;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Media\MoonShine\Library\MediaAssets;
use Ewk\MoonShineCms\Adapters\FieldAdapter;
use Ewk\MoonShineCms\Editor\BlockCard;
use Ewk\MoonShineCms\Editor\EditorTree;
use Ewk\MoonShineCms\Editor\OwnerFieldContext;
use Ewk\MoonShineCms\Editor\PublicationStatusResolver;
use Ewk\MoonShineCms\Support\FixedFieldValues;
use Ewk\MoonShineCms\Support\ModelKey;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Eloquent\Model;
use MoonShine\AssetManager\InlineCss;
use MoonShine\AssetManager\InlineJs;
use MoonShine\Contracts\Core\CrudResourceContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\Collapse;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Modal;
use MoonShine\UI\Components\MoonShineComponent;
use MoonShine\UI\Fields\Hidden;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * The whole editor as one component. It fills a page of its own ({@see
 * \Ewk\MoonShineCms\MoonShine\Pages\BlockEditorPage}) and never a tab of the
 * resource form: a card is a form, and HTML has no nested forms.
 *
 * The table relation is gone: the core hands out the schema and the HTTP
 * commands, and this package builds the interface from them.
 *
 * Every card is a form of its own that posts to its own endpoint. There is no
 * single submit of the page: the structure is heterogeneous, the storage is row
 * per block, and one payload for a page with nested media becomes unliftable.
 *
 * The list lives inside a MoonShine fragment, so a successful command reloads
 * the editor — and with it the version markers of every card — without a page
 * reload and without hand written rendering on the client.
 */
final class BlockEditor extends MoonShineComponent
{
    public const FRAGMENT = 'cms-blocks';

    /**
     * The one modal of the editor: every «+» opens it with the address of its
     * own insertion point.
     */
    public const LIBRARY_MODAL = 'cms-library';

    /**
     * The sortable group every list and every slot of the editor belongs to:
     * one name is what lets a card be dragged from the root into a slot.
     */
    public const SORTABLE_GROUP = 'cms-blocks';

    protected string $view = 'moonshine-cms::components.block-editor';

    /**
     * The cards with their forms, built once, see {@see self::prepared()}.
     *
     * @var list<array{card: BlockCard, form: FormBuilder, slots: list<array{slot: \Ewk\MoonShineCms\Editor\SlotView, cards: list<array<string, mixed>>}>}>|null
     */
    private ?array $prepared = null;

    /** @var list<BlockCard>|null */
    private ?array $roots = null;

    /**
     * A rule of this owner an editor should read before running into it: it
     * belongs inside the panel of the editor and not above the page.
     */
    private ?string $hint = null;

    private ?\MoonShine\Contracts\UI\ComponentContract $lead = null;

    public function __construct(
        private readonly Model $owner,
        private readonly EditorTree $tree,
        private readonly PublicationStatusResolver $status,
        private readonly FieldAdapter $adapter,
        private readonly BlockRegistryInterface $registry,
        private readonly FieldContext $context,
        private readonly int $maxPerPage = 100,
    ) {
        parent::__construct();
    }

    public static function forOwner(Model $owner): self
    {
        $configured = app('config')->get('cms-blocks.pagination.max_per_page', 100);

        return new self(
            $owner,
            app(EditorTree::class),
            app(PublicationStatusResolver::class),
            app(FieldAdapter::class),
            app(BlockRegistryInterface::class),
            app(OwnerFieldContext::class)->for($owner),
            is_int($configured) && $configured > 0 ? $configured : 100,
        );
    }

    public function hint(?string $hint): self
    {
        $this->hint = $hint;

        return $this;
    }

    /**
     * What belongs to the record and not to the editor: the switch between the
     * settings and the blocks. It is drawn inside the editor and not above it
     * so that it shares one line with the language switch, exactly as it does
     * on the form of the same record.
     */
    public function lead(?\MoonShine\Contracts\UI\ComponentContract $lead): self
    {
        $this->lead = $lead;

        return $this;
    }

    /**
     * The head of one card as the editor draws it, for the answer of a save:
     * the caption, the badges and the actions are derived from the content and
     * change with it, while the form under them holds what the editor is still
     * typing. Replacing the head alone is what keeps a rich text editor of an
     * open card alive.
     */
    public static function cardHead(Model $owner, int $blockId, string $deleteUrl, string $fragmentEvent): ?string
    {
        $card = self::findCard(app(EditorTree::class)->rootCards($owner), $blockId);

        if (! $card instanceof BlockCard) {
            return null;
        }

        /** @var view-string $view */
        $view = 'moonshine-cms::components.block-editor-head';

        return app(ViewFactory::class)->make($view, [
            'card' => $card,
            'deleteUrl' => $deleteUrl,
            'fragmentEvent' => $fragmentEvent,
        ])->render();
    }

    /**
     * @param  list<BlockCard>  $cards
     */
    private static function findCard(array $cards, int $blockId): ?BlockCard
    {
        foreach ($cards as $card) {
            if ($card->id === $blockId) {
                return $card;
            }

            foreach ($card->slots as $slot) {
                $found = self::findCard($slot->children, $blockId);

                if ($found instanceof BlockCard) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * The look and the behaviour of the editor: one stylesheet written against
     * the theme variables of MoonShine, so light and dark need no second set
     * of rules, and one script that registers the Alpine component of the
     * editor. Both live in files of their own under `resources/` and are
     * inlined from there: the package ships no build step, and a published
     * asset would have to be republished after every change of a file the
     * application does not own.
     *
     * The script has to run before Alpine starts, and it does: the assets of a
     * component are collected while the layout is built and rendered into the
     * head, ahead of the module bundle that starts Alpine.
     *
     * The cards are built here as well, and that is the point of building
     * them here: a MoonShine field registers the assets it needs the moment
     * it is constructed, and the head of the layout is written before the
     * content of the page renders. Built inside `viewData()` the rich text
     * field of a card reached the asset manager after the tags were out, so
     * `tinymce` was never loaded and the card showed a bare textarea.
     *
     * @return list<\MoonShine\Contracts\AssetManager\AssetElementContract>
     */
    protected function assets(): array
    {
        // Building the cards is what registers the assets of their fields:
        // every MoonShine field hands its own to the manager as it is
        // constructed. The returned list is this component's own pair, plus
        // the assets of the picture fields.
        $this->prepared();

        $assets = [];
        $css = file_get_contents(__DIR__.'/../../../../resources/css/cms-editor.css');
        $js = file_get_contents(__DIR__.'/../../../../resources/js/cms-editor.js');

        if ($css !== false) {
            $assets[] = InlineCss::make($css);
        }

        if ($js !== false) {
            $assets[] = InlineJs::make($js);
        }

        // The picture fields of `ewk/moonshine-media` bring their stylesheet
        // and their Alpine components only when one is constructed. A block
        // with a picture inserted into an editor that had none redraws its
        // fragment without a new head, so the editor carries them always.
        // The asset manager keeps one copy of identical inline assets.
        return [...$assets, ...MediaAssets::inline()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        $catalog = app(LanguageCatalog::class);

        return [
            'fragmentName' => self::FRAGMENT,
            'fragmentEvent' => AlpineJs::event(JsEvent::FRAGMENT_UPDATED, self::FRAGMENT),
            'cards' => $this->prepared(),
            'cardIds' => $this->cardIds($this->rootCards()),
            'labels' => $this->labels(),
            'locales' => $catalog->locales(),
            'defaultLocale' => $catalog->defaultLocale(),
            'state' => $this->status->resolve($this->owner),
            'hint' => $this->hint,
            'lead' => $this->lead,
            'group' => self::SORTABLE_GROUP,
            'library' => $this->libraryModal(),
            // The fragment is redrawn after every command and the Alpine
            // scope with it, so which cards are open cannot live in it. The
            // key names the owner, so two pages do not share one state.
            'stateKey' => 'cms-editor:'.$this->owner->getMorphClass().':'.ModelKey::of($this->owner),
            'endpoints' => [
                // The library is fetched when the modal opens and never
                // rendered into the document: what may be inserted depends on
                // the slot and on the type of the owner, and both are known
                // only at that moment.
                'library' => $this->endpoint('cmsBlockLibrary'),
                'insert' => $this->endpoint('insertCmsBlock'),
                'move' => $this->endpoint('moveCmsBlock'),
                'remove' => $this->endpoint('deleteCmsBlock'),
                'publish' => $this->endpoint('publishCmsOwner'),
            ],
        ];
    }

    /**
     * The library is a modal of the panel and its content comes from the
     * server when it opens: the address carries the point the block is
     * inserted at, and `alwaysLoad` makes the next point load again instead of
     * showing the previous answer.
     */
    private function libraryModal(): Modal
    {
        return Modal::make(
            title: (string) __('moonshine-cms::editor.library_title'),
            asyncUrl: $this->endpoint('cmsBlockLibrary'),
        )
            ->name(self::LIBRARY_MODAL)
            ->wide()
            ->alwaysLoad();
    }

    /**
     * The words the client renders on its own: what «save all» reports is
     * counted in the browser, so blade cannot translate it.
     *
     * @return array<string, string>
     */
    private function labels(): array
    {
        return [
            'requestFailed' => (string) __('moonshine-cms::editor.request_failed'),
            'savedAll' => (string) __('moonshine-cms::editor.saved_all'),
            'nothingToSave' => (string) __('moonshine-cms::editor.nothing_to_save'),
        ];
    }

    /**
     * Every card of the tree, containers and their children alike: «expand
     * all» has to reach the nested ones too.
     *
     * @param  list<BlockCard>  $cards
     * @return list<int>
     */
    private function cardIds(array $cards): array
    {
        $ids = [];

        foreach ($cards as $card) {
            $ids[] = $card->id;

            foreach ($card->slots as $slot) {
                array_push($ids, ...$this->cardIds($slot->children));
            }
        }

        return $ids;
    }

    /**
     * The cards of the owner, read once: the tree is asked for by the assets
     * and by the view, and one editor draws one state of the storage.
     *
     * @return list<BlockCard>
     */
    private function rootCards(): array
    {
        return $this->roots ??= $this->tree->rootCards($this->owner);
    }

    /**
     * @return list<array{card: BlockCard, form: FormBuilder, slots: list<array{slot: \Ewk\MoonShineCms\Editor\SlotView, cards: list<array<string, mixed>>}>}>
     */
    private function prepared(): array
    {
        return $this->prepared ??= $this->withForms($this->rootCards());
    }

    /**
     * Each card with the form that saves it.
     *
     * @param  list<BlockCard>  $cards
     * @return list<array{card: BlockCard, form: FormBuilder, slots: list<array{slot: \Ewk\MoonShineCms\Editor\SlotView, cards: list<array<string, mixed>>}>}>
     */
    private function withForms(array $cards): array
    {
        $prepared = [];

        foreach ($cards as $card) {
            $slots = [];

            foreach ($card->slots as $slot) {
                $slots[] = [
                    'slot' => $slot,
                    'cards' => $this->withForms($slot->children),
                ];
            }

            $prepared[] = [
                'card' => $card,
                'form' => $this->form($card),
                'slots' => $slots,
            ];
        }

        return $prepared;
    }

    /**
     * The form of one card: the fields of the schema in the order they are
     * declared, and everything an editor needs only now and then folded away at
     * the end. An inserted shared block shows no fields of its own — they
     * belong to the original.
     *
     * The card no longer opens with a row of settings. The name is not typed
     * any more (the head of the card reads the content), the anchor is a
     * technical address and the activity switch has its own control in the
     * head, so all three live under «more».
     */
    private function form(BlockCard $card): FormBuilder
    {
        $fields = [
            Hidden::make('', 'block'),
            Hidden::make('', 'version'),
        ];

        if ($card->variant !== null || $card->variantTitle !== null) {
            $fields[] = Select::make((string) __('moonshine-cms::editor.variant'), 'variant')
                ->options($this->variantOptions($card))
                ->native();
        }

        if (! $card->shared && $card->known) {
            $schema = $this->adapter
                ->withLocaleSwitcher()
                ->form($this->registry->schemaFor($card->type), context: $this->context);

            foreach ($schema as $component) {
                $fields[] = $component;
            }
        }

        foreach ($this->paginationSettings($card) as $component) {
            $fields[] = $component;
        }

        $fields[] = Collapse::make(
            (string) __('moonshine-cms::editor.more'),
            $this->moreFields($card),
        )
            ->persist(false)
            ->customAttributes(['class' => 'cms-card__more']);

        return FormBuilder::make()
            ->name('cms-block-'.$card->id)
            ->fields($fields)
            ->fill($this->values($card))
            ->asyncMethod(
                'saveCmsBlock',
                page: $this->page(),
                resource: $this->resource(),
            )
            ->customAttributes([
                'class' => 'cms-card__form',
                // What «save all» walks: the identifier of the card and the
                // name it reports a refusal under.
                'data-cms-form' => (string) $card->id,
                'data-cms-name' => $card->caption !== '' ? $card->caption : $card->typeTitle,
            ])
            ->submit((string) __('moonshine-cms::editor.save'));
    }

    /**
     * What sits under «more»: the name an import or an older editor left
     * behind, the anchor of the block and its activity.
     *
     * @return list<\MoonShine\Contracts\UI\ComponentContract>
     */
    private function moreFields(BlockCard $card): array
    {
        $columns = [
            Column::make([Text::make((string) __('moonshine-cms::editor.name'), 'name')], 6),
            Column::make([
                Text::make((string) __('moonshine-cms::editor.anchor'), 'anchor')
                    ->hint((string) __('moonshine-cms::editor.anchor_hint')),
            ], 6),
        ];

        if ($card->switchable) {
            $columns[] = Column::make([
                Switcher::make((string) __('moonshine-cms::editor.is_active'), 'is_active')
                    ->customAttributes(['data-cms-active' => (string) $card->id]),
            ], 6);
        }

        return [Grid::make($columns)];
    }

    /**
     * The settings of the pagination engine, but only the ones the schema hands
     * to the editor. They are stored in `content` beside the fields and are not
     * fields themselves, which is why they are drawn here and not by the field
     * adapter.
     *
     * @return list<Column>
     */
    private function paginationSettings(BlockCard $card): array
    {
        $pagination = $this->paginationOf($card);

        if ($pagination === null) {
            return [];
        }

        $columns = [];

        if ($pagination->editable) {
            $limit = max(1, min($pagination->maxPerPage ?? $this->maxPerPage, $this->maxPerPage));

            $columns[] = Column::make([
                Number::make((string) __('moonshine-cms::editor.per_page'), 'content.per_page')
                    ->min(1)
                    ->max($limit)
                    ->hint((string) __('moonshine-cms::editor.per_page_hint', ['max' => $limit])),
            ], 3);
        }

        if ($pagination->modeEditable) {
            $columns[] = Column::make([
                Select::make((string) __('moonshine-cms::editor.pagination_mode'), 'content.pagination_mode')
                    ->options($this->modeOptions($pagination))
                    ->native(),
            ], 3);
        }

        return $columns;
    }

    private function paginationOf(BlockCard $card): ?Pagination
    {
        if ($card->shared || ! $card->known || ! $this->registry->has($card->type)) {
            return null;
        }

        return $this->registry->schemaFor($card->type)->pagination();
    }

    /**
     * What the editor may pick: the declared subset, or all three modes when
     * the schema names none.
     *
     * @return array<string, string>
     */
    private function modeOptions(Pagination $pagination): array
    {
        $modes = $pagination->allowedModes === [] ? PaginationMode::cases() : $pagination->allowedModes;
        $options = [];

        foreach ($modes as $mode) {
            $options[$mode->value] = (string) __('moonshine-cms::editor.pagination_modes.'.$mode->value);
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function values(BlockCard $card): array
    {
        return [
            'block' => $card->id,
            'version' => $card->version,
            'name' => $card->name,
            'anchor' => $card->anchor,
            'variant' => $card->variant,
            'is_active' => $card->isActive,
            'content' => $this->contentOf($card),
        ];
    }

    /**
     * The stored content, with the values this owner leaves no choice about
     * put in place: their hidden inputs carry what the block will be saved
     * with and not what an earlier owner, or a default of the declaration,
     * left behind, see {@see FixedFieldValues}.
     *
     * @return array<array-key, mixed>
     */
    private function contentOf(BlockCard $card): array
    {
        if ($card->shared || ! $card->known || ! $this->registry->has($card->type)) {
            return $card->content;
        }

        return FixedFieldValues::of($this->registry->schemaFor($card->type), $this->context) + $card->content;
    }

    /**
     * @return array<string, string>
     */
    private function variantOptions(BlockCard $card): array
    {
        if (! $this->registry->has($card->type)) {
            return [];
        }

        $options = [];

        foreach ($this->registry->schemaFor($card->type)->variants() as $code => $title) {
            $options[(string) $code] = $title;
        }

        return $options;
    }

    private function endpoint(string $method): string
    {
        return $this->getCore()->getRouter()->getEndpoints()->method(
            $method,
            params: ['resourceItem' => ModelKey::of($this->owner)],
            page: $this->page(),
            resource: $this->resource(),
        );
    }

    private function page(): PageContract
    {
        return $this->getCore()->getCrudRequest()->getPage();
    }

    private function resource(): ?CrudResourceContract
    {
        $request = $this->getCore()->getCrudRequest();

        return $request->hasResource() ? $request->getResource() : null;
    }
}
