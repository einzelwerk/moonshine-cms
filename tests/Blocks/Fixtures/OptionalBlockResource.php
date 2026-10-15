<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\Contracts\OmitsWhenEmpty;
use Ewk\Cms\Blocks\Http\Resources\SchemaBlockResource;
use Illuminate\Http\Request;

final class OptionalBlockResource extends SchemaBlockResource implements OmitsWhenEmpty
{
    public function isEmpty(array $data, Request $request): bool
    {
        return ($data['caption'] ?? '') === '';
    }
}
