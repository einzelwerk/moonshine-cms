<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Actions;

use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\Cms\Blocks\Schema\ValidationRuleBuilder;
use Ewk\MoonShineCms\Editor\ContentAssembler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The write of the declared content of a record that is not built out of
 * blocks: the header and the footer of the site ({@see SaveChrome}) and any
 * record of an installation whose form draws a schema through
 * {@see \Ewk\MoonShineCms\Adapters\SchemaContentForm}.
 *
 * The content is a nested value inside one json column, and the generic apply
 * of the panel cannot reach into it: `data_set()` on a cast attribute loses the
 * write without a word. So the pipeline the block editor uses owns the value
 * here: {@see ContentAssembler} turns the submitted form into the declared
 * shape, and the rules and the captions derived from the very same schema
 * validate it. A picture is a reference by the time it arrives: it was uploaded
 * when it was chosen.
 */
final readonly class SaveSchemaContent
{
    public function __construct(
        private ValidationRuleBuilder $rules,
        private ContentAssembler $assembler,
    ) {
    }

    /**
     * Assembles, validates and saves; nothing is written when the content is
     * refused.
     *
     * @param  array<array-key, mixed>  $raw  the submitted `content` of the form
     *
     * @throws ValidationException
     */
    public function __invoke(Model $record, BlockSchema $schema, array $raw, string $attribute = 'content'): void
    {
        $content = $this->assembler->assemble($schema, $raw);

        Validator::make(
            ['content' => $content],
            ['content' => ['array'], ...$this->rules->build($schema)],
            [],
            $this->rules->attributes($schema),
        )->validate();

        // The stored shape is owned by the fields, not by the validator: a key
        // the rules do not mention would silently disappear from `validated()`.
        $record->setAttribute($attribute, $content);

        $record->save();
    }
}
