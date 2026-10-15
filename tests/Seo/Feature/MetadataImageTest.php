<?php

declare(strict_types=1);

use Ewk\Cms\Media\Enums\MediaVariantState;
use Ewk\Cms\Media\Exceptions\MediaInUseException;
use Ewk\Cms\Media\Models\CmsMediaUsage;
use Ewk\Cms\Seo\Tests\Fixtures\Make;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

it('takes the original file when no preset is configured', function (): void {
    $owner = Make::owner();
    $media = Make::media();
    Make::metadata($owner, ['og_image' => ['ru' => $media->id]]);

    $images = Make::document(Make::context($owner))['openGraph']['images'];

    expect($images)->toHaveCount(1)
        ->and($images[0]['url'])->toContain('cms/original.jpg')
        ->and($images[0]['width'])->toBe(2000)
        ->and($images[0]['height'])->toBe(1200);
});

it('hands out an absolute address for a picture of the library', function (): void {
    $owner = Make::owner();
    $media = Make::media();
    Make::metadata($owner, ['og_image' => ['ru' => $media->id]]);

    $document = Make::document(Make::context($owner));

    // A relative address in an Open Graph tag is simply wrong: the tag is read
    // off the page by something that is not a browser on this host.
    expect($document['openGraph']['images'][0]['url'])->toStartWith('http')
        ->and($document['twitter']['images'][0])->toStartWith('http');
});

it('completes a path of a disk without an address of its own with the address of the application', function (): void {
    // A disk without an absolute `url` answers with a path of this
    // application, not of the public site.
    Config::set('filesystems.disks.public.url', '/storage');
    Config::set('app.url', 'https://api.example.com/');
    Storage::forgetDisk('public');

    $owner = Make::owner();
    $media = Make::media();
    Make::metadata($owner, ['og_image' => ['ru' => $media->id]]);

    $document = Make::document(Make::context($owner));

    expect($document['openGraph']['images'][0]['url'])->toBe('https://api.example.com/storage/cms/original.jpg')
        ->and($document['twitter']['images'][0])->toBe('https://api.example.com/storage/cms/original.jpg');
});

it('hands out the absolute address of a disk unchanged', function (): void {
    Config::set('filesystems.disks.public.url', 'https://cdn.example.com/files');
    Config::set('app.url', 'https://api.example.com');
    Storage::forgetDisk('public');

    $owner = Make::owner();
    $media = Make::media();
    Make::metadata($owner, ['og_image' => ['ru' => $media->id]]);

    $document = Make::document(Make::context($owner));

    expect($document['openGraph']['images'][0]['url'])->toBe('https://cdn.example.com/files/cms/original.jpg')
        ->and($document['twitter']['images'][0])->toBe('https://cdn.example.com/files/cms/original.jpg');
});

it('takes the address of the variant when the configured preset is ready', function (): void {
    Config::set('cms-seo.image_preset', 'og');

    $owner = Make::owner();
    $media = Make::media();
    $media->variants()->create([
        'preset' => 'og',
        'disk' => 'public',
        'path' => 'cms/variants/og.webp',
        'width' => 1200,
        'height' => 630,
        'format' => 'webp',
        'state' => MediaVariantState::READY,
    ]);
    Make::metadata($owner, ['og_image' => ['ru' => $media->id]]);

    $images = Make::document(Make::context($owner))['openGraph']['images'];

    expect($images[0]['url'])->toContain('cms/variants/og.webp')
        ->and($images[0]['width'])->toBe(1200)
        ->and($images[0]['height'])->toBe(630);
});

it('takes the original while the variant is not generated yet', function (): void {
    Config::set('cms-seo.image_preset', 'og');

    $owner = Make::owner();
    $media = Make::media();
    $media->variants()->create([
        'preset' => 'og',
        'disk' => 'public',
        'path' => null,
        'state' => MediaVariantState::PENDING,
    ]);
    Make::metadata($owner, ['og_image' => ['ru' => $media->id]]);

    $images = Make::document(Make::context($owner))['openGraph']['images'];

    expect($images[0]['url'])->toContain('cms/original.jpg');
});

it('enlarges the Twitter card as soon as there is a picture', function (): void {
    $owner = Make::owner();
    $media = Make::media();
    Make::metadata($owner, ['og_image' => ['ru' => $media->id]]);

    $document = Make::document(Make::context($owner));

    expect($document['twitter']['card'])->toBe('summary_large_image')
        ->and($document['twitter']['images'])->toBe([$document['openGraph']['images'][0]['url']]);
});

it('lets a Twitter picture of its own override the Open Graph one', function (): void {
    $owner = Make::owner();
    $og = Make::media('cms/og.jpg');
    $twitter = Make::media('cms/twitter.jpg');
    Make::metadata($owner, ['og_image' => ['ru' => $og->id], 'tw_image' => ['ru' => $twitter->id]]);

    $document = Make::document(Make::context($owner));

    expect($document['openGraph']['images'][0]['url'])->toContain('cms/og.jpg')
        ->and($document['twitter']['images'][0])->toContain('cms/twitter.jpg');
});

it('falls back to the cover of the entity, and reports no dimensions for it', function (): void {
    $owner = Make::owner(cover: 'https://cdn.example.com/cover.jpg');

    $document = Make::document(Make::context($owner));

    expect($document['openGraph']['images'])->toBe([['url' => 'https://cdn.example.com/cover.jpg']])
        ->and($document['twitter']['images'])->toBe(['https://cdn.example.com/cover.jpg'])
        ->and($document['twitter']['card'])->toBe('summary_large_image');
});

it('reads a reference to a record that is gone as no picture, the column carrying no foreign key', function (): void {
    $owner = Make::owner();
    Make::metadata($owner, ['og_image' => ['ru' => 4040]]);

    $document = Make::document(Make::context($owner));

    expect($document['openGraph']['images'])->toBe([])
        ->and($document['twitter']['card'])->toBe('summary');
});

it('writes no usage for a picture that is not in the library', function (): void {
    $owner = Make::owner();

    // The column carries no foreign key and `cms_media_usages.cms_media_id` does:
    // registering the reference as a usage would be a row the database refuses.
    $metadata = Make::metadata($owner, ['og_image' => ['ru' => 4040]]);

    expect(CmsMediaUsage::query()->where('usable_type', $metadata->getMorphClass())->count())->toBe(0);
});

it('registers the pictures of a card as usages of that card', function (): void {
    $owner = Make::owner();
    $og = Make::media('cms/og.jpg');
    $tw = Make::media('cms/tw.jpg');

    $metadata = Make::metadata($owner, ['og_image' => ['ru' => $og->id], 'tw_image' => ['ru' => $tw->id]]);

    $usages = CmsMediaUsage::query()
        ->where('usable_type', $metadata->getMorphClass())
        ->where('usable_id', $metadata->getKey())
        ->get()
        ->map(static fn (CmsMediaUsage $usage): array => [$usage->field, $usage->path, $usage->cms_media_id])
        ->sort()
        ->values()
        ->all();

    expect($usages)->toBe([['og_image', 'og_image.ru', $og->id], ['tw_image', 'tw_image.ru', $tw->id]])
        ->and(fn () => $og->delete())->toThrow(MediaInUseException::class);
});

it('releases the picture an editor cleared and the pictures of a deleted card', function (): void {
    $owner = Make::owner();
    $og = Make::media('cms/og.jpg');

    $metadata = Make::metadata($owner, ['og_image' => ['ru' => $og->id]]);

    $metadata->putImage('og_image', 'ru', null);
    $metadata->save();

    expect($og->fresh()?->delete())->toBeTrue();

    $other = Make::media('cms/second.jpg');
    $metadata->putImage('og_image', 'ru', $other->id);
    $metadata->save();

    expect(fn () => $other->delete())->toThrow(MediaInUseException::class);

    $metadata->delete();

    expect($other->fresh()?->delete())->toBeTrue();
});

it('hands each language its own picture of the card', function (): void {
    $owner = Make::owner();
    $ru = Make::media('cms/og-ru.jpg');
    $en = Make::media('cms/og-en.jpg');
    $twEn = Make::media('cms/tw-en.jpg');
    Make::metadata($owner, ['og_image' => ['ru' => $ru->id, 'en' => $en->id], 'tw_image' => ['en' => $twEn->id]]);

    $russian = Make::document(Make::context($owner, locale: 'ru'));
    $english = Make::document(Make::context($owner, locale: 'en'));

    expect($russian['openGraph']['images'][0]['url'])->toContain('cms/og-ru.jpg')
        ->and($russian['twitter']['images'][0])->toContain('cms/og-ru.jpg')
        ->and($english['openGraph']['images'][0]['url'])->toContain('cms/og-en.jpg')
        ->and($english['twitter']['images'][0])->toContain('cms/tw-en.jpg');
});

it('lets a language without a picture of its own fall back to the default language before the cover', function (): void {
    $owner = Make::owner(cover: 'https://cdn.example.com/cover.jpg');
    $ru = Make::media('cms/og-ru.jpg');
    Make::metadata($owner, ['og_image' => ['ru' => $ru->id]]);

    $german = Make::document(Make::context($owner, locale: 'de'));

    expect($german['openGraph']['images'][0]['url'])->toContain('cms/og-ru.jpg');
});

it('registers the picture of every language as a usage of its own', function (): void {
    $owner = Make::owner();
    $ru = Make::media('cms/og-ru.jpg');
    $en = Make::media('cms/og-en.jpg');

    $metadata = Make::metadata($owner, ['og_image' => ['ru' => $ru->id, 'en' => $en->id]]);

    $usages = CmsMediaUsage::query()
        ->where('usable_type', $metadata->getMorphClass())
        ->where('field', 'og_image')
        ->pluck('cms_media_id', 'path')
        ->all();

    expect($usages)->toEqualCanonicalizing(['og_image.ru' => $ru->id, 'og_image.en' => $en->id]);

    $metadata->putImage('og_image', 'en', null);
    $metadata->save();

    expect($en->fresh()?->delete())->toBeTrue()
        ->and(fn () => $ru->delete())->toThrow(MediaInUseException::class);
});
