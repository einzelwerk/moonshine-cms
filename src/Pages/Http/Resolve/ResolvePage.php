<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Http\Resolve;

use Ewk\Cms\Blocks\Localization\LocalizedValueResolver;
use Ewk\Cms\Blocks\Params\BlockParameters;
use Ewk\Cms\Blocks\Rendering\BlockRenderer;
use Ewk\Cms\Blocks\Rendering\RenderedBlocks;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Pages\Events\PageResolved;
use Ewk\Cms\Pages\Resolving\PathResolver;
use Ewk\Cms\Pages\Resolving\Resolution;
use Ewk\Cms\Pages\Resolving\ResolvedPath;
use Ewk\Cms\Pages\Support\SubjectContext;
use Ewk\Cms\Pages\Types\Subject;
use Ewk\Cms\Pages\Types\SubjectPresentation;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use stdClass;

/**
 * The service behind `GET /api/v1/resolve`: an address and a query string in,
 * the document of the answer out.
 *
 * The order is fixed and the first three steps come before anything that can
 * wait on the database of a catalog, because an honest 404 has to be decided
 * before a single byte of the answer is written:
 *
 * 1. normalize and resolve the address;
 * 2. a language prefix is reported as such, not as a miss;
 * 3. a miss is a miss;
 * 4. no published snapshot, or a page switched off — a miss again. **The
 *    public answer reads the snapshot and never the draft**: a draft is a
 *    404 and not a preview;
 * 5. the subject goes into {@see SubjectContext}, so the blocks of the
 *    template find it;
 * 6. the parameters of the blocks are parsed out of the query string;
 * 7. the blocks and their normalized parameters come out of one traversal of
 *    the snapshot, see {@see RenderedBlocks};
 * 8. the body of the document is written;
 * 9. the contributors add theirs, in the order they registered;
 * 10. {@see PageResolved} tells the installation a whole page was answered,
 *     so that what a view means to it, a history of the visitor for one,
 *     hangs on the event and not on this class. {@see self::locate()} fires
 *     nothing: the endpoint of a single block is not a view of a page.
 *
 * A declared parameter with a value it does not accept throws
 * {@see \Illuminate\Validation\ValidationException} out of the parameter
 * resolution and is not caught here: 422 belongs to the transport, and
 * swallowing it would hand the visitor a list he did not ask for.
 */
final readonly class ResolvePage
{
    public function __construct(
        private PathResolver $paths,
        private BlockRenderer $renderer,
        private LanguageCatalog $languages,
        private ResolveContributorRegistry $contributors,
        private SubjectPresentation $subjects,
        private Translator $translator,
        private Dispatcher $events,
    ) {
    }

    public function resolve(string $path, Request $request): ResolveOutcome
    {
        $outcome = $this->locate($path, $request);

        if (! $outcome->isFound() || $outcome->located === null) {
            return $outcome;
        }

        $located = $outcome->located;

        // One traversal of one tree for both halves of the answer: the
        // envelopes and the normalized parameters the canonical address is
        // built from. Asking for them separately hydrates the snapshot twice
        // and sends every options provider of every filter to the database
        // twice.
        $rendered = $this->renderer->renderPublishedDocument(
            $located->page,
            $request,
            BlockParameters::fromPageQuery($request->query()),
            $located->version,
        );

        if ($rendered === null) {
            return ResolveOutcome::notFound();
        }

        $document = new ResolveDocument(
            $located->resolved,
            $rendered->version,
            $rendered->parameters,
            $this->translator->getLocale(),
        );

        $this->describe($document, $located, $rendered, $request);

        foreach ($this->contributors->all() as $contributor) {
            $contributor->contribute($document, $request);
        }

        $this->events->dispatch(new PageResolved($located, $request));

        return ResolveOutcome::found($located, $document);
    }

    /**
     * Steps 1 to 5: the address, the publication and the subject, without
     * rendering anything. The endpoint of a single block takes exactly this
     * much, because it looks the block up in the same snapshot.
     */
    public function locate(string $path, Request $request): ResolveOutcome
    {
        $resolved = $this->paths->resolve($path);

        if ($resolved->outcome === Resolution::LANGUAGE_PREFIX) {
            return ResolveOutcome::languagePrefix((string) $resolved->language);
        }

        $page = $resolved->page;

        if (! $resolved->found || $page === null || ! $page->is_active) {
            return ResolveOutcome::notFound();
        }

        $version = $this->renderer->versionOf($page);

        if ($version === null) {
            return ResolveOutcome::notFound();
        }

        $subject = $resolved->subject;

        if ($subject !== null) {
            SubjectContext::attach($request, $subject);
        }

        return ResolveOutcome::located(new LocatedPage($resolved, $page, $version));
    }

    /**
     * The body of the answer, see {@see
     * \Ewk\Cms\Pages\OpenApi\Schemas\ResolveSchemas}. Written before the
     * contributors run, so a contributor can read it.
     */
    private function describe(
        ResolveDocument $document,
        LocatedPage $located,
        RenderedBlocks $rendered,
        Request $request,
    ): void {
        $resolved = $located->resolved;
        $page = $located->page;
        $locale = $document->locale();

        $document->set('type', $resolved->type?->key());
        $document->set('path', $resolved->path);
        $document->set('page', [
            'uuid' => $page->uuid,
            // The caption in the language of the request, with the fallback
            // every translatable value of the CMS shares: requested,
            // default, empty.
            'name' => LocalizedValueResolver::resolve(
                $page->name,
                $locale,
                $this->languages->defaultLocale(),
            ),
            // The raw column: which layout is actually drawn is decided by the
            // layouts, which know whether the code names an active layout and
            // what the default one is. It replaces this value through a
            // contributor, exactly as the SEO part adds its metadata; without
            // that contributor nobody here knows more than the column.
            'layout_code' => $page->layout_code,
            // The header of the `text` layout shows it.
            'published_at' => $rendered->version->published_at?->toAtomString(),
        ]);
        $document->set('subject', $this->subjectOf($resolved, $request));
        $document->set('blocks', $rendered->blocks);
        $document->set('languages', $this->languages->locales());
    }

    /**
     * The entity behind a template address, as the type declared it should be
     * presented. A type without a presenter still answers with a subject and
     * an empty `data`: the address did resolve to an entity, and the frontend
     * must not have to guess whether it did.
     *
     * @return array{type: string, slug: string, data: non-empty-array<string, mixed>|stdClass}|null
     */
    private function subjectOf(ResolvedPath $resolved, Request $request): ?array
    {
        $subject = $resolved->subject;
        $type = $resolved->type;

        if ($subject === null || $type === null) {
            return null;
        }

        $declared = $type->subject();
        $slug = $subject->getAttribute($declared instanceof Subject ? $declared->slugColumn : 'slug');

        $data = $this->subjects->data($type, $subject, $request);

        return [
            'type' => $type->key(),
            'slug' => is_scalar($slug) ? (string) $slug : '',
            'data' => $data === [] ? new stdClass() : $data,
        ];
    }
}
