<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    protected $fillable = [
        'site_name',
        'site_email',
        'site_phone',
        'address',
        'email_notifications',
        'sms_notifications',
        'new_appointment_alert',
        'payment_alert',
        'language',
        'max_appointments_per_day',
        'appointment_duration',
    ];

    public function toApiArray(): array
    {
        return [
            'siteName' => $this->site_name,
            'siteEmail' => $this->site_email,
            'sitePhone' => $this->site_phone,
            'address' => $this->address,
            'emailNotifications' => (bool) $this->email_notifications,
            'smsNotifications' => (bool) $this->sms_notifications,
            'newAppointmentAlert' => (bool) $this->new_appointment_alert,
            'paymentAlert' => (bool) $this->payment_alert,
            'language' => $this->language,
            'maxAppointmentsPerDay' => (string) $this->max_appointments_per_day,
            'appointmentDuration' => (string) $this->appointment_duration,
        ];
    }
}
