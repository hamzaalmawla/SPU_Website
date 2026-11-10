<?php

declare(strict_types=1);

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonAppointmentTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_appointment_id',
        'locale',
        'role_override',
    ];

    public function personAppointment(): BelongsTo
    {
        return $this->belongsTo(PersonAppointment::class);
    }
}
