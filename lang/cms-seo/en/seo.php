<?php

declare(strict_types=1);

return [
    'section' => 'Metadata',

    'fields' => [
        'title' => 'Title',
        'description' => 'Description',
        'og_title' => 'Open Graph title',
        'og_description' => 'Open Graph description',
        'og_type' => 'Open Graph type',
        'og_image' => 'Open Graph image',
        'tw_card' => 'Twitter card',
        'tw_title' => 'Twitter title',
        'tw_description' => 'Twitter description',
        'tw_image' => 'Twitter image',
        'robots_index' => 'Allow indexing',
        'robots_follow' => 'Allow following links',
        'canonical' => 'Canonical address',
    ],

    // What goes into an empty field when the document is handed out. The form
    // says so out loud, otherwise an editor cannot tell an empty field from a
    // broken one.
    'fallbacks' => [
        'title' => 'Left empty: the name of the page or of the record is used.',
        'description' => 'Left empty: no description is sent. There is no fallback on purpose.',
        'og_title' => 'Left empty: the title above is used.',
        'og_description' => 'Left empty: the description above is used.',
        'og_type' => 'Left empty: `website` is sent, the neutral type every network understands.',
        'og_image' => 'One per language. Left empty: the image of the default language is used, and without one the cover of the record.',
        'tw_card' => 'Left empty: a large card when there is a picture, a small one otherwise.',
        'tw_title' => 'Left empty: the Open Graph title is used.',
        'tw_description' => 'Left empty: the Open Graph description is used.',
        'tw_image' => 'One per language. Left empty: the Twitter image of the default language is used, and without one the Open Graph image.',
        'canonical' => 'Left empty: the address of this page is used. A field for exceptions.',
    ],

    // The counter is a recommendation of the search engine and not a rule: the
    // value is never cut.
    'counters' => [
        'recommended' => 'Recommended length: up to :max characters.',
        'over' => 'Longer than recommended: search results may trim it.',
    ],

    'sitemap' => [
        'not_found' => 'There is no such sitemap file.',
    ],
];
