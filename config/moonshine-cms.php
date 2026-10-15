<?php

declare(strict_types=1);

return [
    /*
     * Which MoonShine field renders a rich text value. MoonShine 4.19 itself
     * ships no rich text editor, so the neutral default is a textarea; an
     * application that installs one (for instance `moonshine/tinymce`) points
     * this at its field class instead of this package growing the dependency.
     */
    'rich_text_field' => \MoonShine\UI\Fields\Textarea::class,

    /*
     * How many rows of a target model the entity picker offers. The picker is
     * a searchable select, not a browser of the whole table.
     */
    'entity_options_limit' => 100,

    /*
     * Which icon the insertion library draws for a block type. The key is the
     * code of the block, or the code and a variant separated by a dot; the
     * value is a name of the icon set MoonShine already ships, so nothing is
     * added to the dependencies. A variant without an entry of its own takes
     * the icon of its type, and a code without one takes a neutral glyph.
     *
     * An installation adds the icons of its own blocks here. The key is
     * merged whole, not per entry, so an application that publishes this file
     * repeats the four standard types below next to its own.
     */
    'block_icons' => [
        'text' => 'document-text',
        'toc' => 'list-bullet',
        'columns' => 'view-columns',
        'shared' => 'rectangle-stack',
    ],

    /*
     * The container widths a layout may take. The value is a word of the
     * frontend, exactly as the code of a layout is, so the list is closed: a
     * width without a rule in the stylesheet there is nothing. The caption of
     * each one is read from `moonshine-cms::chrome.containers`, and a width an
     * installation adds shows its own word until it is translated.
     */
    'container_widths' => ['wide', 'narrow', 'full'],

    /*
     * The resource of pages. An application that adds a tab of its own to
     * the form, or otherwise extends the section, names its subclass of
     * \Ewk\MoonShineCms\MoonShine\Resources\PageResource here; the package
     * registers that class and hands it out wherever the page resource is
     * asked for.
     */
    'page_resource' => \Ewk\MoonShineCms\MoonShine\Resources\PageResource::class,
];
