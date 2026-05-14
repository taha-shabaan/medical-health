<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'gender',
        'height',
        'blood_type',
        'weight',
        'governorate',
        'area',
        'address',
        'bio',
        'date_of_birth',
        'avatar',
        'specialty',
        'consultation_price',
        'role',
        'is_active',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'height' => 'integer',
            'consultation_price' => 'integer',
        ];
    }

    public function patientAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function doctorAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class, 'patient_id');
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class, 'user_id');
    }

    public function doctorRatingsReceived(): HasMany
    {
        return $this->hasMany(DoctorRating::class, 'doctor_id');
    }

    public function doctorRatingsGiven(): HasMany
    {
        return $this->hasMany(DoctorRating::class, 'patient_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'user_id');
    }
}
