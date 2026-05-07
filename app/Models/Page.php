<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Non-localized page shell record.
     *
     * content_json is reserved for shell-level data. Localized runtime content lives on
     * page_translations and should win when both shapes are present.
     */

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'type',
        'template',
        'slug',
        'faculty_scope_slug',
        'status',
        'sort_order',
        'is_enabled',
        'show_in_breadcrumbs',
        'show_in_nav',
        'is_homepage_shell',
        'publish_at',
        'published_at',
        'created_by',
        'updated_by',
        'approved_by',
        'last_reviewed_at',
        'layout_key',
        'builder_schema_version',
        'content_json',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
            'show_in_breadcrumbs' => 'boolean',
            'show_in_nav' => 'boolean',
            'is_homepage_shell' => 'boolean',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
            'last_reviewed_at' => 'date',
            'builder_schema_version' => 'integer',
            'content_json' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class)->orderBy('locale');
    }

    public function seoMeta(): HasMany
    {
        return $this->hasMany(PageSeoMeta::class)->orderBy('locale');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(PageDraft::class)->latest('updated_at');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'target_id')->where('target_kind', 'page');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at');
    }
}
