<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\DoctorRating;
use App\Models\User;
use App\Support\InAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $appointments = Appointment::with(['doctor:id,name,email,specialty,avatar,governorate,area', 'patient:id,name,email', 'rating'])
            ->when($user->role === 'patient', fn ($q) => $q->where('patient_id', $user->id))
            ->when($user->role === 'doctor', fn ($q) => $q->where('doctor_id', $user->id))
            ->latest()
            ->get();

        $rows = $appointments->map(function (Appointment $a) use ($user) {
            $row = [
                'id' => $a->id,
                'appointment_date' => $a->appointment_date,
                'appointment_time' => $a->appointment_time,
                'status' => $a->status,
                'notes' => $a->notes,
                'doctor' => $a->doctor,
                'patient' => $a->patient,
                'rating' => $a->rating ? [
                    'id' => $a->rating->id,
                    'rating' => $a->rating->rating,
                    'comment' => $a->rating->comment,
                ] : null,
            ];
            if ($user->role === 'patient') {
                $row['can_rate'] = $a->status === 'completed';
            }

            return $row;
        })->values();

        return response()->json(['appointments' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:users,id'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
        ]);

        $doctor = User::find($validated['doctor_id']);
        if (!$doctor || $doctor->role !== 'doctor') {
            return response()->json(['message' => 'Invalid doctor selected.'], 422);
        }

        $appointment = Appointment::create([
            'patient_id' => $user->id,
            'doctor_id' => $validated['doctor_id'],
            'appointment_date' => $validated['appointment_date'],
            'appointment_time' => $validated['appointment_time'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        InAppNotification::send(
            (int) $appointment->doctor_id,
            'New appointment booked',
            'A patient booked an appointment on '.$appointment->appointment_date.' at '.substr((string) $appointment->appointment_time, 0, 5),
            'appointment_booked',
            ['appointment_id' => $appointment->id]
        );

        InAppNotification::send(
            (int) $appointment->patient_id,
            'Appointment request sent',
            'Your booking request was submitted successfully.',
            'appointment_booked',
            ['appointment_id' => $appointment->id]
        );

        return response()->json([
            'message' => 'Appointment booked successfully',
            'appointment' => $appointment->load(['doctor:id,name,email', 'patient:id,name,email']),
        ], 201);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        if ($user->role === 'patient' && $appointment->patient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized appointment access.'], 403);
        }

        if ($user->role === 'doctor' && $appointment->doctor_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized appointment access.'], 403);
        }

        $validated = $request->validate([
            'appointment_date' => ['sometimes', 'date'],
            'appointment_time' => ['sometimes', 'date_format:H:i'],
            'status' => ['sometimes', Rule::in(Appointment::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        $statusBefore = $appointment->status;
        $dateBefore = (string) $appointment->appointment_date;
        $timeBefore = (string) $appointment->appointment_time;
        $appointment->update($validated);

        if (
            $statusBefore !== $appointment->status ||
            $dateBefore !== (string) $appointment->appointment_date ||
            $timeBefore !== (string) $appointment->appointment_time
        ) {
            $otherUserId = $user->role === 'patient' ? (int) $appointment->doctor_id : (int) $appointment->patient_id;
            InAppNotification::send(
                $otherUserId,
                'Appointment updated',
                'An appointment was updated to status: '.$appointment->status,
                'appointment_updated',
                ['appointment_id' => $appointment->id, 'status' => $appointment->status]
            );
        }

        return response()->json([
            'message' => 'Appointment updated successfully',
            'appointment' => $appointment->fresh()->load(['doctor:id,name,email', 'patient:id,name,email']),
        ]);
    }

    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        if ($user->role === 'patient' && $appointment->patient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized appointment access.'], 403);
        }

        if ($user->role === 'doctor' && $appointment->doctor_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized appointment access.'], 403);
        }

        $appointment->delete();

        return response()->json(['message' => 'Appointment cancelled successfully']);
    }

    public function rate(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'patient' || $appointment->patient_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized appointment access.'], 403);
        }
        if ($appointment->status !== 'completed') {
            return response()->json(['message' => 'You can only rate completed appointments.'], 422);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = DoctorRating::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'doctor_id' => $appointment->doctor_id,
                'patient_id' => $user->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        InAppNotification::send(
            (int) $appointment->doctor_id,
            'New patient rating',
            'A patient submitted a '.$record->rating.'/5 rating.',
            'doctor_rating',
            ['appointment_id' => $appointment->id, 'rating' => $record->rating]
        );

        return response()->json([
            'message' => 'Doctor rating saved successfully',
            'rating' => [
                'id' => $record->id,
                'rating' => $record->rating,
                'comment' => $record->comment,
            ],
        ]);
    }
}
