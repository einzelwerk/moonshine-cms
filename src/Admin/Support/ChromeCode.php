<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The code of a layout, a header, a footer or a menu: the contract with the
 * frontend, typed by whoever ships the component there. One rule for all
 * four forms.
 */
final class ChromeCode
{
    public const string PATTERN = '/^[a-z][a-z0-9_-]*$/';

    /**
     * @return list<string>
     */
    public static function rules(string $table, int|string|null $exceptKey): array
    {
        return [
            'required',
            'string',
            'max:64',
            'regex:'.self::PATTERN,
            'unique:'.$table.',code,'.($exceptKey ?? 'NULL'),
        ];
    }

    /**
     * The code an editor did not type, built from the name in the default
     * language. The form fills the field in front of the editor as well; this
     * is the same value derived on the server, so a browser that runs no
     * javascript stores the same thing.
     *
     * A code that is there stays untouched: renaming a record never rewrites
     * the address its links already carry.
     */
    public static function derive(Request $request, string $defaultLocale): void
    {
        $typed = $request->input('code');

        if (is_string($typed) && trim($typed) !== '') {
            return;
        }

        $name = $request->input('name');
        $caption = is_array($name) ? ($name[$defaultLocale] ?? null) : $name;

        if (! is_string($caption) || trim($caption) === '') {
            return;
        }

        $code = Str::slug($caption, '-', $defaultLocale);

        if ($code === '') {
            return;
        }

        $request->merge(['code' => $code]);
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'code.regex' => (string) __('moonshine-cms::chrome.code_kebab'),
        ];
    }
}
