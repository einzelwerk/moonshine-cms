<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * How the entity behind a template page reaches the answer of the resolver: the
 * type declares the presenter, the package calls it and knows nothing about the
 * catalog it presents.
 *
 * A type without a presenter still answers with a subject — `{type, slug,
 * data: {}}` — because the address resolved to an entity either way, and the
 * frontend must not have to guess whether it did.
 */
interface SubjectPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Model $subject, Request $request): array;
}
