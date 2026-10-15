<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Http\Resolve;

/**
 * Who writes into the answer of the resolver besides the resolver itself. A
 * singleton, filled by the providers of the neighbouring packages; the
 * contributors run in the order they registered, so a later one can read what
 * an earlier one wrote.
 */
final class ResolveContributorRegistry
{
    /** @var list<ResolveContributor> */
    private array $contributors = [];

    public function register(ResolveContributor $contributor): void
    {
        $this->contributors[] = $contributor;
    }

    /**
     * @return list<ResolveContributor>
     */
    public function all(): array
    {
        return $this->contributors;
    }
}
