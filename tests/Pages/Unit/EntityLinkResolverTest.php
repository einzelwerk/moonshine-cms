<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Pages\Links\EntityLinkResolver;
use Ewk\Cms\Pages\Tests\Fixtures\AwkwardEntity;
use Ewk\Cms\Pages\Tests\Fixtures\Concert;
use Ewk\Cms\Pages\Tests\Fixtures\Make;
use Ewk\Cms\Pages\Tests\Fixtures\Series;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    TestPageTypes::registerAll(app(PageTypeRegistry::class));
});

function entityLink(mixed $type, mixed $id): Link
{
    return new Link(LinkKind::ENTITY, ['type' => $type, 'id' => $id]);
}

it('builds the address out of the path template of the type the entity belongs to', function (): void {
    $concert = Make::concert('leto', 'noch-muzyki');

    $resolved = app(EntityLinkResolver::class)->resolve(entityLink(Concert::class, $concert->id), 'ru');

    expect($resolved->toArray())->toBe([
        'kind' => 'entity',
        'href' => '/series/leto/noch-muzyki',
        'external' => false,
        'new_tab' => false,
        'action' => null,
    ]);
});

it('reads the class out of a morph alias as readily as out of the class name', function (): void {
    Relation::morphMap(['concert' => Concert::class]);

    $concert = Make::concert('leto', 'noch-muzyki');

    expect(app(EntityLinkResolver::class)->resolve(entityLink('concert', $concert->id), 'ru')->href)
        ->toBe('/series/leto/noch-muzyki');
});

it('hands out a broken link for an entity its own visibility criterion hides', function (): void {
    $hidden = Make::concert('leto', 'tajnyj-kontsert', active: false, hidden: true);

    $resolved = app(EntityLinkResolver::class)->resolve(entityLink(Concert::class, $hidden->id), 'ru');

    expect($resolved->kind)->toBe(LinkKind::ENTITY)
        ->and($resolved->href)->toBeNull();
});

it('hands out a broken link for a record that is gone and for a type nothing declares', function (): void {
    $resolver = app(EntityLinkResolver::class);

    expect($resolver->resolve(entityLink(Concert::class, 404), 'ru')->href)->toBeNull()
        ->and($resolver->resolve(entityLink(Series::class, 1), 'ru')->href)->toBeNull()
        ->and($resolver->resolve(entityLink('App\\Nothing', 1), 'ru')->href)->toBeNull()
        ->and($resolver->resolve(entityLink(null, null), 'ru')->href)->toBeNull();
});

it('reads the type of a class without building one, so an awkward model cannot take the answer down', function (): void {
    $resolved = app(EntityLinkResolver::class)->resolve(entityLink(AwkwardEntity::class, 1), 'ru');

    expect($resolved->kind)->toBe(LinkKind::ENTITY)
        ->and($resolved->href)->toBeNull();
});

it('asks the database nothing for a class no page type stands over', function (): void {
    $resolver = app(EntityLinkResolver::class);

    DB::flushQueryLog();
    DB::enableQueryLog();

    expect($resolver->resolve(entityLink(Series::class, 1), 'ru')->href)->toBeNull()
        ->and(DB::getQueryLog())->toBe([]);

    DB::disableQueryLog();
});

it('refuses a link to a type nothing declares a page for, in the form', function (): void {
    $rules = ['link' => LinkField::make('cta')->rules()];

    $fails = static fn (mixed $type): bool => Validator::make(
        ['link' => ['kind' => 'entity', 'attributes' => ['type' => $type, 'id' => 1]]],
        $rules,
    )->fails();

    expect($fails(Concert::class))->toBeFalse()
        ->and($fails(Series::class))->toBeTrue()
        ->and($fails('App\\Nothing'))->toBeTrue();
});

it('hands out a broken link for an entity whose slug is not one segment', function (): void {
    $concert = Make::concert('leto', 'noch/../admin');

    expect(app(EntityLinkResolver::class)->resolve(entityLink(Concert::class, $concert->id), 'ru')->href)
        ->toBeNull();
});
