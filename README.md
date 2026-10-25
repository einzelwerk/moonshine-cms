# MoonShine CMS

A block CMS for Laravel with an admin for [MoonShine 4](https://moonshine-laravel.com):
content blocks with a declared schema, a tree of pages with an address resolver,
layouts, headers, footers and menus, metadata with a sitemap feed, and the panel that
edits all of it. The site itself is a separate frontend: the package answers JSON,
describes it with OpenAPI, and never renders a public page.

Files and pictures live in [`ewk/moonshine-media`](https://github.com/einzelwerk/moonshine-media),
which this package requires: a block, a header or a card of metadata stores a reference
to a record of that library, and the panel uses its picker dialog and its fields.

Status: **0.x** — the API may still change between minor versions.

## Requirements

- PHP 8.4
- Laravel 13
- MoonShine 4.19+
- `ewk/moonshine-media` 0.1
- `zircote/swagger-php` 6, for the generated contract of the blocks
- MariaDB or SQLite are what the suites run on; MySQL and PostgreSQL are untested

## Installation

```bash
composer require ewk/moonshine-cms
php artisan migrate
```

The package is one install made of five parts, each with a service provider of its own.
All of them are discovered automatically, together with the two of `ewk/moonshine-media`:

| Part | Namespace | Provider | What it is |
|---|---|---|---|
| Blocks | `Ewk\Cms\Blocks` | `CmsBlocksServiceProvider` | Storage, schema and registry of block types, nesting, shared blocks, publication, typed links, dynamic blocks, the generated OpenAPI contract. |
| Pages | `Ewk\Cms\Pages` | `CmsPagesServiceProvider` | The tree of pages, page types, slugs, the address resolver and the endpoint of one block. |
| Layouts | `Ewk\Cms\Layouts` | `CmsLayoutsServiceProvider` | Layouts, headers, footers, menus and the chrome endpoint. |
| SEO | `Ewk\Cms\Seo` | `CmsSeoServiceProvider` | Metadata, the SEO document, canonical addresses, language versions, JSON-LD builders, the sitemap feed. |
| Admin | `Ewk\MoonShineCms` | `MoonShineCmsServiceProvider` | The MoonShine panel: the block editor, the sections of pages, shared blocks, layouts, headers, footers and menus, the link picker and the metadata form. |

The four core parts know nothing of MoonShine, and each depends only on the parts below
it: the pages, the layouts and the SEO part stand on the blocks, the SEO part on the
pages, and only the admin knows them all. The suite enforces this with architecture
tests.

Publishing is optional; every part keeps its own tags:

```bash
php artisan vendor:publish --tag=cms-blocks-config      # config/cms-blocks.php
php artisan vendor:publish --tag=cms-pages-config       # config/cms-pages.php
php artisan vendor:publish --tag=cms-layouts-config     # config/cms-layouts.php
php artisan vendor:publish --tag=cms-seo-config         # config/cms-seo.php
php artisan vendor:publish --tag=moonshine-cms-config   # config/moonshine-cms.php

php artisan vendor:publish --tag=cms-blocks-lang        # lang/vendor/cms-blocks
php artisan vendor:publish --tag=cms-pages-lang         # lang/vendor/cms-pages
php artisan vendor:publish --tag=cms-layouts-lang       # lang/vendor/cms-layouts
php artisan vendor:publish --tag=cms-seo-lang           # lang/vendor/cms-seo
php artisan vendor:publish --tag=moonshine-cms-lang     # lang/vendor/moonshine-cms
php artisan vendor:publish --tag=moonshine-cms-views    # resources/views/vendor/moonshine-cms

php artisan vendor:publish --tag=cms-blocks-migrations
php artisan vendor:publish --tag=cms-pages-migrations
php artisan vendor:publish --tag=cms-layouts-migrations
php artisan vendor:publish --tag=cms-seo-migrations
```

The tables are `cms_blocks`, `cms_shared_blocks`, `cms_versions`, `cms_pages`,
`cms_layouts`, `cms_headers`, `cms_footers`, `cms_menus`, `cms_menu_items`,
`cms_menu_slot_assignments` and `cms_metadata`. Every polymorphic column stores a morph
alias (`cms_block`, `cms_page`, `cms_header`…) and never a class name, so a model may be
renamed without a data migration.

## What it takes from `ewk/moonshine-media`

- **The content languages.** Every translatable value of the CMS is a dictionary of
  languages, read in one order: the language asked for, then the default one, then
  empty. The list of languages is `Ewk\Cms\Media\Localization\LanguageCatalog`. Its
  default reads `cms-media.locales` and `cms-media.default_locale`; an application that
  keeps its languages elsewhere binds its own implementation, and that binding wins
  whichever provider runs first.

  ```php
  // config/cms-media.php
  'locales' => ['ru', 'en', 'de'],
  'default_locale' => 'ru',
  ```

- **Pictures and files.** A media field of a block, a picture of a header, the Open Graph
  picture of a card and a link to a file all store a reference to a record of the
  library, and register it as a usage, so a file that content still shows cannot be
  deleted.
- **«Where this file is used».** The four core parts register resolvers in
  `Ewk\Cms\Media\Usages\UsageLabelRegistry`, which name a page, a shared block, a header,
  a footer or a card of metadata. The admin adds to
  `Ewk\Cms\Media\MoonShine\Library\UsageLinkRegistry` the resource that edits each of
  them and the caption of its kind of usage (`moonshine-cms::usages.*`), so the library
  links straight into the form that holds the file.
- **The panel.** The picture field of the block editor and of the metadata tab is the
  `MediaPicker` of the media library, and the link picker opens the same picker dialog.
  The «Медиа» section is registered by the media admin; `MoonShineCms::menuItems()`
  places it right after the shared blocks.

Every registration into the media library is an extender of a singleton
(`$this->app->extend(...)`), never a `make()` in `register()`. Composer lists discovered
packages by name, so the providers of this package run before the ones of
`ewk/moonshine-media`; an extender is applied whenever the registry is first resolved,
so the order of the providers does not matter.

## Blocks

### A block type

A block type is a class with a code and a schema. Five things are derived from the one
schema: the form of the panel, the validation rules, the stored shape, the payload of the
frontend and the OpenAPI description.

```php
use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Fields\RichTextField;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Schema\BlockSchema;

final class QuoteBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'quote';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title(__('blocks.quote.title'))
            ->category('content')
            ->fields([
                RichTextField::make('text')->translatable()->required(),
                TextField::make('author')->translatable(),
            ])
            ->placement(root: '*', parents: '*', shared: true);
    }
}
```

```php
// config/cms-blocks.php
'blocks' => [
    \Ewk\Cms\Blocks\Blocks\TextBlock::class,
    \Ewk\Cms\Blocks\Blocks\ColumnsBlock::class,
    \Ewk\Cms\Blocks\Blocks\TocBlock::class,
    \Ewk\Cms\Blocks\Blocks\SharedInsertionBlock::class,
    QuoteBlock::class,
],
```

The core ships four types: `text`, `columns` (a container on a grid of twelve), `toc`
(a table of contents built from the anchors of the page) and `shared` (an insertion of a
shared block). A block may override `resource()` with a resource of its own when its
payload is more than its fields.

The field dictionary is neutral and carries data only: text, textarea, rich text,
number, boolean, select and multi-select, date and date-time, colour, coordinates,
media, link, entity, group and repeater. A field may be `->translatable()`; the payload
carries the value of the requested language.

### Owners, drafts and publication

A model that holds blocks uses `Ewk\Cms\Blocks\Concerns\HasBlocks`. A block is a row of
`cms_blocks` with its own uuid, so it can be addressed, referenced and moved; children
live in named slots of a container, at most `cms-blocks.max_depth` levels deep, and every
nesting rule is checked on every write and not only in the form.

The live rows are the draft. `Ewk\Cms\Blocks\Actions\PublishOwner` writes the snapshot
the public answer reads (`cms_versions`), after checking what a draft may lack — the
required blocks of the owner, the minimum number of children of a slot — and refuses
with the full list of what stands in the way. `BlockRenderer::render()` reads the draft,
`renderPublished()` the snapshot; an owner without a snapshot or switched off answers
`null`.

A shared block (`cms_shared_blocks`) is a block of the library inserted on many pages.
It is an owner of exactly one root block, published like any other, and deleting it is
refused while it is still inserted somewhere.

### Typed links

A link stores the kind of its target and the attributes that identify it, never an
address: `url`, `email`, `phone`, `anchor`, `file` and `action` are resolved by the
blocks, `page`, `list`, `entity` and `route` by the pages. The resolved link carries
`href`, or `href: null` with the kind kept when the target is gone — a broken link is
visible rather than dropped. A kind no resolver stands behind is refused by the field
instead of being stored. Actions, such as «buy», are registered by the application:

```php
$this->app->extend(ActionRegistry::class, static function (ActionRegistry $actions, Container $app): ActionRegistry {
    $actions->register($app->make(BuyAction::class));

    return $actions;
});
```

### Dynamic blocks

A list block declares its query parameters, its pagination and its order beside its
fields. Parameter names are snake_case, like the rest of the public API.

```php
BlockSchema::make()
    ->fields([TextField::make('title')->translatable()])
    ->params([
        EnumParam::make('city')->options(CityOptionsProvider::class)->nullable()->filter(),
        StringParam::make('month')->pattern('^\d{4}-(0[1-9]|1[0-2])$')->nullable()->filter(),
    ])
    ->pagination(mode: PaginationMode::PAGES, perPage: 5, maxPerPage: 20, editable: true)
    ->sort('event_date', 'asc');
```

The parameters of the pagination itself are never listed in `params()`: the mode adds
them. `pages` gives `page` and `per_page`, `more` gives `shown` over the address of the
page or `cursor` over the endpoint of the block, `none` gives nothing. `filter()` marks a
parameter the visitor may change, which is what decides the canonical address of a page.
Where `pagination(modeEditable: true, allowedModes: [...])` says so, the editor picks the
mode of his own block; `per_page` and `pagination_mode` are the two non-field keys of
`content`, so no block type may declare a field under either name.

Parameters are namespaced by the anchor of the block, so two lists on one page never
share `page` or `shown`:

```
GET /api/v1/resolve?path=/tours/stand-up&b[raspisanie][page]=2&b[afisha][shown]=36
GET /api/v1/blocks/{uuid}?path=/tours/stand-up&page=2&month=2026-01
```

An undeclared parameter, or a whole set under an anchor the page does not carry, is
dropped; a declared one with a value it does not accept, `per_page` above the limit
included, answers 422; `page` and `shown` are clamped, never refused. The anchor of a
dynamic block is mandatory and generated from its code (`schedule`, `schedule-2`) when
the editor leaves it empty.

`pagination` and `filters` live inside the `data` of the block, marked by the mode, and
every address arrives ready, so the frontend assembles no query string:

```php
PaginationPayload::pages($parameters, $total, $resolved);
// {mode: 'pages', per_page, current_page, last_page, total, has_more, links: {self, next, prev}}

PaginationPayload::more($parameters, $total, $resolved, ['href' => '/events']);
// {mode: 'more', continuation: 'url', per_page, shown, total, has_more, next, catalog?}

PaginationPayload::moreByCursor($perPage, $nextCursor, $endpointHref);
// {mode: 'more', continuation: 'endpoint', per_page, has_more, next}

PaginationPayload::none();                                    // {mode: 'none'}
FiltersPayload::make('inline', $fields);                      // {mode, fields: {name: {value, options}}}
```

`has_more: true` with `next: null` means the list goes on but the ceiling of the block is
reached, and `catalog` is where to send the visitor instead. `PageQuery::encode()` is
the one query encoder of the CMS — keys sorted, brackets readable — so a pagination link
and a canonical address are the same string.

## Pages

### Page types

A page type is a declaration in code, registered by the application. Its cardinality says
where the address comes from: `MANY` pages of the tree, one `SINGLETON` at a fixed
address, or a `TEMPLATE` rendered for every entity of a model at the address its path
template builds.

```php
$this->app->extend(PageTypeRegistry::class, static function (PageTypeRegistry $types): PageTypeRegistry {
    $types->register(PageType::make('page')->title('Page'));

    $types->register(
        PageType::make('event')
            ->title('Event page')
            ->subjectTitle('Event')
            ->cardinality(Cardinality::TEMPLATE)
            ->subject(Subject::model(Event::class)->by('slug')->visible('publiclyVisible')->presenter(EventPresenter::class))
            ->path('/tours/{tour}/{event}')
            ->required(['hero', 'checkout'])
            ->starter(['hero', 'checkout', 'about', 'schedule']),
    );

    return $types;
});
```

The path template serves both directions: it parses an incoming address and builds the
canonical address of an entity (`SubjectPath`), so there is no second list of routes. A
slug is one strict kebab segment; a page whose address collides with a reserved prefix
or carries a language code is refused where it is set. The page model is configurable
(`cms-pages.model`), so an application extends `CmsPage` with what belongs to it.

### Endpoints

| Route | Name | What it answers |
|---|---|---|
| `GET <prefix>/resolve?path=…` | `cms.resolve` | The page behind an address, its subject, the blocks of the published snapshot, the languages of the catalog, plus whatever the contributors add — the layout and the metadata among them. |
| `GET <prefix>/blocks/{uuid}?path=…` | `cms.blocks.show` | The data of one dynamic block out of the same snapshot, throttled by `cms-pages.routes.block_throttle`. |

Both answer in the `{data: …}` envelope and carry `Vary: Accept-Language` on every
outcome. A path whose first segment is a language code answers 400 with an explanation
instead of a silent 404; a draft, an owner taken off publication and an unknown address
answer 404; a declared block parameter with a value it does not accept answers 422. The
language of the answer is the locale of the application, so a middleware of the
installation turns `Accept-Language` into it.

### Extension points

| What | Contract |
|---|---|
| The entity of a template page in the answer | `Ewk\Cms\Pages\Types\SubjectPresenter`, declared with `Subject::presenter()` |
| The answer of the resolver | `Ewk\Cms\Pages\Http\Resolve\ResolveContributor`, registered in `ResolveContributorRegistry` by an extender — this is how the layouts add the layout and the SEO part adds `metadata` and `json_ld` |
| A page view | the `Ewk\Cms\Pages\Events\PageResolved` event, fired once the document of a whole page is written |
| The subject inside a block of a template | `Ewk\Cms\Pages\Support\SubjectContext` (request attribute `cms.subject`) |

## Layouts, headers, footers and menus

| Route | Name | What it answers |
|---|---|---|
| `GET <prefix>/chrome` | `cms.chrome` | Every active layout, header and footer with the menus of their slots, in one document — the same for every visitor. |
| `GET <prefix>/menus/{code}` | `cms.menus.show` | One menu by code; a menu that is switched off answers exactly like one that does not exist. |

The fields of a header and of a footer are declared by the application with the very
field dictionary of the blocks, and so is the set of layouts — a code without a component
in the frontend is an unkept promise:

```php
$this->app->extend(ChromeSchemaRegistry::class, static function (ChromeSchemaRegistry $registry): ChromeSchemaRegistry {
    $registry->register(ChromeKind::FOOTER, ChromeSchema::make()->fields([
        MediaField::make('logo'),
        TextField::make('copyright')->translatable(),
    ]));

    return $registry;
});

$this->app->extend(LayoutCatalog::class, static function (LayoutCatalog $catalog): LayoutCatalog {
    $catalog->register(LayoutDefinition::make('default', 'Standard page', 'Header, footer, wide column'));
    $catalog->register(LayoutDefinition::make('text', 'Text page', 'Narrow column for long reading'));

    return $catalog;
});
```

The row of `cms_layouts` keeps what an editor changes — which header and footer, the
width of the container, the default flag. A page names a layout by code; the resolver
answers the layout the page names when it is active, otherwise the default one, and
`null` when there is no default at all.

Menus are put into slots of a header or a footer. The seven shipped slots are
`header_primary`, `header_secondary`, `footer_1`, `footer_2`, `footer_3`, `legal` and
`social`; `cms-layouts.menu_slots` adds more. A slot is a place in the markup of the
frontend, so the dictionary is closed and travels into the API description as an
enumeration. A menu item holds its own caption and a typed link; two levels are the whole
depth.

A menu can also be shown on a record of the application, the footer of a letter for one.
Such a place is declared together with its owner, is offered on the form of a menu and
never reaches the chrome document:

```php
$this->app->extend(MenuSlotCatalog::class, static function (MenuSlotCatalog $catalog): MenuSlotCatalog {
    $catalog->register(
        MenuSlotDefinition::make('mail_legal')
            ->label(static fn (): string => __('admin.mail_footer.slots.legal'))
            ->ownedBy(static fn (): Model => MailFooter::ensure()),
    );

    return $catalog;
});
```

`MenuSourceRegistry` holds the dictionaries a menu can be filled from by a button in the
panel; the package registers none.

## SEO

- **`cms_metadata`** — one row per publicly visible record: a translatable title and
  description, Open Graph and Twitter fields, the pictures as references to the media
  library (one per language), the indexing switches and a manual canonical address.
  Nothing is generated.
- **Fallbacks are computed when the document is handed out**, never written back: an
  empty title becomes the name of the record, Open Graph falls back to the plain values,
  Twitter to Open Graph, the picture to the cover. The description has no fallback on
  purpose. An owner answers through `ProvidesSeoFallbacks`, or the caller puts
  `fallbackTitle` and `fallbackImage` into the `SeoContext`.
- **The document has the shape of `Metadata` of Next** — `title`, `description`,
  `alternates`, `openGraph`, `twitter`, `robots` — so the frontend puts it into
  `generateMetadata` as it is. Page addresses are absolute and carry the language. The
  address of a picture is the one its disk hands out, completed with `app.url` when the
  disk answers with a bare path.
- **The canonical address points at itself**, the page number included; filtering
  parameters are excluded and the query string is written by `PageQuery::encode()`.
- **Language versions** are built from the languages of the catalog, with `x-default`
  on the default language.
- **JSON-LD** is a registry of builders the application fills; the package ships none.

```php
final class Tour extends Model implements ProvidesSeoFallbacks
{
    use \Ewk\Cms\Seo\Concerns\HasSeoMetadata;

    public function seoFallbackTitle(string $locale): ?string { /* … */ }

    public function seoFallbackImage(string $locale): ?string { /* … */ }
}

$this->app->extend(JsonLdRegistry::class, static function (JsonLdRegistry $registry, Container $app): JsonLdRegistry {
    $registry->register($app->make(EventJsonLdBuilder::class));

    return $registry;
});
```

The SEO part writes `metadata` and `json_ld` into the answer of `GET /resolve` through a
contributor; nothing has to be wired. The owner of the metadata is the subject of a
template address when there is one, and the page itself otherwise.

### The sitemap

`GET <prefix>/sitemap` (`cms.sitemap.index`) names the files, `GET <prefix>/sitemap/{id}`
(`cms.sitemap.show`) answers the entries of one in the shape of `MetadataRoute.Sitemap`
of Next — `url`, `lastModified`, `alternates.languages` — and the frontend writes the XML.
The sources are derived: the pages of the tree that open by their own address, and one
source per template type with the entities its visibility criterion lets through. A file
is one language and one source (`ru-event-2`); whatever an editor took out of the index is
left out; `lastModified` is the publication or a later edit of the metadata. An
installation narrows a type or adds a source of its own:

```php
$this->app->extend(SitemapRegistry::class, static function (SitemapRegistry $sitemap): SitemapRegistry {
    $sitemap->narrow('event', static fn (Builder $query) => $query->where('starts_at', '>=', now()->subDay()));
    $sitemap->register(new LandingSitemapSource());

    return $sitemap;
});
```

## OpenAPI

The endpoints carry their own `#[OA\Get]` attributes and the shapes of their answers live
in `OpenApi/Schemas` of each part. The contract of the blocks is generated out of the
registry of types by a processor of swagger-php, because the set of types is a runtime
fact; the slots of the menus are written the same way. With `darkaonline/l5-swagger`:

```php
// config/l5-swagger.php
'annotations' => [
    base_path('app'),
    base_path('vendor/ewk/moonshine-cms/src/Pages/Http'),
    base_path('vendor/ewk/moonshine-cms/src/Pages/OpenApi'),
    base_path('vendor/ewk/moonshine-cms/src/Layouts/Http'),
    base_path('vendor/ewk/moonshine-cms/src/Layouts/OpenApi'),
    base_path('vendor/ewk/moonshine-cms/src/Seo/Http'),
    base_path('vendor/ewk/moonshine-cms/src/Seo/OpenApi'),
],
'scanOptions' => [
    'processors' => [
        // By class name: the configuration has to survive `config:cache`.
        \Ewk\Cms\Blocks\OpenApi\CmsSchemasProcessor::class,
        \Ewk\Cms\Pages\OpenApi\SubjectSchemasProcessor::class,
        \Ewk\Cms\Pages\OpenApi\PageSecurityProcessor::class,
        \Ewk\Cms\Layouts\OpenApi\MenuSlotsProcessor::class,
    ],
],
```

Every operation of the package declares `security: []`. The resolver and the endpoint of
one block render the blocks of the installation, which may read the identity of the
visitor: `PageSecurityProcessor` replaces their `security` with
`cms-pages.openapi.security`, in the scheme names of the installation. A scheme the
document does not declare is a `LogicException`.

`src/Blocks/OpenApi` holds no attributes and is not scanned.

| Component | What it is |
|---|---|
| `CmsBlock` | `oneOf` of every registered type, `discriminator` on `code` |
| `CmsBlockChildren` | children of a container by slot, recursive on `CmsBlock` |
| `<Studly>Block`, `<Studly>BlockData` | the envelope (`uuid`, `code`, `variant`, `anchor`, `data`, `children`) and the payload of one type; parameters of a dynamic type travel as `x-cms-params` |
| `CmsMediaRef` / `CmsMedia` | the stored reference, and the hydrated picture |
| `CmsLink`, `CmsPagination*`, `CmsFilters` | a resolved link, the pagination by mode, the filter panel |
| `CmsError`, `CmsValidationError` | the refusals of every endpoint of the CMS |
| `CmsResolveResponse`, `CmsBlockResponse` | the answers of the pages |
| `CmsResolveSubject` | `BlockSubject` plus `slug`: the subject of a resolved address |
| `Chrome`, `ChromeLayout`, `ChromePart`, `ChromeMenu`, `ChromeMenuItem` | the chrome |
| `CmsMetadata`, `CmsJsonLd`, `CmsSitemapFile`, `CmsSitemapEntry` | the SEO part |
| `BlockSubject`, `BlockSubject<Studly>` | the entity of a template page: `oneOf` of one variant per template type, `discriminator` on `type` |
| `CmsSubjectData` | `anyOf` of the components the presenters describe; an installation may declare it itself |
| `CmsResolveExtension` | an open placeholder an installation replaces by declaring a component of the same name: the keys its own contributors write |

The `code` of every variant is a single-value enum, because a generated client narrows by
that literal and not by `discriminator`. A resource whose payload its fields cannot show
implements `DescribesPayloadSchema` with a static `payloadSchema()`. A presenter of a subject
implements `DescribesSubjectSchema` with a static `subjectSchema()` that names the component
of its `data`; a presenter that does not is described as an open object. A component name
already taken by another definition is a `LogicException`, not a merge.

```php
final readonly class TourPresenter implements SubjectPresenter, DescribesSubjectSchema
{
    public static function subjectSchema(): string
    {
        return 'TourListResource';
    }

    public function present(Model $subject, Request $request): array { /* … */ }
}
```

## The admin in MoonShine

The provider registers the resources of the CMS with the panel: pages, shared blocks,
layouts, headers, footers, menus, menu items and menu slots. They carry flat `Group`
attributes; an application that wants the sections as an editor reads them builds its
menu from `MoonShineCms::menuItems()` — the pages, the shared blocks, the «Медиа» section,
then a «Site appearance» group with the layouts, the headers, the footers and the menus:

```php
protected function menu(): array
{
    return [
        MenuGroup::make('Content', [...MoonShineCms::menuItems(config()), ...$itemsOfOtherModules]),
    ];
}
```

The resource of pages is configurable (`moonshine-cms.page_resource`), so an application
adds a tab of its own by naming a subclass; the package hands that class out wherever the
page resource is asked for.

### The block editor

Apply the concern to the MoonShine resource whose model uses `HasBlocks`, and register a
page that extends `Ewk\MoonShineCms\MoonShine\Pages\BlockEditorPage` among its pages. The
editor lives on a page of its own and not on a tab of the form: every card is a form, and
HTML allows no form inside another. `PageResource` with `PageBlocksPage` is the reference:

```php
final class LandingResource extends ModelResource
{
    use \Ewk\MoonShineCms\Concerns\InteractsWithBlockEditor;

    protected function pages(): array
    {
        return [LandingIndexPage::class, LandingFormPage::class, LandingBlocksPage::class];
    }
}
```

The concern brings the endpoints of the editor: `saveCmsBlock`, `moveCmsBlock`,
`deleteCmsBlock`, `insertCmsBlock`, `cmsBlockLibrary` and `publishCmsOwner`. Each card
saves through its own request with an optimistic lock, and every reorder or move goes
through `MoveBlock`, the one place the nesting rules are enforced.

- **The panel** above the cards holds the publication status and what stands in its way,
  the incomplete translations, one language switch for the whole editor, «save all»,
  «add block» and «publish». `BlockEditorPage::editorHint()` adds a rule of the owner an
  editor should read before running into it.
- **A card** is a drag handle, the glyph of the type, a caption taken from the first
  filled text of the content, badges, the activity toggle and a delete that asks first.
  The fields stand in the order the schema declares them; the name, the anchor and the
  switch are folded under «More». Slots of a container sit on a grid of twelve with a
  «+» each, and a card drags from the root into a slot and back.
- **The insertion library** opens on any «+» and answers with the tiles allowed at that
  exact point. A resource narrows the root level through
  `cmsNarrowLibrary(array $categories, Request $request)`; the server checks the
  insertion again either way.
- **Translatable fields** draw one input per language in the place the schema declares
  them, and the switch shows one; «required» belongs to the default language alone.
- **Rich text** renders as a textarea by default, because MoonShine 4.19 ships no editor;
  `moonshine-cms.rich_text_field` names the field class of one you install.

Shared blocks are a section of their own with the very same editor on the entry, a list
of where the entry is inserted, and the button that unlinks every insertion — the one way
out of a refused deletion.

### The link picker

A typed link is one field with one label. The first select asks where it leads, in words
an editor reads — «Site page», «Tour», «Catalogue with a filter», «Section of this page»,
«File», «Action», «External link»; the second step is a search over the pages, the
records of the chosen class, the sections of a page or the registered actions. A file is
chosen in the picker dialog of the media library. The entries of the first select come
from the registry of page types, so the package knows no class of the application.

The lookups are routes of the package inside the group of the panel, with its domain,
prefix, middleware and authentication:

| Route | Name | What it answers |
|---|---|---|
| `GET <panel>/cms/link/pages` | `moonshine.cms.link.pages` | active pages that are not templates |
| `GET <panel>/cms/link/entities` | `moonshine.cms.link.entities` | records of the classes asked for |
| `GET <panel>/cms/link/anchors` | `moonshine.cms.link.anchors` | the sections of one page |
| `GET <panel>/cms/link/actions` | `moonshine.cms.link.actions` | the registered actions, in words |
| `GET <panel>/cms/link/catalog` | `moonshine.cms.link.catalog` | the lists of a catalogue page and the values their filters accept |

The caption of an action is read from `moonshine-cms::links.actions.<code>` and falls back
to the code; an application lays its captions over `lang/vendor/moonshine-cms`.

### Entity forms

Pages, menus, menu items, headers, footers and shared blocks are drawn inside
`Components\LocaleScope`: one language switch above the fields, and a code or a slug
built from the name while the record is created and never rewritten by a rename.

- **A page** has three tabs: main, SEO and advanced. The tree of pages drags nodes between
  levels; the templates of the entity types are tiles under the tree, each with one button
  that assembles the template or opens its blocks.
- **Layouts** are configured, not created: the section edits what the catalog declares.
- **A menu** answers how it is called, where it is shown — the slots of the header and the
  footer and the places the application declared — and where its items come from. The
  items are a tree with drag and drop.
- **Headers and footers** carry the fields their kind declares and the table of their
  slots.

### The metadata form

Apply the concern to the resource of any publicly visible model and put the tab on its
form page. The tab carries a preview of the search snippet, a counter of the recommended
length on the title and the description, and the hint of what a fallback would put into
an empty field.

```php
final class TourFormPage extends FormPage
{
    use \Ewk\MoonShineCms\Concerns\InteractsWithSeoFields;

    protected function fields(): iterable
    {
        return [Tabs::make([Tab::make('Main', $this->mainFields()), $this->seoTab()])];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [...$this->seoRules()];
    }
}

final class TourResource extends ModelResource
{
    use \Ewk\MoonShineCms\Concerns\InteractsWithSeoFields;

    protected function afterSave(DataWrapperContract $item, FieldsContract $fields): DataWrapperContract
    {
        $this->saveSeo($item->getOriginal(), request()->all());

        return parent::afterSave($item, $fields);
    }
}
```

Every input of the tab lives under `seo[…]`, so a column of the edited record is never
shadowed. The page form of the package carries the tab already.

### Assets

The stylesheets and scripts of the admin are inlined by the components that need them,
so there is no build step and nothing to publish. The picture field and the picker dialog
bring the assets of `ewk/moonshine-media` themselves, and the block editor carries them
too, so a card with a picture inserted into an editor that had none works without a page
reload; identical inline assets are rendered once.

## Extension points

| What | How |
|---|---|
| Block types | `cms-blocks.blocks`, classes extending `AbstractBlock` |
| Actions of a link | Extend `Ewk\Cms\Blocks\Links\Actions\ActionRegistry` |
| Link kinds | Extend `Ewk\Cms\Blocks\Links\LinkResolverRegistry` with a `LinkResolverContract` |
| Page types | Extend `Ewk\Cms\Pages\Types\PageTypeRegistry` |
| The answer of the resolver | Extend `Ewk\Cms\Pages\Http\Resolve\ResolveContributorRegistry` |
| Fields of a header and a footer | Extend `Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry` |
| The layouts | Extend `Ewk\Cms\Layouts\Layouts\LayoutCatalog` |
| Places for menus on records of the application | `MenuSlotCatalog::register()` with a `MenuSlotDefinition` |
| Dictionaries a menu is filled from | Extend `Ewk\Cms\Layouts\Menus\MenuSourceRegistry` |
| Structured data | Extend `Ewk\Cms\Seo\JsonLd\JsonLdRegistry` |
| The sitemap | Extend `Ewk\Cms\Seo\Sitemap\SitemapRegistry` (`narrow()`, `register()`) |
| The content languages | Bind `Ewk\Cms\Media\Localization\LanguageCatalog`, or set `cms-media.locales` |
| The page model and the page resource | `cms-pages.model`, `moonshine-cms.page_resource` |
| What the block library offers at the root | `InteractsWithBlockEditor::cmsNarrowLibrary()` |

## Configuration

`config/cms-blocks.php`:

| Key | Default | What it is |
|---|---|---|
| `cms-blocks.blocks` | `[]` | The block type classes of the installation. |
| `cms-blocks.max_depth` | `2` | How deep containers may nest; the root is level 0. |
| `cms-blocks.pagination.default_per_page` | `10` | The portion of a dynamic list whose type declares none. |
| `cms-blocks.pagination.max_per_page` | `100` | The ceiling of `per_page`; a value above it answers 422. |
| `cms-blocks.pagination.max_shown` | `500` | The ceiling of the accumulator of `more`. |

`config/cms-pages.php`:

| Key | Default | What it is |
|---|---|---|
| `cms-pages.model` | `Ewk\Cms\Pages\Models\CmsPage` | The page model of the installation; a subclass of the package one. |
| `cms-pages.reserved_segments` | `[]` | Path prefixes a page may never occupy: technical prefixes of the backend and the routes of the frontend. Language codes come from the catalog and are not listed. |
| `cms-pages.frontend_routes` | `[]` | Named routes of the frontend with their path templates (`'auth.login' => '/auth/login'`), offered by the `route` kind of a link. |
| `cms-pages.routes.prefix` | `api/v1` | Where the two endpoints hang. |
| `cms-pages.routes.middleware` | `['api']` | The stack of the installation. |
| `cms-pages.routes.block_throttle` | `'60,1'` | The rate limit of the endpoint of one block. |
| `cms-pages.openapi.security` | `[]` | The security requirements of the resolver and the endpoint of one block in the contract, e.g. `[[], ['clientUuid' => []]]` for an optional identity. |

`config/cms-layouts.php`:

| Key | Default | What it is |
|---|---|---|
| `cms-layouts.menu_slots` | `[]` | Extra slots added to the seven shipped ones. |
| `cms-layouts.routes.prefix` | `api/v1` | Where the two endpoints hang. |
| `cms-layouts.routes.middleware` | `['api']` | The stack of the installation. |

`config/cms-seo.php`:

| Key | Default | What it is |
|---|---|---|
| `cms-seo.site_url` | `CMS_SITE_URL`, else `APP_URL` | The address of the public site, which is another host in a headless setup. |
| `cms-seo.locale_prefix` | `always` | `always` puts the language into every address, `never` into none. |
| `cms-seo.image_preset` | `null` | The media preset of the card picture; `null`, or a variant that is not ready, means the original. |
| `cms-seo.sitemap.per_file` | `10000` | Entries in one file of the sitemap, at most 50 000. |
| `cms-seo.sitemap.routes` | `api/v1`, `['api']` | `prefix` and `middleware` of the feed. The merge is shallow: an installation that sets `sitemap` sets the whole array. |

`config/moonshine-cms.php`:

| Key | Default | What it is |
|---|---|---|
| `moonshine-cms.rich_text_field` | `MoonShine\UI\Fields\Textarea` | The field a `RichTextField` renders as. |
| `moonshine-cms.entity_options_limit` | `100` | How many records the entity picker offers; it is a searchable select. |
| `moonshine-cms.block_icons` | the four shipped types | The icon of a block type, or of `code.variant`, out of the icon set of MoonShine. The key is merged whole, so a published file repeats the defaults. |
| `moonshine-cms.container_widths` | `['wide', 'narrow', 'full']` | The container widths a layout may take, captioned by `moonshine-cms::chrome.containers`. |
| `moonshine-cms.page_resource` | `Ewk\MoonShineCms\MoonShine\Resources\PageResource` | The resource of pages. |

The languages (`cms-media.locales`, `cms-media.default_locale`), the thumbnail preset and
the display time zone of the panel (`moonshine-media.thumbnail_preset`,
`moonshine-media.display_timezone`) belong to `ewk/moonshine-media`.

## Testing

```bash
composer install
composer test        # pest
composer phpstan     # level max
composer format:dry  # php-cs-fixer
```

Each part keeps its own suite under `tests/` with the providers it stands on
(`composer test:blocks`, `test:pages`, `test:layouts`, `test:seo`, `test:admin`), plus
`tests/Arch` for the layering rules. The suites run on SQLite in memory with foreign keys
enforced, and boot the providers in the order an application does.

## License

MIT, see [LICENSE](LICENSE).
