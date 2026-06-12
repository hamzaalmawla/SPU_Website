<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'locale',
        'name',
        'email',
        'subject',
        'message',
        'status',
        'ip_address',
        'user_agent',
    ];
}
