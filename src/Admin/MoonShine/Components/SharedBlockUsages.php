<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Components;

use DateTimeInterface;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Media\MoonShine\Support\DisplayTime;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\MoonShineCms\MoonShineCms;
use Ewk\MoonShineCms\Support\Translated;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use MoonShine\AssetManager\InlineCss;
use MoonShine\UI\Components\MoonShineComponent;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Url;
use Throwable;

/**
 * Where a library entry is inserted: the admin shows the usages and offers to
 * unlink them, because deleting an original that is still in use is refused.
 *
 * An owner that is a page of the tree is a link to its block editor, which is
 * where the insertion actually sits. An owner of another kind is named and
 * not linked: the package knows no admin address for it.
 *
 * The «unlink everything» button lives here and not beside the component: the
 * list is the one place that already knows whether there is anything to
 * unlink, so the form does not repeat the query to find out.
 */
final class SharedBlockUsages extends MoonShineComponent
{
    protected string $view = 'moonshine-cms::components.shared-block-usages';

    public function __construct(
        private readonly CmsSharedBlock $sharedBlock,
        private readonly ?string $unlinkUrl = null,
    ) {
        parent::__construct();
    }

    public static function forSharedBlock(CmsSharedBlock $sharedBlock, ?string $unlinkUrl = null): self
    {
        return new self($sharedBlock, $unlinkUrl);
    }

    /**
     * @return list<InlineCss>
     */
    protected function assets(): array
    {
        $css = file_get_contents(__DIR__.'/../../../../resources/css/shared-block-usages.css');

        return $css === false ? [] : [InlineCss::make($css)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        $usages = $this->usages();

        return [
            'count' => count($usages),
            'table' => $this->table($usages),
            'unlinkUrl' => $this->unlinkUrl,
        ];
    }

    /**
     * The standard table of the panel and no markup of its own: the rows say
     * where the entry is inserted, when that owner changed last and carry the
     * way in.
     *
     * @param  list<array{owner: string, updated: string, url: string}>  $usages
     */
    private function table(array $usages): TableBuilder
    {
        return TableBuilder::make([
            Text::make((string) __('moonshine-cms::shared.usage_owner'), 'owner'),
            Text::make((string) __('moonshine-cms::shared.usage_updated'), 'updated'),
            Url::make('', 'url')
                ->title(static fn (): string => (string) __('moonshine-cms::shared.usage_open'))
                ->customAttributes(['data-cms-usage-open' => 'true']),
        ], $usages)
            ->preview()
            ->simple()
            ->customAttributes(['data-cms-shared-usages-table' => 'true']);
    }

    /**
     * @return list<array{owner: string, updated: string, url: string}>
     */
    private function usages(): array
    {
        $usages = [];

        foreach ($this->sharedBlock->insertions()->with('blockable')->orderBy('id')->get() as $insertion) {
            $owner = $insertion->blockable;
            $url = $owner instanceof CmsPage ? $this->pageUrl($owner) : null;

            $usages[] = [
                'owner' => $this->ownerCaption($insertion, $owner),
                'updated' => $this->changedAt($owner),
                'url' => $url ?? '',
            ];
        }

        return $usages;
    }

    /**
     * When the owner changed last. A record without the timestamps says so
     * instead of showing an empty cell that reads as «never».
     */
    private function changedAt(?Model $owner): string
    {
        $changed = $owner?->getAttribute('updated_at');

        return $changed instanceof DateTimeInterface
            ? DisplayTime::local($changed)->format('d.m.Y H:i')
            : (string) __('moonshine-cms::shared.usage_never');
    }

    private function ownerCaption(CmsBlock $insertion, ?Model $owner): string
    {
        if (! $owner instanceof Model) {
            return (string) __('moonshine-cms::shared.usage_unknown_owner').' #'.$insertion->blockable_id;
        }

        if ($owner instanceof CmsPage) {
            $caption = Translated::caption($owner->getAttribute('name'));

            return $caption !== '' ? $caption.', '.$owner->path : $owner->path;
        }

        $caption = Translated::caption($owner->getAttribute('name'));

        return $caption !== '' ? $caption : $insertion->blockable_type.' #'.$insertion->blockable_id;
    }

    /**
     * The resource of pages is the one of the installation, see
     * `moonshine-cms.page_resource`. A panel that does not register it at all
     * leaves the row unlinked rather than failing the whole form.
     */
    private function pageUrl(CmsPage $page): ?string
    {
        try {
            $configured = MoonShineCms::pageResource($this->getCore()->getContainer(Repository::class));

            return $this->getCore()->getContainer($configured)->blockEditorPageUrl($page->id);
        } catch (Throwable) {
            return null;
        }
    }
}
