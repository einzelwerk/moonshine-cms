<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Actions;

use Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The write of the header and footer forms, mounted on their resources as the
 * save handler. The declared content of the kind is written by
 * {@see SaveSchemaContent}, see the reasons there; a kind that declares none
 * saves the record alone.
 */
final readonly class SaveChrome
{
    public function __construct(
        private ChromeSchemaRegistry $schemas,
        private SaveSchemaContent $content,
        private Request $request,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  the submitted form, already applied to the model
     *
     * @throws ValidationException
     */
    public function __invoke(CmsHeader|CmsFooter $record, array $data): CmsHeader|CmsFooter
    {
        $kind = $record->chromeKind();

        if (! $this->schemas->has($kind)) {
            $record->save();

            return $record;
        }

        $raw = $this->request->input('content', []);

        ($this->content)($record, $this->schemas->schemaFor($kind)->toBlockSchema(), is_array($raw) ? $raw : []);

        return $record;
    }
}
