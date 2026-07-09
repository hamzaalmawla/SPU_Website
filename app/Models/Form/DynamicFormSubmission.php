<?php

declare(strict_types=1);

namespace App\Models\Form;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DynamicFormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'locale',
        'applicant_name',
        'applicant_email',
        'status',
        'payload_json',
        'files_json',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'files_json' => 'array',
    ];
}
