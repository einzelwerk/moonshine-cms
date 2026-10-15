<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Actions\PublishOwner;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\MoonShineCms\Editor\PublicationState;
use Ewk\MoonShineCms\Editor\PublicationStatus;
use Ewk\MoonShineCms\Editor\PublicationStatusResolver;
use Ewk\MoonShineCms\Tests\Fixtures\EditorOwner;
use Ewk\MoonShineCms\Tests\Fixtures\StaticLanguageCatalog;
use Illuminate\Support\Carbon;

function publicationState(EditorOwner $owner): PublicationState
{
    return app(PublicationStatusResolver::class)->resolve($owner);
}

it('reports a draft while no snapshot exists', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['content' => ['body' => ['ru' => 'Текст']]]);

    expect(publicationState($owner)->status)->toBe(PublicationStatus::DRAFT)
        ->and(publicationState($owner)->number)->toBe(0);
});

it('reports published right after a publication', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['content' => ['body' => ['ru' => 'Текст', 'en' => 'Text']]]);

    Carbon::setTestNow(Carbon::now()->addMinute());
    app(PublishOwner::class)->handle($owner);
    Carbon::setTestNow();

    expect(publicationState($owner->refresh())->status)->toBe(PublicationStatus::PUBLISHED)
        ->and(publicationState($owner)->number)->toBe(1);
});

it('reports published and modified once a block changed after the snapshot', function (): void {
    $owner = editorOwner();
    $block = cmsBlock($owner, ['content' => ['body' => ['ru' => 'Текст', 'en' => 'Text']]]);

    app(PublishOwner::class)->handle($owner);

    Carbon::setTestNow(Carbon::now()->addHour());
    $block->name = 'Другой';
    $block->save();
    Carbon::setTestNow();

    expect(publicationState($owner->refresh())->status)->toBe(PublicationStatus::PUBLISHED_AND_MODIFIED);
});

it('reports published and modified once the owner itself was touched after the snapshot', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['content' => ['body' => ['ru' => 'Текст', 'en' => 'Text']]]);

    Carbon::setTestNow(Carbon::now()->addMinute());
    app(PublishOwner::class)->handle($owner);
    Carbon::setTestNow();

    expect(publicationState($owner->refresh())->status)->toBe(PublicationStatus::PUBLISHED);

    // What a deletion or a move of a block leaves behind: the mark of the owner
    // moves while the live rows say nothing.
    $owner->updated_at = Carbon::now()->addHour();
    $owner->save();

    expect(publicationState($owner->refresh())->status)->toBe(PublicationStatus::PUBLISHED_AND_MODIFIED);
});

it('reports unpublished while the owner is switched off', function (): void {
    $owner = editorOwner();
    cmsBlock($owner, ['content' => ['body' => ['ru' => 'Текст', 'en' => 'Text']]]);

    app(PublishOwner::class)->handle($owner);

    $owner->is_active = false;
    $owner->save();

    expect(publicationState($owner)->status)->toBe(PublicationStatus::UNPUBLISHED);
});

it('names the languages that are not filled everywhere', function (): void {
    app()->instance(LanguageCatalog::class, new StaticLanguageCatalog(['ru', 'en'], 'ru'));

    $owner = editorOwner();
    cmsBlock($owner, ['content' => ['title' => ['ru' => 'Заголовок'], 'body' => ['ru' => 'Текст']]]);

    expect(publicationState($owner)->incompleteLocales)->toBe(['en']);
});

it('keeps the publication problems next to the status without blocking a save', function (): void {
    $owner = editorOwner();

    cmsBlock($owner, ['type' => 'panel', 'name' => 'Панель']);

    $state = publicationState($owner);

    expect($state->isPublishable())->toBeFalse()
        ->and($state->problems)->not->toBeEmpty()
        ->and($state->status)->toBe(PublicationStatus::DRAFT);
});
