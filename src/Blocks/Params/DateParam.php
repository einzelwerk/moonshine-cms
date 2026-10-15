<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

/**
 * An ISO date in the query and an ISO date in the normalized set: parsing it
 * into an object belongs to the block, which knows what it selects by.
 */
final class DateParam extends Param
{
    private string $format = 'Y-m-d';

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function rules(): array
    {
        return ['date_format:'.$this->format];
    }

    public function normalize(mixed $raw): mixed
    {
        return is_scalar($raw) ? (string) $raw : null;
    }
}
