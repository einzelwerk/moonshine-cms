<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\Http\Payload\FiltersPayload;
use Ewk\Cms\Blocks\Http\Payload\PaginationPayload;
use Ewk\Cms\Blocks\Http\Resources\SchemaBlockResource;
use Ewk\Cms\Blocks\Params\Continuation;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Illuminate\Http\Request;

/**
 * A list whose payload is built out of the resolved parameters, the way an
 * application block does it. The total is fixed, because the fixture selects
 * nothing: what is under test is the shape of the payload and the addresses.
 */
final class DynamicListBlockResource extends SchemaBlockResource
{
    public const TOTAL = 42;

    protected function toData(Request $request): array
    {
        $parameters = $this->parameters();
        $resolved = $this->resolvedParameters();

        if ($parameters === null || $resolved === null) {
            return [...parent::toData($request), 'pagination' => PaginationPayload::none()];
        }

        $pagination = match (true) {
            $parameters->mode() === PaginationMode::PAGES => PaginationPayload::pages(
                $parameters,
                self::TOTAL,
                $resolved,
            ),
            $parameters->mode() === PaginationMode::MORE
                && $parameters->continuation() === Continuation::URL => PaginationPayload::more(
                    $parameters,
                    self::TOTAL,
                    $resolved,
                    ['href' => '/events'],
                ),
            $parameters->mode() === PaginationMode::MORE => PaginationPayload::moreByCursor(
                $parameters->perPage,
                'ZW5k',
                '/api/v1/blocks/'.$this->block->uuid.'?path=%2F',
            ),
            default => PaginationPayload::none(),
        };

        return [
            ...parent::toData($request),
            'pagination' => $pagination,
            'filters' => FiltersPayload::make(FiltersPayload::MODE_INLINE, [
                'city' => [
                    'value' => $parameters->get('city'),
                    'options' => [
                        ['value' => null, 'label' => 'Все'],
                        ['value' => 'berlin', 'label' => 'Берлин'],
                    ],
                ],
            ]),
        ];
    }
}
