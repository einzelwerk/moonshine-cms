<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Http\Resolve;

use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Blocks\Params\ResolvedParameters;
use Ewk\Cms\Pages\Resolving\ResolvedPath;

/**
 * The answer of the resolver while it is still being written.
 *
 * It is mutable, and only for the length of one request: the pages do not
 * depend on the SEO part, so metadata and JSON-LD are put in by a contributor
 * instead of by a constructor argument, see {@see ResolveContributor}. What the
 * contributors need to build them — the resolved address, the published
 * version, the normalized parameters and the language — is readable here and
 * never guessed from the payload.
 */
final class ResolveDocument
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        private readonly ResolvedPath $resolved,
        private readonly ?CmsVersion $version,
        private readonly ResolvedParameters $parameters,
        private readonly string $locale,
    ) {
    }

    public function resolved(): ResolvedPath
    {
        return $this->resolved;
    }

    public function version(): ?CmsVersion
    {
        return $this->version;
    }

    public function parameters(): ResolvedParameters
    {
        return $this->parameters;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * The payload in the order the keys were written: the resolver writes the
     * body of the answer, then the contributors add theirs.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
