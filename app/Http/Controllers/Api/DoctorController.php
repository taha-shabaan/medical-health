<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\DoctorPatientStatus;
use App\Models\DoctorReport;
use App\Models\Prescription;
use App\Models\User;
use App\Services\SymptomDoctorAdvisor;
use App\Support\DoctorAvailability;
use App\Support\InAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DoctorController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = User::where('role', 'doctor')
            ->where('is_active', true);

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('specialty', 'like', '%'.$search.'%')
                    ->orWhere('area', 'like', '%'.$search.'%');
            });
        }

        if ($specialty = $request->query('specialty')) {
            $query->where('specialty', 'like', '%'.$specialty.'%');
        }

        $doctors = $query
            ->withAvg('doctorRatingsReceived as rating_avg', 'rating')
            ->orderByDesc('id')
            ->limit(50)
            ->get([
                'id', 'name', 'email', 'phone', 'gender', 'date_of_birth', 'avatar',
                'specialty', 'governorate', 'area', 'address', 'consultation_price',
            ]);

        return response()->json(['doctors' => $doctors]);
    }

    public function slots(User $doctor): JsonResponse
    {
        if ($doctor->role !== 'doctor' || ! $doctor->is_active) {
            return response()->json(['message' => 'Doctor not found.'], 404);
        }

        $days = DoctorAvailability::nextSevenDaysSlots($doctor);

        return response()->json([
            'doctor' => [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'email' => $doctor->email,
                'phone' => $doctor->phone,
                'avatar' => $doctor->avatar,
                'specialty' => $doctor->specialty,
                'governorate' => $doctor->governorate,
                'area' => $doctor->area,
                'address' => $doctor->address,
            ],
            'days' => $days,
        ]);
    }

    /**
     * Rule-based symptom → specialty + doctor shortlist with next slot (patient dashboard).
     */
    public function symptomAdvice(Request $request, SymptomDoctorAdvisor $advisor): JsonResponse
    {
        $validated = $request->validate([
            'symptoms' => ['required', 'string', 'max:2000'],
        ]);

        $patientGov = $request->user()?->governorate;
        $payload = $advisor->analyze($validated['symptoms'], is_string($patientGov) ? $patientGov : null);

        return response()->json($payload);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'specialty' => ['nullable', 'string', 'max:191'],
            'consultation_price' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'governorate' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:2000'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $request->user()->update($validated);

        return response()->json([
            'message' => 'Doctor profile updated successfully',
            'user' => $request->user()->fresh(),
        ]);
    }

    public function allSchedules(Request $request): JsonResponse
    {
        $appointments = Appointment::where('doctor_id', $request->user()->id)
            ->with('patient:id,name,email,date_of_birth,governorate,area,avatar')
            ->latest()
            ->get();

        return response()->json(['schedules' => $appointments]);
    }

    public function storeAppointment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:users,id'],
            'appointment_date' => ['required', 'date'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $patient = User::where('id', $validated['patient_id'])->where('role', 'patient')->first();
        if (! $patient) {
            return response()->json(['message' => 'Invalid patient.'], 422);
        }

        $appointment = Appointment::create([
            'patient_id' => $validated['patient_id'],
            'doctor_id' => $request->user()->id,
            'appointment_date' => $validated['appointment_date'],
            'appointment_time' => $validated['appointment_time'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'confirmed',
        ]);

        InAppNotification::send(
            (int) $appointment->patient_id,
            'New appointment from doctor',
            'Your doctor scheduled an appointment on '.$appointment->appointment_date.' at '.substr((string) $appointment->appointment_time, 0, 5),
            'appointment_booked',
            ['appointment_id' => $appointment->id]
        );

        return response()->json([
            'message' => 'Appointment created successfully',
            'appointment' => $appointment->load(['patient:id,name,email', 'doctor:id,name,email']),
        ], 201);
    }

    public function editSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'appointment_date' => ['sometimes', 'date'],
            'appointment_time' => ['sometimes', 'date_format:H:i'],
            'status' => ['sometimes', Rule::in(Appointment::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        $appointment = Appointment::findOrFail($validated['appointment_id']);
        if ($appointment->doctor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized schedule access.'], 403);
        }

        $oldStatus = $appointment->status;
        unset($validated['appointment_id']);
        $appointment->update($validated);

        if ($oldStatus !== $appointment->status) {
            InAppNotification::send(
                (int) $appointment->patient_id,
                'Appointment status updated',
                'Your appointment status is now: '.$appointment->status,
                'appointment_updated',
                ['appointment_id' => $appointment->id, 'status' => $appointment->status]
            );
        }

        return response()->json([
            'message' => 'Schedule updated successfully',
            'schedule' => $appointment->fresh()->load('patient:id,name,email'),
        ]);
    }

    public function patients(Request $request): JsonResponse
    {
        $doctorId = $request->user()->id;

        $careByPatient = DoctorPatientStatus::where('doctor_id', $doctorId)
            ->get()
            ->keyBy('patient_id');

        $rows = Appointment::where('doctor_id', $doctorId)
            ->with('patient:id,name,email,phone,gender,date_of_birth,avatar')
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->get()
            ->groupBy('patient_id')
            ->map(function ($items) use ($careByPatient) {
                $latest = $items->first();
                $patient = $latest?->patient;
                if (! $patient) {
                    return null;
                }

                $care = $careByPatient->get($patient->id);

                return [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email,
                    'phone' => $patient->phone,
                    'gender' => $patient->gender,
                    'date_of_birth' => $patient->date_of_birth,
                    'last_visit' => $latest->appointment_date,
                    'appointments_count' => $items->count(),
                    'care_status' => $care?->status ?? 'active',
                    'avatar' => $patient->avatar,
                ];
            })
            ->filter()
            ->values();

        return response()->json(['patients' => $rows]);
    }

    /**
     * Register a new patient account and link them to this doctor (care status + initial appointment).
     */
    public function storePatient(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
        ]);

        $patient = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'role' => 'patient',
            'is_active' => true,
        ]);

        $doctorId = $request->user()->id;

        DoctorPatientStatus::updateOrCreate(
            ['doctor_id' => $doctorId, 'patient_id' => $patient->id],
            ['status' => 'active']
        );

        $now = now();
        Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctorId,
            'appointment_date' => $now->toDateString(),
            'appointment_time' => $now->format('H:i'),
            'notes' => 'New patient registration (doctor dashboard)',
            'status' => 'confirmed',
        ]);

        return response()->json([
            'message' => 'Patient created successfully',
            'patient' => $patient->fresh()->only(['id', 'name', 'email', 'phone', 'gender', 'date_of_birth', 'role', 'created_at']),
        ], 201);
    }

    public function updatePatientCareStatus(Request $request, int $patient): JsonResponse
    {
        $patientUser = User::where('id', $patient)->where('role', 'patient')->first();

        if (! $patientUser) {
            return response()->json(['message' => 'Patient not found.'], 404);
        }

        $doctorId = $request->user()->id;

        $hasAppointment = Appointment::where('doctor_id', $doctorId)
            ->where('patient_id', $patientUser->id)
            ->exists();

        if (! $hasAppointment) {
            return response()->json(['message' => 'Patient not found in your list.'], 404);
        }

        $validated = $request->validate([
            'care_status' => ['required', 'in:active,follow_up,stable,archived'],
        ]);

        DoctorPatientStatus::updateOrCreate(
            ['doctor_id' => $doctorId, 'patient_id' => $patientUser->id],
            ['status' => $validated['care_status']]
        );

        return response()->json([
            'message' => 'Patient status updated',
            'patient_id' => $patientUser->id,
            'care_status' => $validated['care_status'],
        ]);
    }

    public function patientById(Request $request, User $patient): JsonResponse
    {
        $doctorId = $request->user()->id;

        $appointments = Appointment::where('doctor_id', $doctorId)
            ->where('patient_id', $patient->id)
            ->latest()
            ->get();

        if ($appointments->isEmpty()) {
            return response()->json(['message' => 'Patient not found in your list.'], 404);
        }

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->name,
                'email' => $patient->email,
                'phone' => $patient->phone,
                'gender' => $patient->gender,
                'date_of_birth' => $patient->date_of_birth,
            ],
            'appointments' => $appointments,
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $reports = DoctorReport::where('doctor_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['reports' => $reports]);
    }

    public function storeReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['required', 'in:lab,xray,periodic,rx'],
            'report_date' => ['nullable', 'date'],
            'details' => ['nullable', 'string'],
            'status' => ['nullable', 'in:completed,underReview,pending'],
        ]);

        $report = DoctorReport::create([
            ...$validated,
            'doctor_id' => $request->user()->id,
            'status' => $validated['status'] ?? 'underReview',
        ]);

        return response()->json([
            'message' => 'Report created successfully',
            'report' => $report,
        ], 201);
    }

    public function prescriptions(Request $request): JsonResponse
    {
        $prescriptions = Prescription::where('doctor_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['prescriptions' => $prescriptions]);
    }

    public function storePrescription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_id' => ['nullable', 'integer', 'exists:users,id'],
            'diagnosis' => ['nullable', 'string'],
            'drug' => ['required', 'string'],
            'prescribed_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,ended'],
            'notes' => ['nullable', 'string'],
        ]);

        $prescription = Prescription::create([
            ...$validated,
            'doctor_id' => $request->user()->id,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Prescription created successfully',
            'prescription' => $prescription,
        ], 201);
    }

    public function deletePrescription(Request $request, int $prescription): JsonResponse
    {
        $model = Prescription::where('id', $prescription)->first();

        if (! $model) {
            return response()->json(['message' => 'Prescription not found.'], 404);
        }

        if ($model->doctor_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $model->delete();

        return response()->json(['message' => 'Prescription deleted successfully']);
    }

    public function updatePrescription(Request $request, int $prescription): JsonResponse
    {
        $model = Prescription::where('id', $prescription)->first();

        if (! $model) {
            return response()->json(['message' => 'Prescription not found.'], 404);
        }

        if ($model->doctor_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:active,ended'],
        ]);

        $model->update($validated);

        return response()->json([
            'message' => 'Prescription updated',
            'prescription' => $model->fresh(),
        ]);
    }

    public function updateReport(Request $request, int $report): JsonResponse
    {
        $model = DoctorReport::where('id', $report)
            ->where('doctor_id', $request->user()->id)
            ->first();

        if (! $model) {
            return response()->json(['message' => 'Report not found.'], 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:completed,underReview,pending'],
        ]);

        $model->update($validated);

        return response()->json([
            'message' => 'Report updated',
            'report' => $model->fresh(),
        ]);
    }
}
