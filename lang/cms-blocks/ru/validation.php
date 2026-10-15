<?php

declare(strict_types=1);

/**
 * Refusals of the field types of the schema vocabulary. `:attribute` is
 * substituted by the validator itself, so the placeholder stays untouched here.
 */
return [
    'link' => [
        'unknown_kind' => 'Поле :attribute должно называть известный вид ссылки.',
        'unsupported_kind' => 'Для вида ссылки [:kind] не зарегистрирован резолвер, поэтому адрес по нему не собрать.',
        'kind_not_allowed' => 'Поле :attribute не принимает вид ссылки [:kind].',
        'attributes_object' => 'Цель ссылки в поле :attribute должна быть объектом.',
        'url_scheme_rejected' => 'Адрес в поле :attribute использует запрещённую схему.',
        'url_scheme' => 'Адрес в поле :attribute должен использовать схему http или https.',
        'email' => 'Поле :attribute должно нести корректный адрес почты.',
        'phone' => 'Поле :attribute должно нести номер телефона в международном формате.',
        'target_required' => 'Поле :attribute должно нести цель [:target] для вида ссылки [:kind].',
        'too_long' => 'Цель [:target] в поле :attribute длиннее :max символов.',
        'params_object' => 'Параметры ссылки в поле :attribute должны быть объектом.',
        'unknown_action' => 'Действие [:action] не зарегистрировано.',
        'anchor_format' => 'Якорь в поле :attribute должен быть идентификатором блока в нижнем регистре.',
        'unknown_anchor' => 'На указанной странице нет блока с якорем [:anchor].',
    ],

    'media' => [
        'uuid' => 'Поле :attribute должно ссылаться на запись медиа по её uuid.',
        'metadata' => 'Свойство metadata в поле :attribute должно быть объектом.',
    ],

    'entity' => [
        'reference' => 'Поле :attribute должно ссылаться на запись по типу и идентификатору.',
        'type_not_allowed' => 'Поле :attribute не принимает ссылку на тип [:type].',
    ],

    'coordinates' => [
        'numeric' => 'Поле :attribute должно нести числовые lat и lng.',
        'range' => 'Поле :attribute должно нести координаты в допустимом диапазоне.',
    ],

    'multi_select' => [
        'options' => 'Поле :attribute содержит значение вне объявленного списка.',
    ],
];
