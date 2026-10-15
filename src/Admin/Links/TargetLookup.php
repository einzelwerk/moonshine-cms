<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Links;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Fields\EntityField;
use Ewk\Cms\Blocks\Fields\Field as BlockField;
use Ewk\Cms\Blocks\Fields\GroupField;
use Ewk\Cms\Blocks\Fields\RepeaterField;
use Ewk\Cms\Blocks\Links\Actions\ActionRegistry;
use Ewk\Cms\Blocks\Params\BooleanParam;
use Ewk\Cms\Blocks\Params\DateParam;
use Ewk\Cms\Blocks\Params\EnumParam;
use Ewk\Cms\Blocks\Params\Param;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Media\MoonShine\Library\MediaPreview;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PathPattern;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\MoonShineCms\Editor\BlockCard;
use Ewk\MoonShineCms\Editor\EditorTree;
use Ewk\MoonShineCms\MoonShine\Fields\EntityPicker;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Everything the link picker has to look up, in one place: what a page is
 * called, which records a class holds, which sections a page carries, what one
 * file of the library is called, which actions are registered and which
 * filters a catalogue declares.
 *
 * The same object serves both directions. The field asks it for the caption of
 * what is already stored, so a saved link opens with a name and not with a
 * key; the endpoints ask it for what the editor is typing. Two lookups would
 * be two answers to one question.
 */
final readonly class TargetLookup
{
    /**
     * How many rows one answer of a select carries. A link points at one
     * record; a list longer than this is a list nobody scrolls, and the search
     * is what narrows it.
     */
    public const int LIMIT = 50;

    public function __construct(
        private PageTypeRegistry $types,
        private BlockRegistryInterface $blocks,
        private EditorTree $tree,
        private ActionRegistry $actions,
        private Repository $config,
    ) {
    }

    /**
     * The pages a link may point at: active and not a template, because a
     * template answers a pattern and has no address of its own.
     *
     * @return array<string, string>  uuid => «name (address)»
     */
    public function pages(string $query = '', int $limit = self::LIMIT): array
    {
        $term = trim($query);

        try {
            $builder = CmsPage::query()->where('is_active', true);

            if ($term !== '') {
                $builder->where(static function (Builder $nested) use ($term): void {
                    $nested->where('name', 'like', '%'.$term.'%')
                        ->orWhere('path', 'like', '%'.$term.'%');
                });
            }

            /** @var list<CmsPage> $rows */
            $rows = $builder->orderBy('path')->limit($limit)->get()->all();
        } catch (Throwable) {
            // An installation without the page tree still edits blocks; an
            // empty select is a smaller problem than a broken card.
            return [];
        }

        $options = [];

        foreach ($rows as $page) {
            if ($this->isTemplate($page)) {
                continue;
            }

            $options[$page->uuid] = $this->pageCaptionOf($page);
        }

        return $options;
    }

    /**
     * The caption of one stored page, or null when it is gone: a link whose
     * target disappeared stays stored and broken.
     */
    public function pageCaption(mixed $uuid): ?string
    {
        $page = $this->page($uuid);

        return $page === null ? null : $this->pageCaptionOf($page);
    }

    /**
     * The records of the classes asked for, as one option list: the stored
     * value carries the type next to the key, so a field accepting a venue and
     * a concert needs no second select.
     *
     * @param  list<string>  $types
     * @return array<string, string>  packed value => caption
     */
    public function entities(array $types, string $query = '', int $limit = self::LIMIT): array
    {
        $options = [];

        foreach ($types as $type) {
            if ($this->allows($type)) {
                $options += EntityPicker::search($type, $query, $limit);
            }
        }

        return $options;
    }

    public function entityCaption(mixed $type, mixed $id): ?string
    {
        if (! is_string($type) || trim($type) === '' || (! is_string($id) && ! is_int($id))) {
            return null;
        }

        return EntityPicker::captionOf(trim($type), $id);
    }

    /**
     * The classes an editor may search from the panel: the subjects of the
     * page types, plus whatever the registered block schemas declare as
     * targets of their entity fields. A class outside that set is not a target
     * of this installation, and the endpoint says so instead of querying it.
     *
     * @return list<string>
     */
    public function entityTypes(): array
    {
        $allowed = [];

        foreach ($this->types->all() as $type) {
            $subject = $type->subject();

            if ($subject !== null) {
                $allowed[$subject->modelClass] = true;
            }
        }

        foreach (array_keys($this->blocks->all()) as $code) {
            try {
                $fields = $this->blocks->schemaFor($code)->fields();
            } catch (Throwable) {
                continue;
            }

            foreach (self::entityFields($fields) as $field) {
                foreach ($field->getTargets() as $target) {
                    $allowed[$target] = true;
                }
            }
        }

        return array_keys($allowed);
    }

    /**
     * The sections of one page: its blocks that carry an anchor, named the way
     * the card of the editor names them, «type: what the block says».
     *
     * @return array<string, string>  anchor => caption
     */
    public function anchors(mixed $uuid): array
    {
        $page = $this->page($uuid);

        if ($page === null) {
            return [];
        }

        $options = [];

        self::collectAnchors($this->tree->rootCards($page), $options);

        return $options;
    }

    /**
     * @return array{value: string, label: string, properties: array{image: string}}|null
     */
    public function file(mixed $uuid): ?array
    {
        if (! is_string($uuid) || trim($uuid) === '') {
            return null;
        }

        try {
            $media = CmsMedia::query()->where('uuid', trim($uuid))->first();
        } catch (Throwable) {
            return null;
        }

        return $media instanceof CmsMedia ? $this->fileOption($media) : null;
    }

    /**
     * The registered actions, named in words. An action declares its code and
     * its address and nothing else, so the caption comes from the translations
     * of the panel and falls back to the code where an installation has not
     * written one.
     *
     * @return array<string, string>
     */
    public function actionOptions(): array
    {
        $options = [];

        foreach ($this->actions->names() as $name) {
            $options[$name] = self::translated('moonshine-cms::links.actions.'.$name, $name);
        }

        return $options;
    }

    /**
     * The service addresses of the frontend: the editor picks an address and
     * never a route name.
     *
     * @return list<array{name: string, label: string, params: list<string>}>
     */
    public function routeOptions(): array
    {
        $manifest = $this->config->get('cms-pages.frontend_routes', []);

        if (! is_array($manifest)) {
            return [];
        }

        $routes = [];

        foreach ($manifest as $name => $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $pattern = PathPattern::fromString($path);

            $routes[] = [
                'name' => (string) $name,
                'label' => $path,
                'params' => $pattern->placeholders(),
            ];
        }

        return $routes;
    }

    public function routeCaption(mixed $name): ?string
    {
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        foreach ($this->routeOptions() as $route) {
            if ($route['name'] === trim($name)) {
                return $route['label'];
            }
        }

        return null;
    }

    /**
     * What a catalogue link may filter by: the lists standing on the target
     * page and the filtering parameters they declare, each with the values it
     * accepts.
     *
     * The anchor is asked for only where there is a choice: with one list the
     * resolver finds it on its own, see {@see \Ewk\Cms\Pages\Links\ListLinkResolver}.
     *
     * @return array{lists: list<array{anchor: string, label: string}>, filters: list<array{name: string, label: string, type: string, options: array<array-key, string>}>}
     */
    public function catalog(mixed $uuid): array
    {
        $page = $this->page($uuid);

        if ($page === null) {
            return ['lists' => [], 'filters' => []];
        }

        $cards = [];

        self::flatten($this->tree->rootCards($page), $cards);

        $lists = [];
        $filters = [];

        foreach ($cards as $card) {
            if ($card->anchor === null || $card->anchor === '' || ! $this->blocks->has($card->type)) {
                continue;
            }

            $schema = $this->blocks->schemaFor($card->type);

            if (! $schema->isDynamic()) {
                continue;
            }

            $declared = self::filtersOf($schema->params());

            if ($declared === []) {
                continue;
            }

            $lists[] = ['anchor' => $card->anchor, 'label' => self::cardCaption($card)];

            foreach ($declared as $filter) {
                $filters[$filter['name']] = $filter;
            }
        }

        return ['lists' => $lists, 'filters' => array_values($filters)];
    }

    /**
     * @param  list<BlockCard>  $cards
     * @param  array<string, string>  $options
     */
    private static function collectAnchors(array $cards, array &$options): void
    {
        foreach ($cards as $card) {
            if ($card->anchor !== null && $card->anchor !== '') {
                $options[$card->anchor] = self::cardCaption($card);
            }

            foreach ($card->slots as $slot) {
                self::collectAnchors($slot->children, $options);
            }
        }
    }

    /**
     * @param  list<BlockCard>  $cards
     * @param  list<BlockCard>  $flat
     */
    private static function flatten(array $cards, array &$flat): void
    {
        foreach ($cards as $card) {
            $flat[] = $card;

            foreach ($card->slots as $slot) {
                self::flatten($slot->children, $flat);
            }
        }
    }

    /**
     * The head of a card, exactly as the editor draws it: the type and what the
     * block says about itself.
     */
    private static function cardCaption(BlockCard $card): string
    {
        return $card->caption === ''
            ? $card->typeTitle
            : $card->typeTitle.': '.$card->caption;
    }

    /**
     * @param  list<Param>  $params
     * @return array<string, array{name: string, label: string, type: string, options: array<array-key, string>}>
     */
    private static function filtersOf(array $params): array
    {
        $filters = [];

        foreach ($params as $param) {
            if (! $param->isFilter()) {
                continue;
            }

            $filters[$param->name()] = [
                'name' => $param->name(),
                'label' => $param->getLabel() ?? $param->name(),
                'type' => self::paramType($param),
                'options' => self::paramOptions($param),
            ];
        }

        return $filters;
    }

    private static function paramType(Param $param): string
    {
        return match (true) {
            $param instanceof EnumParam, $param instanceof BooleanParam => 'options',
            $param instanceof DateParam => 'date',
            default => 'text',
        };
    }

    /**
     * The keys are the values the parameter accepts; php turns a numeric one
     * into an integer whatever is written here, and the form compares them as
     * strings.
     *
     * @return array<array-key, string>
     */
    private static function paramOptions(Param $param): array
    {
        if ($param instanceof BooleanParam) {
            // A yes or a no is a choice of two and never a word to type.
            $yes = (string) __('moonshine-cms::links.boolean.yes');
            $no = (string) __('moonshine-cms::links.boolean.no');

            return ['1' => $yes, '0' => $no];
        }

        if (! $param instanceof EnumParam) {
            return [];
        }

        return $param->getOptions();
    }

    /**
     * @param  list<BlockField>  $fields
     * @return list<EntityField>
     */
    private static function entityFields(array $fields): array
    {
        $found = [];

        foreach ($fields as $field) {
            if ($field instanceof EntityField) {
                $found[] = $field;

                continue;
            }

            if ($field instanceof GroupField) {
                array_push($found, ...self::entityFields($field->getFields()));
            }

            if ($field instanceof RepeaterField) {
                array_push($found, ...self::entityFields($field->getFields()));
            }
        }

        return $found;
    }

    /**
     * @return array{value: string, label: string, properties: array{image: string}}
     */
    private function fileOption(CmsMedia $media): array
    {
        return [
            'value' => $media->uuid,
            'label' => MediaPreview::fileName($media),
            'properties' => ['image' => MediaPreview::thumbnail($media)],
        ];
    }

    private function allows(string $type): bool
    {
        return in_array(trim($type), $this->entityTypes(), true);
    }

    private function page(mixed $uuid): ?CmsPage
    {
        if (! is_string($uuid) || trim($uuid) === '') {
            return null;
        }

        try {
            $page = CmsPage::query()->where('uuid', trim($uuid))->first();
        } catch (Throwable) {
            return null;
        }

        return $page instanceof CmsPage ? $page : null;
    }

    private function isTemplate(CmsPage $page): bool
    {
        return $this->types->has($page->type) && $this->types->get($page->type)->isTemplate();
    }

    private function pageCaptionOf(CmsPage $page): string
    {
        $name = Caption::of($page->getAttribute('name')) ?? $page->path;

        return (string) __('moonshine-cms::links.page_caption', ['name' => $name, 'path' => $page->path]);
    }

    private static function translated(string $key, string $fallback): string
    {
        $value = __($key);

        return is_string($value) && $value !== $key ? $value : $fallback;
    }
}
