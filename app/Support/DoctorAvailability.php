<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\User;

/**
 * Builds the same 7-day slot grid used by GET /doctors/{id}/slots.
 */
final class DoctorAvailability
{
    /**
     * @return list<array{date: string, day_name: string, slots: list<string>}>
     */
    public static function nextSevenDaysSlots(User $doctor): array
    {
        if ($doctor->role !== 'doctor' || ! $doctor->is_active) {
            return [];
        }

        $today = now()->startOfDay();

        $appointments = Appointment::query()
            ->select(['appointment_date', 'appointment_time'])
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', '>=', $today->toDateString())
            ->whereIn('status', ['pending', 'confirmed', 'inProgress'])
            ->get()
            ->groupBy('appointment_date');

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $today->copy()->addDays($i);
            $dateKey = $date->toDateString();

            $takenTimes = ($appointments[$dateKey] ?? collect())
                ->pluck('appointment_time')
                ->map(fn ($time) => substr((string) $time, 0, 5))
                ->all();

            $slots = [];
            foreach (['09:00', '10:00', '11:00', '12:00', '13:00', '17:00', '18:00', '19:00'] as $time) {
                if (! in_array($time, $takenTimes, true)) {
                    $slots[] = $time;
                }
            }

            $days[] = [
                'date' => $dateKey,
                'day_name' => $date->translatedFormat('l'),
                'slots' => $slots,
            ];
        }

        return $days;
    }

    /**
     * @return array{date: string, time: string, day_name: string}|null
     */
    public static function firstAvailableSlot(User $doctor): ?array
    {
        foreach (self::nextSevenDaysSlots($doctor) as $day) {
            foreach ($day['slots'] as $time) {
                return [
                    'date' => $day['date'],
                    'time' => $time,
                    'day_name' => $day['day_name'],
                ];
            }
        }

        return null;
    }

    /**
     * Rough wait hint for demo UX (fixed slot cadence).
     */
    public static function estimatedWaitMinutes(): int
    {
        return 15;
    }
}
