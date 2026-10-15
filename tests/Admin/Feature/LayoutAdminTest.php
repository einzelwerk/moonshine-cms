<?php

declare(strict_types=1);

use Ewk\Cms\Layouts\Layouts\LayoutCatalog;
use Ewk\Cms\Layouts\Layouts\LayoutDefinition;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Layouts\Models\CmsLayout;
use Ewk\MoonShineCms\MoonShine\Resources\LayoutResource;
use MoonShine\Support\Enums\Action;

beforeEach(function (): void {
    $this->actingAsAdmin();

    app(LayoutCatalog::class)->register(LayoutDefinition::make(
        'text',
        'Текстовая страница',
        'Узкая колонка для документов и длинных текстов.',
    ));
});

function layoutResource(): LayoutResource
{
    return app(LayoutResource::class);
}

function seededLayout(array $attributes = []): CmsLayout
{
    return CmsLayout::query()->create($attributes + [
        'code' => 'text',
        'name' => ['ru' => 'Текстовая страница'],
        'is_active' => true,
    ]);
}

it('neither creates nor deletes a shell: the set is declared in code', function (): void {
    seededLayout();

    expect(layoutResource()->hasAction(Action::CREATE))->toBeFalse()
        ->and(layoutResource()->hasAction(Action::DELETE))->toBeFalse()
        ->and(layoutResource()->hasAction(Action::MASS_DELETE))->toBeFalse()
        ->and(layoutResource()->hasAction(Action::UPDATE))->toBeTrue();

    // The list says why there is no «create» instead of leaving the editor
    // looking for it.
    indexRows(layoutResource())->assertOk();

    $this->get(layoutResource()->getIndexPageUrl())
        ->assertOk()
        ->assertSee(__('moonshine-cms::chrome.layout_catalog_hint'), false);
});

it('keeps the code out of the form and edits the settings only', function (): void {
    $layout = seededLayout(['container' => 'wide']);
    $header = CmsHeader::query()->create(['code' => 'main', 'name' => ['ru' => 'Основная шапка'], 'is_active' => true, 'content' => []]);
    $footer = CmsFooter::query()->create(['code' => 'main', 'name' => ['ru' => 'Основной подвал'], 'is_active' => true, 'content' => []]);

    $response = $this->get(layoutResource()->getFormPageUrl($layout->getKey()))->assertOk();

    $response
        // The code is the contract with the frontend: it is neither typed nor
        // retyped here, and no order field exists anywhere.
        ->assertDontSee('name="code"', false)
        ->assertDontSee('name="sort_order"', false)
        ->assertDontSee('name="body_class"', false)
        // The shells and the widths read as words.
        ->assertSee('Основная шапка', false)
        ->assertSee(__('moonshine-cms::chrome.containers.narrow'), false)
        // What the shell is for, out of the declaration.
        ->assertSee('Узкая колонка для документов и длинных текстов.', false)
        ->assertSee(__('moonshine-cms::chrome.layout_title_edit', ['name' => 'Текстовая страница']), false);

    submitForm(
        layoutResource()->getRoute('crud.update', $layout->getKey()),
        [
            'name' => ['ru' => 'Текстовая страница'],
            'cms_header_id' => $header->getKey(),
            'cms_footer_id' => $footer->getKey(),
            'container' => 'narrow',
            'is_default' => '1',
            'is_active' => '1',
        ],
        'PUT',
    )->assertSuccessful();

    $layout->refresh();

    expect($layout->code)->toBe('text')
        ->and($layout->container)->toBe('narrow')
        ->and($layout->cms_header_id)->toBe($header->getKey())
        ->and($layout->cms_footer_id)->toBe($footer->getKey())
        ->and($layout->is_default)->toBeTrue();
});

it('refuses a width that is no width of the frontend', function (): void {
    $layout = seededLayout();

    submitForm(
        layoutResource()->getRoute('crud.update', $layout->getKey()),
        ['name' => ['ru' => 'Текстовая страница'], 'container' => 'gigantic'],
        'PUT',
    )->assertStatus(422)->assertJsonValidationErrors(['container']);
});

it('says so about a row whose code the catalog does not declare', function (): void {
    $legacy = CmsLayout::query()->create(['code' => 'legacy', 'name' => ['ru' => 'Старое'], 'is_active' => true]);

    $this->get(layoutResource()->getFormPageUrl($legacy->getKey()))
        ->assertOk()
        ->assertSee(__('moonshine-cms::chrome.layout_unknown'), false);
});
