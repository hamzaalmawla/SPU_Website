<?php

declare(strict_types=1);

namespace App\Models\Form;

use Illuminate\Database\Eloquent\Model;

class FormDefinition extends Model
{
    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'inbox',
        'schema_json',
        'is_enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'schema_json' => 'array',
        'is_enabled' => 'boolean',
    ];
}
