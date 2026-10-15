<?php

declare(strict_types=1);

use Ewk\Cms\Seo\Models\CmsMetadata;
use Ewk\Cms\Seo\Tests\Fixtures\Make;
use Ewk\Cms\Seo\Tests\Fixtures\PlainOwner;
use Illuminate\Support\Facades\Config;

it('uses the name of the entity when the title is not filled in', function (): void {
    $owner = Make::owner(['ru' => 'Афиша Таллина', 'en' => 'Tallinn events']);
    Make::metadata($owner, ['title' => ['ru' => '']]);

    expect(Make::document(Make::context($owner))['title'])->toBe('Афиша Таллина')
        ->and(Make::document(Make::context($owner, locale: 'en'))['title'])->toBe('Tallinn events');
});

it('uses the name of the entity when there is no metadata row at all', function (): void {
    $owner = Make::owner(['ru' => 'Афиша']);

    expect(Make::document(Make::context($owner))['title'])->toBe('Афиша');
});

it('leaves the title empty when the owner offers no fallback either', function (): void {
    $owner = new PlainOwner(['name' => ['ru' => 'Не спросят']]);
    $owner->save();

    expect(Make::document(Make::context($owner))['title'])->toBeNull();
});

it('finds the row of an owner that does not use the trait', function (): void {
    $owner = new PlainOwner();
    $owner->save();
    Make::metadata($owner, ['title' => ['ru' => 'Своя строка']]);

    expect(Make::document(Make::context($owner))['title'])->toBe('Своя строка');
});

it('reads a translatable field on the default language and then gives up', function (): void {
    $owner = Make::owner(['ru' => '', 'en' => '']);
    Make::metadata($owner, ['title' => ['ru' => 'Заголовок', 'en' => '']]);

    expect(Make::document(Make::context($owner, locale: 'en'))['title'])->toBe('Заголовок')
        ->and(Make::document(Make::context($owner, locale: 'de'))['title'])->toBe('Заголовок');
});

it('never invents a description', function (): void {
    $owner = Make::owner();
    Make::metadata($owner, ['title' => ['ru' => 'Заголовок'], 'description' => ['ru' => '']]);

    $document = Make::document(Make::context($owner));

    expect($document['description'])->toBeNull()
        ->and($document['openGraph']['description'])->toBeNull()
        ->and($document['twitter']['description'])->toBeNull();
});

it('takes the Open Graph title and description from the plain ones', function (): void {
    $owner = Make::owner();
    Make::metadata($owner, [
        'title' => ['ru' => 'Заголовок'],
        'description' => ['ru' => 'Описание'],
    ]);

    $document = Make::document(Make::context($owner));

    expect($document['openGraph']['title'])->toBe('Заголовок')
        ->and($document['openGraph']['description'])->toBe('Описание');
});

it('takes the Twitter values from the Open Graph ones', function (): void {
    $owner = Make::owner();
    Make::metadata($owner, [
        'title' => ['ru' => 'Заголовок'],
        'og_title' => ['ru' => 'Заголовок OG'],
        'og_description' => ['ru' => 'Описание OG'],
    ]);

    $document = Make::document(Make::context($owner));

    expect($document['twitter']['title'])->toBe('Заголовок OG')
        ->and($document['twitter']['description'])->toBe('Описание OG');
});

it('keeps what the editor filled in over every fallback', function (): void {
    $owner = Make::owner(['ru' => 'Название сущности']);
    Make::metadata($owner, [
        'title' => ['ru' => 'Свой заголовок'],
        'og_title' => ['ru' => 'Свой OG'],
        'tw_title' => ['ru' => 'Свой Twitter'],
        'og_type' => 'article',
        'tw_card' => 'summary',
    ]);

    $document = Make::document(Make::context($owner));

    expect($document['title'])->toBe('Свой заголовок')
        ->and($document['openGraph']['title'])->toBe('Свой OG')
        ->and($document['openGraph']['type'])->toBe('article')
        ->and($document['twitter']['title'])->toBe('Свой Twitter')
        ->and($document['twitter']['card'])->toBe('summary');
});

it('defaults the Open Graph type to a website and the card to the small one without a picture', function (): void {
    $owner = Make::owner();

    $document = Make::document(Make::context($owner));

    expect($document['openGraph']['type'])->toBe('website')
        ->and($document['openGraph']['images'])->toBe([])
        ->and($document['twitter']['card'])->toBe('summary')
        ->and($document['twitter']['images'])->toBe([]);
});

it('carries the indexing switches into robots and allows both by default', function (): void {
    $owner = Make::owner();

    expect(Make::document(Make::context($owner))['robots'])->toBe(['index' => true, 'follow' => true]);

    Make::metadata($owner, ['robots_index' => false, 'robots_follow' => false]);

    expect(Make::document(Make::context($owner))['robots'])->toBe(['index' => false, 'follow' => false]);
});

it('lets the manual canonical address of the editor win', function (): void {
    $owner = Make::owner();
    Make::metadata($owner, ['canonical' => 'https://example.com/ru/another']);

    expect(Make::document(Make::context($owner))['alternates']['canonical'])->toBe('https://example.com/ru/another');
});

it('lists the language versions and points x-default at the default language', function (?string $prefix, array $languages, string $default, array $alternates): void {
    if ($prefix !== null) {
        Config::set('cms-seo.locale_prefix', $prefix);
    }

    expect(Make::document(Make::context(Make::owner(), path: '/afisha', languages: $languages, defaultLanguage: $default))['alternates'])
        ->toBe($alternates);
})->with([
    'every language of the catalog' => [null, ['ru', 'en', 'de'], 'en', [
        'canonical' => 'https://example.com/ru/afisha',
        'languages' => [
            'ru' => 'https://example.com/ru/afisha',
            'en' => 'https://example.com/en/afisha',
            'de' => 'https://example.com/de/afisha',
            'x-default' => 'https://example.com/en/afisha',
        ],
    ]],
    // Recording the behaviour rather than defending it: a catalog without its
    // own default language is a misconfiguration, and `x-default` is still
    // built from what the caller declared to be the default.
    'a default the catalog does not list' => [null, ['en', 'de'], 'ru', [
        'canonical' => 'https://example.com/ru/afisha',
        'languages' => [
            'en' => 'https://example.com/en/afisha',
            'de' => 'https://example.com/de/afisha',
            'x-default' => 'https://example.com/ru/afisha',
        ],
    ]],
    'an installation without a language prefix' => ['never', ['ru', 'en'], 'ru', [
        'canonical' => 'https://example.com/afisha',
        'languages' => [
            'ru' => 'https://example.com/afisha',
            'en' => 'https://example.com/afisha',
            'x-default' => 'https://example.com/afisha',
        ],
    ]],
]);

it('excludes the filtering parameters from the language versions too', function (): void {
    $owner = Make::owner();
    $context = Make::context(
        $owner,
        path: '/afisha',
        query: ['b' => ['afisha' => ['city' => 3, 'page' => 2]]],
        filterKeys: ['b[afisha][city]'],
        languages: ['ru', 'en'],
    );

    $languages = Make::document($context)['alternates']['languages'];

    expect($languages['en'])->toBe('https://example.com/en/afisha?b[afisha][page]=2')
        ->and($languages['en'])->not->toContain('city');
});

it('gives a page that is not published no language versions', function (): void {
    $owner = Make::owner();
    $context = Make::context($owner, publishedAt: null);

    expect(Make::document($context)['alternates']['languages'])->toBe([])
        ->and(Make::document($context)['alternates']['canonical'])->toBe('https://example.com/ru/afisha');
});

it('passes the shape of Next Metadata: the keys and their types', function (): void {
    $owner = Make::owner();
    Make::metadata($owner, [
        'title' => ['ru' => 'Заголовок'],
        'description' => ['ru' => 'Описание'],
    ]);

    $document = Make::document(Make::context($owner));

    expect($document)->toHaveKeys(['title', 'description', 'alternates', 'openGraph', 'twitter', 'robots'])
        ->and(array_keys($document['alternates']))->toEqualCanonicalizing(['canonical', 'languages'])
        ->and(array_keys($document['openGraph']))->toEqualCanonicalizing(['title', 'description', 'type', 'images'])
        ->and(array_keys($document['twitter']))->toEqualCanonicalizing(['card', 'title', 'description', 'images'])
        ->and(array_keys($document['robots']))->toEqualCanonicalizing(['index', 'follow'])
        ->and($document['title'])->toBeString()
        ->and($document['description'])->toBeString()
        ->and($document['alternates']['canonical'])->toBeString()
        ->and($document['alternates']['languages'])->toBeArray()
        ->and($document['openGraph']['type'])->toBeString()
        ->and($document['openGraph']['images'])->toBeArray()
        ->and($document['twitter']['card'])->toBeString()
        ->and($document['twitter']['images'])->toBeArray()
        ->and($document['robots']['index'])->toBeBool()
        ->and($document['robots']['follow'])->toBeBool();

    foreach ($document['alternates']['languages'] as $code => $href) {
        expect($code)->toBeString()->and($href)->toStartWith('https://');
    }
});

it('keeps one row of metadata per owner', function (): void {
    $owner = Make::owner();
    Make::metadata($owner);

    expect(fn (): CmsMetadata => Make::metadata($owner))->toThrow(Illuminate\Database\QueryException::class);
});

it('stores the polymorphic alias of a page, not the class of the installation', function (): void {
    $page = Make::page('Страница', 'stranica');

    $metadata = Make::metadata($page, ['title' => ['ru' => 'Заголовок страницы']]);

    expect($metadata->model_type)->toBe('cms_page')
        ->and(Make::document(Make::context($page))['title'])->toBe('Заголовок страницы');
});

it('takes the fallback title the caller put into the context', function (): void {
    // The name of a page of `cms_pages` arrives this way: the model of the
    // pages does not implement a contract of the SEO part, which the pages do
    // not depend on.
    $owner = new PlainOwner();
    $owner->save();

    expect(Make::document(Make::context($owner, fallbackTitle: 'Имя страницы'))['title'])->toBe('Имя страницы');
});

it('prefers the fallbacks of the context over the contract of the owner', function (): void {
    $owner = Make::owner(['ru' => 'Название владельца'], cover: 'https://cdn.example.com/owner.jpg');

    $document = Make::document(Make::context(
        $owner,
        fallbackTitle: 'Имя из контекста',
        fallbackImage: 'https://cdn.example.com/context.jpg',
    ));

    expect($document['title'])->toBe('Имя из контекста')
        ->and($document['openGraph']['images'])->toBe([['url' => 'https://cdn.example.com/context.jpg']]);
});

it('still asks the owner when the context carries no fallback', function (): void {
    $owner = Make::owner(['ru' => 'Название владельца'], cover: 'https://cdn.example.com/owner.jpg');

    $document = Make::document(Make::context($owner, fallbackTitle: '   ', fallbackImage: ''));

    expect($document['title'])->toBe('Название владельца')
        ->and($document['openGraph']['images'][0]['url'])->toBe('https://cdn.example.com/owner.jpg');
});

it('writes nothing while building the document: a fallback is computed, not stored', function (): void {
    $owner = Make::owner(['ru' => 'Название сущности']);

    $this->assertDatabaseCount('cms_metadata', 0);

    $document = Make::document(Make::context($owner));

    expect($document['title'])->toBe('Название сущности');
    $this->assertDatabaseCount('cms_metadata', 0);

    $metadata = Make::metadata($owner, ['title' => ['ru' => 'Заголовок']]);
    $this->assertDatabaseCount('cms_metadata', 1);

    Make::document(Make::context($owner));

    $this->assertDatabaseCount('cms_metadata', 1);
    $this->assertDatabaseHas('cms_metadata', ['id' => $metadata->id, 'updated_at' => $metadata->updated_at]);
});
