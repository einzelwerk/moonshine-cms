<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

use Ewk\Cms\Blocks\Fields\FieldContext;
use Ewk\Cms\Blocks\Fields\ProvidesFieldContext;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\Cms\Pages\Types\Subject;
use Illuminate\Database\Eloquent\Model;

/**
 * What the fields of a card are told about the owner they are drawn for, see
 * {@see FieldContext}.
 *
 * A page is described by its type: the declaration says whether the type is
 * rendered for an entity of the catalog, and the subject of a block is that
 * entity. The core of the blocks knows nothing about page types, so the
 * lookup happens here, in the one package that knows both.
 *
 * An owner of another kind — a library entry, an entity carrying blocks
 * directly — answers for itself through {@see ProvidesFieldContext}, and one
 * that answers nothing gets a context without a subject, which is the honest
 * description of it.
 */
final readonly class OwnerFieldContext
{
    public function __construct(private PageTypeRegistry $types)
    {
    }

    public function for(?Model $owner): FieldContext
    {
        if ($owner instanceof ProvidesFieldContext) {
            return $owner->blockFieldContext();
        }

        if (! $owner instanceof CmsPage || ! $this->types->has($owner->type)) {
            return FieldContext::of($owner);
        }

        $subject = $this->types->get($owner->type)->subject();

        return FieldContext::of($owner, $subject instanceof Subject ? $subject->modelClass : null);
    }
}
