<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Http\Controllers;

use Ewk\MoonShineCms\Links\TargetLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * What the link picker asks the server while the editor is choosing: pages,
 * records of one class, sections of a page and the filters of a catalogue. A
 * file is chosen in the library dialog and not out of a list here.
 *
 * The endpoints live on routes of the package and not on a resource: the very
 * same picker is drawn on the block editor, on the form of a menu item and on
 * the header and the footer, and none of those owns the others. They run
 * behind the middleware of the panel, so they answer an authenticated
 * administrator and nobody else.
 *
 * Every answer is the shape the select of MoonShine reads: either a map of
 * value to caption, or a list of options carrying their own thumbnail.
 */
final class LinkPickerController extends Controller
{
    public function __construct(private readonly TargetLookup $lookup)
    {
    }

    public function pages(Request $request): JsonResponse
    {
        return new JsonResponse(self::rows($this->lookup->pages(self::query($request))));
    }

    public function entities(Request $request): JsonResponse
    {
        return new JsonResponse(self::rows(
            $this->lookup->entities(self::types($request), self::query($request)),
        ));
    }

    public function anchors(Request $request): JsonResponse
    {
        return new JsonResponse(self::rows($this->lookup->anchors($request->input('page'))));
    }

    public function actions(): JsonResponse
    {
        return new JsonResponse(self::rows($this->lookup->actionOptions()));
    }

    /**
     * The lists of a catalogue page and the values their filters accept. The
     * answer is not a select of its own: the form draws one control per
     * declared filter out of it.
     */
    public function catalog(Request $request): JsonResponse
    {
        return new JsonResponse($this->lookup->catalog($request->input('page')));
    }

    /**
     * The select of the panel reads a list of options and refuses a map, see
     * the `fetchOptions` of its own component, so every answer is spelled out
     * as rows.
     *
     * @param  array<string, string>  $options
     * @return list<array{value: string, label: string}>
     */
    private static function rows(array $options): array
    {
        $rows = [];

        foreach ($options as $value => $label) {
            $rows[] = ['value' => (string) $value, 'label' => $label];
        }

        return $rows;
    }

    /**
     * One class or several: a field of a block may accept a venue and a
     * concert at once, and the panel asks for both in one call.
     *
     * @return list<string>
     */
    private static function types(Request $request): array
    {
        $type = $request->input('type');
        $types = is_array($type) ? $type : [$type];

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_string($item) ? $item : '', $types),
            static fn (string $item): bool => $item !== '',
        ));
    }

    private static function query(Request $request): string
    {
        $query = $request->input('query');

        return is_string($query) ? $query : '';
    }
}
