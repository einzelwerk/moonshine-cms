<?php

declare(strict_types=1);

use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Container\Container;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The picture of a card is a dictionary of languages like every text of the
 * card: a language version is shared on its own and carries its own caption on
 * the picture. `og_image_id` and `tw_image_id` become `og_image` and
 * `tw_image`, `{"ru": 12, "en": 15}`.
 *
 * A picture that was there goes under the default language, the one every
 * other language falls back to, so each language version shows exactly what it
 * showed before. The usages of the library follow: the column is the field of
 * the usage and `og_image.ru` its path.
 */
return new class extends Migration
{
    /** @var array<string, string> the old column => the new one */
    private const array COLUMNS = [
        'og_image_id' => 'og_image',
        'tw_image_id' => 'tw_image',
    ];

    public function up(): void
    {
        Schema::table('cms_metadata', function (Blueprint $table): void {
            $table->json('og_image')->nullable()->after('og_type');
            $table->json('tw_image')->nullable()->after('tw_description');
        });

        $locale = self::defaultLocale();

        foreach (self::COLUMNS as $old => $new) {
            DB::table('cms_metadata')
                ->whereNotNull($old)
                ->orderBy('id')
                ->select(['id', $old])
                ->each(static function (object $row) use ($old, $new, $locale): void {
                    DB::table('cms_metadata')
                        ->where('id', $row->id)
                        ->update([$new => json_encode([$locale => (int) $row->{$old}])]);
                });

            if (Schema::hasTable('cms_media_usages')) {
                DB::table('cms_media_usages')
                    ->where('usable_type', 'cms_metadata')
                    ->where('field', $old)
                    ->update(['field' => $new, 'path' => $new.'.'.$locale]);
            }
        }

        Schema::table('cms_metadata', function (Blueprint $table): void {
            $table->dropColumn(array_keys(self::COLUMNS));
        });
    }

    /**
     * Only the default language survives a rollback: the old column holds one
     * picture, and that is the one every language used to fall back to.
     */
    public function down(): void
    {
        Schema::table('cms_metadata', function (Blueprint $table): void {
            $table->unsignedBigInteger('og_image_id')->nullable()->after('og_type');
            $table->unsignedBigInteger('tw_image_id')->nullable()->after('tw_description');
        });

        $locale = self::defaultLocale();

        foreach (self::COLUMNS as $old => $new) {
            DB::table('cms_metadata')
                ->whereNotNull($new)
                ->orderBy('id')
                ->select(['id', $new])
                ->each(static function (object $row) use ($old, $new, $locale): void {
                    $decoded = json_decode((string) $row->{$new}, true);
                    $id = is_array($decoded) ? ($decoded[$locale] ?? null) : null;

                    DB::table('cms_metadata')
                        ->where('id', $row->id)
                        ->update([$old => is_int($id) ? $id : null]);
                });

            if (Schema::hasTable('cms_media_usages')) {
                DB::table('cms_media_usages')
                    ->where('usable_type', 'cms_metadata')
                    ->where('field', $new)
                    ->where('path', '!=', $new.'.'.$locale)
                    ->delete();

                DB::table('cms_media_usages')
                    ->where('usable_type', 'cms_metadata')
                    ->where('field', $new)
                    ->update(['field' => $old, 'path' => $old]);
            }
        }

        Schema::table('cms_metadata', function (Blueprint $table): void {
            $table->dropColumn(array_values(self::COLUMNS));
        });
    }

    private static function defaultLocale(): string
    {
        return Container::getInstance()->make(LanguageCatalog::class)->defaultLocale();
    }
};
