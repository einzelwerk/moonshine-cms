<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Fields\FieldContext;
use Ewk\Cms\Blocks\Fields\ProvidesFieldContext;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\MoonShineCms\Editor\OwnerFieldContext;
use Ewk\MoonShineCms\Tests\Fixtures\Catalogue;
use Illuminate\Database\Eloquent\Model;

function ownerContexts(): OwnerFieldContext
{
    return app(OwnerFieldContext::class);
}

it('reads the entity of a page out of the declaration of its type', function (): void {
    $template = new CmsPage(['type' => 'concert']);
    $ordinary = new CmsPage(['type' => 'page']);

    expect(ownerContexts()->for($template)->subjectClass)->toBe(Catalogue::class)
        ->and(ownerContexts()->for($template)->hasSubject())->toBeTrue()
        ->and(ownerContexts()->for($ordinary)->hasSubject())->toBeFalse()
        ->and(ownerContexts()->for($ordinary)->owner)->toBe($ordinary);
});

it('says nothing about a page whose type is not registered', function (): void {
    $page = new CmsPage(['type' => 'gone']);

    expect(ownerContexts()->for($page)->hasSubject())->toBeFalse();
});

it('lets an owner of another kind describe itself', function (): void {
    $owner = new class () extends Model implements ProvidesFieldContext {
        public function blockFieldContext(): FieldContext
        {
            return FieldContext::of($this, Catalogue::class);
        }
    };

    expect(ownerContexts()->for($owner)->subjectIs(Catalogue::class))->toBeTrue();
});

it('gives an owner that says nothing a context without a subject', function (): void {
    expect(ownerContexts()->for(new CmsSharedBlock())->hasSubject())->toBeFalse()
        // A block whose owner is gone is described by nobody at all, and the
        // fields then answer with their own declaration.
        ->and(ownerContexts()->for(null)->owner)->toBeNull();
});
