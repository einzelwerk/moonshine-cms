<?php

declare(strict_types=1);

/**
 * Refusals of the field types of the schema vocabulary. `:attribute` is
 * substituted by the validator itself, so the placeholder stays untouched here.
 */
return [
    'link' => [
        'unknown_kind' => 'The :attribute must declare a known link kind.',
        'unsupported_kind' => 'The [:kind] link kind has no registered resolver, so no address can be built for it.',
        'kind_not_allowed' => 'The :attribute may not use the [:kind] link kind.',
        'attributes_object' => 'The :attribute attributes must be an object.',
        'url_scheme_rejected' => 'The :attribute address uses a rejected scheme.',
        'url_scheme' => 'The :attribute address must use the http or https scheme.',
        'email' => 'The :attribute must carry a valid email address.',
        'phone' => 'The :attribute must carry a phone number in international format.',
        'target_required' => 'The :attribute must carry the [:target] target of the [:kind] link kind.',
        'too_long' => 'The [:target] target of the :attribute is longer than :max characters.',
        'params_object' => 'The :attribute parameters must be an object.',
        'unknown_action' => 'The [:action] action is not registered.',
        'anchor_format' => 'The :attribute anchor must be a lower case block identifier.',
        'unknown_anchor' => 'The chosen page carries no block under the [:anchor] anchor.',
    ],

    'media' => [
        'uuid' => 'The :attribute must reference a media record by its uuid.',
        'metadata' => 'The :attribute metadata must be an object.',
    ],

    'entity' => [
        'reference' => 'The :attribute must reference an entity by type and id.',
        'type_not_allowed' => 'The :attribute may not reference the [:type] entity type.',
    ],

    'coordinates' => [
        'numeric' => 'The :attribute must hold numeric lat and lng values.',
        'range' => 'The :attribute must hold coordinates within the valid range.',
    ],

    'multi_select' => [
        'options' => 'The :attribute contains a value outside the declared options.',
    ],
];
