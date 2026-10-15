<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Actions;

use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Layouts\Exceptions\InvalidMenuItemPlacementException;
use Ewk\Cms\Layouts\Models\CmsMenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The write of a menu item, mounted on its resource as the save handler.
 *
 * Two things the generic apply of the panel cannot do live here. The target is
 * a nested value inside one json column, and `data_set()` on an Eloquent model
 * does not reach into a cast attribute — the link would silently never be
 * stored. And the depth of a menu is validated by the model of the package: a
 * rule implemented in a form is bypassed by the first import, so the form
 * catches the refusal of the model instead of repeating the check.
 */
final readonly class SaveMenuItem
{
    public function __construct(private Request $request)
    {
    }

    /**
     * @param  array<string, mixed>  $data  the submitted form, already applied to the model
     *
     * @throws ValidationException
     */
    public function __invoke(CmsMenuItem $item, array $data): CmsMenuItem
    {
        $field = LinkField::make('link');
        $raw = $this->request->input('link');

        // An item without a target is allowed: that is a group caption of a
        // footer column.
        $link = $field->normalize($raw);

        if ($link !== null) {
            Validator::make(['link' => $link], ['link' => $field->rules()])->validate();
        }

        $item->link = $link;

        try {
            $item->save();
        } catch (InvalidMenuItemPlacementException $refusal) {
            throw ValidationException::withMessages(['parent_id' => $refusal->getMessage()]);
        }

        return $item;
    }
}
