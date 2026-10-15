<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\JsonLd;

use Ewk\Cms\Seo\Documents\SeoContext;

/**
 * One kind of structured data. The response of the resolver carries ready
 * JSON-LD objects and the frontend inserts them with a script.
 *
 * This is not the backend building markup: JSON-LD is data. The reason is
 * practical — half of the fields of an event live only here. The minimum
 * price including fees is computed on the backend, the status of the event
 * and the availability of tickets are ours too, and making the frontend
 * assemble that again would mean a second place where a price is computed.
 *
 * The core ships no builder at all: an event is a matter of the application.
 */
interface JsonLdBuilder
{
    /**
     * Whether this builder has anything to say about this page. Asked before
     * {@see self::build()} so a builder does not have to answer with an empty
     * list.
     */
    public function supports(SeoContext $context): bool;

    /**
     * One page may carry several objects: a tour is a separate object per
     * date, each with its own page, as the documentation requires.
     *
     * @return list<array<string, mixed>>
     */
    public function build(SeoContext $context): array;
}
