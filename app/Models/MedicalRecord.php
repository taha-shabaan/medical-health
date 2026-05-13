<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalRecord extends Model
{
    protected $fillable = [
        'patient_id',
        'blood_pressure',
        'blood_sugar',
        'body_weight',
        'body_temperature',
        'allergies',
        'chronic_conditions',
        'medications',
        'surgeries',
        'family_history',
        'notes',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MedicalRecordAttachment::class, 'medical_record_id');
    }
}
