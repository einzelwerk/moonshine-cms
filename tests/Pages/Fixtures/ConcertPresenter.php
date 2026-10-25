<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests\Fixtures;

use Ewk\Cms\Pages\Types\DescribesSubjectSchema;
use Ewk\Cms\Pages\Types\SubjectPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * What an installation does: a presenter of a subject reuses the public
 * resource of that entity. Here it is a plain array, because this package
 * ships no catalog of its own.
 */
final class ConcertPresenter implements SubjectPresenter, DescribesSubjectSchema
{
    public static function subjectSchema(): string
    {
        return 'ConcertResource';
    }

    public function present(Model $subject, Request $request): array
    {
        return [
            'id' => $subject->getKey(),
            'slug' => $subject->getAttribute('slug'),
        ];
    }
}
