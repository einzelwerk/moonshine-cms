<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Pages;

use Ewk\MoonShineCms\Contracts\EditsBlocksContract;
use Ewk\MoonShineCms\MoonShine\Components\BlockEditor;
use Ewk\MoonShineCms\Support\ModelKey;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\CrudResourceContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\Components\Fragment;
use MoonShine\Crud\Exceptions\NotFoundException;
use MoonShine\Laravel\Pages\Page;
use RuntimeException;

/**
 * The block editor of one item, on a page of its own.
 *
 * It used to be a tab of the resource form, and that was the bug: a card of
 * the editor is a form, the resource form is a form, and HTML has no nested
 * forms. The browser dropped the opening `<form>` of the first card — its
 * inputs joined the page form and lost the Alpine scope that drives
 * conditional fields — and closed the outer form at the first `</form>` it
 * met. Everything below behaved as if it were outside a form.
 *
 * A page of its own has no outer form, so every card keeps its own.
 *
 * @template TResource of CrudResourceContract = CrudResourceContract
 *
 * @extends Page<TResource>
 */
abstract class BlockEditorPage extends Page
{
    /**
     * The address names an item; without one there is nothing to edit and the
     * address is wrong. Checked before anything renders, the way every crud
     * page of MoonShine does it.
     */
    protected function prepareBeforeRender(): void
    {
        parent::prepareBeforeRender();

        if (! $this->blockResource()->getItem() instanceof Model) {
            $this->throw404();
        }
    }

    /**
     * The page is called by what it edits; that it holds the blocks of it is
     * the subtitle. No dash glues the two together.
     */
    public function getTitle(): string
    {
        return $this->ownerCaption($this->owner());
    }

    public function getSubtitle(): string
    {
        return (string) __('moonshine-cms::editor.title');
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        $resource = $this->blockResource();
        $owner = $this->owner();

        return [
            $resource->getIndexPageUrl() => $resource->getTitle(),
            $resource->getFormPageUrl(ModelKey::of($owner)) => $this->ownerCaption($owner),
            '#' => (string) __('moonshine-cms::editor.title'),
        ];
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        return [
            Fragment::make([
                BlockEditor::forOwner($this->owner())
                    ->hint($this->editorHint())
                    ->lead($this->blockResource()->blockEditorSwitcher(true)),
            ])->name(BlockEditor::FRAGMENT),
        ];
    }

    /**
     * A rule of this owner an editor should read before running into it. It is
     * drawn inside the panel of the editor, so nothing floats above the
     * «settings | blocks» switch.
     */
    protected function editorHint(): ?string
    {
        return null;
    }

    /**
     * How the owner is called in the title and the breadcrumbs: a page is
     * known by its name, another owner by something else entirely.
     */
    abstract protected function ownerCaption(Model $owner): string;

    /**
     * The item the address names. Blocks belong to a row: without one there is
     * nothing to edit and the address is wrong.
     *
     * @throws NotFoundException
     */
    protected function owner(): Model
    {
        $item = $this->blockResource()->getItem();

        if (! $item instanceof Model) {
            throw new NotFoundException();
        }

        return $item;
    }

    private function blockResource(): CrudResourceContract&EditsBlocksContract
    {
        $resource = $this->getResource();

        if (! $resource instanceof EditsBlocksContract) {
            throw new RuntimeException(
                'A block editor page belongs to a resource that uses InteractsWithBlockEditor.',
            );
        }

        return $resource;
    }
}
