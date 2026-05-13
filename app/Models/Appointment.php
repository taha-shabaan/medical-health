<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    /** @var list<string> */
    public const STATUSES = ['pending', 'confirmed', 'inProgress', 'completed', 'cancelled'];

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_date',
        'appointment_time',
        'status',
        'notes',
    ];

    protected static function booted(): void
    {
        static::created(function (Appointment $appointment): void {
            Invoice::firstOrCreate(
                ['appointment_id' => $appointment->id],
                [
                    'invoice_number' => 'INV-'.str_pad((string) $appointment->id, 4, '0', STR_PAD_LEFT),
                    'patient_id' => $appointment->patient_id,
                    'doctor_id' => $appointment->doctor_id,
                    'service' => $appointment->notes ?: 'General consultation',
                    'amount' => 300,
                    'currency' => 'EGP',
                    'status' => 'unpaid',
                ]
            );
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function rating(): HasOne
    {
        return $this->hasOne(DoctorRating::class, 'appointment_id');
    }
}
