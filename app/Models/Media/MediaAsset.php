<?php

declare(strict_types=1);

namespace App\Models\Media;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaAsset extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'disk',
        'directory',
        'filename',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'width',
        'height',
        'alt_text_ar',
        'alt_text_en',
        'caption_ar',
        'caption_en',
        'title_ar',
        'title_en',
        'path',
        'webp_path',
        'srcset_json',
        'uploaded_by',
        'faculty_scope_slug',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'srcset_json' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function seoMeta(): HasMany
    {
        return $this->hasMany(PageSeoMeta::class, 'og_image_media_id');
    }
}
