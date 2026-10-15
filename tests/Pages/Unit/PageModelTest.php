<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Tests\Fixtures\CmsPage as InstallationPage;
use Illuminate\Database\Eloquent\Relations\Relation;

it('is the package model until an installation names its own', function (): void {
    expect(PageModel::class())->toBe(CmsPage::class)
        ->and(PageModel::make(['slug' => 'info']))->toBeInstanceOf(CmsPage::class);
});

it('hands back instances of the configured subclass', function (): void {
    config()->set('cms-pages.model', InstallationPage::class);

    $page = PageModel::make(['type' => 'page', 'slug' => 'info', 'name' => ['ru' => 'Инфо'], 'path' => '/info']);
    $page->save();

    $found = PageModel::query()->where('slug', 'info')->first();

    expect(PageModel::class())->toBe(InstallationPage::class)
        ->and($page)->toBeInstanceOf(InstallationPage::class)
        ->and($found)->toBeInstanceOf(InstallationPage::class)
        ->and($found?->caption())->toBe('Инфо');
});

it('refuses a configured class that is not a page', function (): void {
    config()->set('cms-pages.model', \stdClass::class);

    PageModel::class();
})->throws(InvalidArgumentException::class);

it('writes one morph alias for every class of the hierarchy', function (): void {
    config()->set('cms-pages.model', InstallationPage::class);
    Relation::morphMap([CmsPage::MORPH_ALIAS => InstallationPage::class]);

    $base = new CmsPage(['type' => 'page', 'slug' => 'a', 'name' => [], 'path' => '/a']);
    $base->save();

    $block = $base->blocks()->create(['type' => 'text', 'name' => 'Текст', 'content' => []]);

    // The row created through the package class is found again through the
    // class of the installation, and the block points back at that class.
    $installation = InstallationPage::query()->find($base->getKey());

    expect($block->blockable_type)->toBe('cms_page')
        ->and((new InstallationPage())->getMorphClass())->toBe('cms_page')
        ->and($installation?->blocks()->count())->toBe(1)
        ->and(CmsBlock::query()->find($block->getKey())?->blockable)->toBeInstanceOf(InstallationPage::class);
});
