<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Models;

use Ewk\Cms\Blocks\Concerns\HasBlocks;
use Ewk\Cms\Blocks\Contracts\DeclaresRequiredBlocks;
use Ewk\Cms\Pages\Support\PathNormalizer;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A node of the page tree. Parent and position are the truth, the path is
 * derived and carries the unique index; writing either of them by hand bypasses
 * the checks of {@see \Ewk\Cms\Pages\Services\PageTree}.
 *
 * A page owns blocks, which is the whole reason it exists as a row.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $parent_id
 * @property string $type
 * @property array<string, string> $name
 * @property string $slug
 * @property string $path
 * @property int $depth
 * @property int $sort_order
 * @property string|null $layout_code
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CmsPage|null $parent
 * @property-read Collection<int, CmsPage> $children
 */
// Not final: an installation extends this model to mix in what belongs to it
// and not to the CMS — translatable captions, metadata, its own factory. Traits
// carrying instance methods cannot be attached from the outside, and the
// storage of a page is the same either way, so the subclass stays a subclass
// and the package keeps knowing nothing about it.
class CmsPage extends Model implements DeclaresRequiredBlocks
{
    use HasBlocks;

    /**
     * What the polymorphic columns of the CMS store for a page, whichever
     * class of the hierarchy wrote the row: the package creates pages too
     * (assembling a template), and the application reads them back through
     * its own subclass. Two class names in `blockable_type` would split one
     * page into two owners. The provider maps the alias back to the class of
     * the installation, see {@see \Ewk\Cms\Pages\Support\PageModel}.
     */
    public const string MORPH_ALIAS = 'cms_page';

    protected $fillable = [
        'uuid',
        'parent_id',
        'type',
        'name',
        'slug',
        'path',
        'depth',
        'sort_order',
        'layout_code',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'name' => 'array',
        'parent_id' => 'integer',
        'depth' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (CmsPage $page): void {
            $page->uuid = $page->uuid ?: (string) Str::uuid();
        });
    }

    public function getMorphClass(): string
    {
        return self::MORPH_ALIAS;
    }

    /**
     * @return BelongsTo<CmsPage, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<CmsPage, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * The whole subtree in one query by path prefix. The separator is part of
     * the prefix, so `/concert` never captures `/concert-hall`.
     *
     * @param  Builder<CmsPage>  $query
     * @return Builder<CmsPage>
     */
    public function scopeUnderPath(Builder $query, string $path): Builder
    {
        $prefix = $path === PathNormalizer::ROOT ? PathNormalizer::ROOT : $path.PathNormalizer::ROOT;

        return $query->where('path', 'like', $prefix.'%')->where('path', '!=', $path);
    }

    /**
     * The block codes the page type demands. Publication of the blocks package
     * asks the owner for them and reports the missing ones; the core keeps
     * knowing nothing about page types.
     *
     * A type absent from the registry demands nothing: a row may carry a type
     * an installation has not declared, and publication is not the place to
     * discover it.
     *
     * @return list<string>
     */
    public function requiredBlockCodes(): array
    {
        $registry = Container::getInstance()->make(PageTypeRegistry::class);

        return $registry->has($this->type) ? $registry->get($this->type)->required() : [];
    }
}
