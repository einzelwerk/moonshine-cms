<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests\Fixtures\Blocks;

use Ewk\Cms\Blocks\Http\Resources\SchemaBlockResource;
use Illuminate\Http\Request;

/**
 * The payload of the fixture list: its own fields plus the parameters the
 * resolution handed it, so a test can see which set landed in which block.
 */
final class ListingBlockResource extends SchemaBlockResource
{
    protected function toData(Request $request): array
    {
        $parameters = $this->parameters();

        return [
            ...parent::toData($request),
            'city' => $parameters?->get('city'),
            'pick' => $parameters?->get('pick'),
            'year' => $parameters?->get('year'),
            'page' => $parameters?->page,
            'per_page' => $parameters?->perPage,
        ];
    }
}
