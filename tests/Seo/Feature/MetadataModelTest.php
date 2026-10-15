<?php

declare(strict_types=1);

use Ewk\Cms\Seo\Documents\MetadataDocumentBuilder;
use Ewk\Cms\Seo\Models\CmsMetadata;
use Ewk\Cms\Seo\Tests\Fixtures\Make;
use Ewk\Cms\Seo\Tests\Fixtures\SeoOwner;

it('reaches the row of an owner through the relation of the trait', function (): void {
    $owner = Make::owner();
    $metadata = Make::metadata($owner, ['title' => ['ru' => 'Заголовок']]);

    expect($owner->seoMetadata()->first()?->id)->toBe($metadata->id)
        ->and($metadata->model()->first()?->getKey())->toBe($owner->getKey());
});

it('uses the row already loaded with the owner', function (): void {
    $owner = Make::owner();
    Make::metadata($owner, ['title' => ['ru' => 'Загружено заранее']]);

    $loaded = SeoOwner::query()->with('seoMetadata')->findOrFail($owner->getKey());

    expect(app(MetadataDocumentBuilder::class)->build(Make::context($loaded))['title'])
        ->toBe('Загружено заранее');
});

it('treats an owner loaded without a row as an owner without metadata', function (): void {
    $owner = Make::owner(['ru' => 'Название']);

    $loaded = SeoOwner::query()->with('seoMetadata')->findOrFail($owner->getKey());

    expect($loaded->relationLoaded('seoMetadata'))->toBeTrue()
        ->and(app(MetadataDocumentBuilder::class)->build(Make::context($loaded))['title'])->toBe('Название');
});

it('tells an empty dictionary from a filled one', function (): void {
    $metadata = new CmsMetadata([
        'title' => ['ru' => '', 'en' => 'Title'],
        'description' => [],
    ]);

    expect($metadata->titleFor('ru', 'ru'))->toBeNull()
        ->and($metadata->titleFor('en', 'ru'))->toBe('Title')
        ->and($metadata->descriptionFor('ru', 'ru'))->toBeNull();
});

it('writes one language of a translatable value and removes it when emptied', function (): void {
    $owner = Make::owner();
    $metadata = $owner->seoMetadata()->firstOrCreate([]);

    $metadata->putTranslation('title', 'ru', ' Заголовок ');
    $metadata->putTranslation('title', 'en', 'Title');
    $metadata->save();

    expect($metadata->refresh()->title)->toBe(['ru' => 'Заголовок', 'en' => 'Title']);

    $metadata->putTranslation('title', 'en', '');
    $metadata->save();

    expect($metadata->refresh()->title)->toBe(['ru' => 'Заголовок']);
});

it('refuses a column that holds no dictionary of languages', function (): void {
    $metadata = new CmsMetadata();

    expect(static fn (): mixed => $metadata->putTranslation('canonical', 'ru', '/afisha'))
        ->toThrow(InvalidArgumentException::class);
});

it('removes the metadata of an owner that is deleted', function (): void {
    $owner = Make::owner();
    $other = Make::owner();
    Make::metadata($owner, ['title' => ['ru' => 'Уходит']]);
    $kept = Make::metadata($other, ['title' => ['ru' => 'Остаётся']]);

    $owner->delete();

    expect(CmsMetadata::query()->where('model_id', $owner->getKey())->where('model_type', $owner->getMorphClass())->exists())->toBeFalse()
        ->and(CmsMetadata::query()->whereKey($kept->getKey())->exists())->toBeTrue();
});
