<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\AiKnowledgeEntry;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\User;
use App\Support\InAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function allDoctors(): JsonResponse
    {
        return response()->json([
            'doctors' => User::where('role', 'doctor')->latest()->get(),
        ]);
    }

    public function allAdmins(): JsonResponse
    {
        return response()->json([
            'admins' => User::where('role', 'admin')->latest()->get(),
        ]);
    }

    public function addAdmin(Request $request): JsonResponse
    {
        return $this->createStaff($request, 'admin', 'Admin added successfully');
    }

    public function deleteAdmin(User $admin): JsonResponse
    {
        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Admin not found.'], 404);
        }

        $admin->delete();

        return response()->json(['message' => 'Admin deleted successfully']);
    }

    public function doctorById(User $doctor): JsonResponse
    {
        if ($doctor->role !== 'doctor') {
            return response()->json(['message' => 'Doctor not found.'], 404);
        }

        return response()->json(['doctor' => $doctor]);
    }

    public function addDoctor(Request $request): JsonResponse
    {
        return $this->createStaff($request, 'doctor', 'Doctor added successfully');
    }

    public function editDoctor(Request $request, User $doctor): JsonResponse
    {
        if ($doctor->role !== 'doctor') {
            return response()->json(['message' => 'Doctor not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$doctor->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'specialty' => ['nullable', 'string', 'max:191'],
            'governorate' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        $doctor->update($validated);

        return response()->json([
            'message' => 'Doctor updated successfully',
            'doctor' => $doctor->fresh(),
        ]);
    }

    public function editDoctorWithAvatar(Request $request, User $doctor): JsonResponse
    {
        if ($doctor->role !== 'doctor') {
            return response()->json(['message' => 'Doctor not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$doctor->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'specialty' => ['nullable', 'string', 'max:191'],
            'governorate' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:2000'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $doctor->update($validated);

        return response()->json([
            'message' => 'Doctor updated successfully',
            'doctor' => $doctor->fresh(),
        ]);
    }

    public function deleteDoctor(User $doctor): JsonResponse
    {
        if ($doctor->role !== 'doctor') {
            return response()->json(['message' => 'Doctor not found.'], 404);
        }

        $doctor->delete();

        return response()->json(['message' => 'Doctor deleted successfully']);
    }

    public function changeDoctorStatus(User $doctor): JsonResponse
    {
        if ($doctor->role !== 'doctor') {
            return response()->json(['message' => 'Doctor not found.'], 404);
        }

        $doctor->update(['is_active' => ! $doctor->is_active]);

        return response()->json([
            'message' => 'Doctor status changed successfully',
            'doctor' => $doctor->fresh(),
        ]);
    }

    public function allPatients(): JsonResponse
    {
        return response()->json([
            'patients' => User::where('role', 'patient')->latest()->get(),
        ]);
    }

    public function patientById(User $patient): JsonResponse
    {
        if ($patient->role !== 'patient') {
            return response()->json(['message' => 'Patient not found.'], 404);
        }

        return response()->json(['patient' => $patient]);
    }

    public function addPatient(Request $request): JsonResponse
    {
        return $this->createStaff($request, 'patient', 'Patient added successfully');
    }

    public function editPatient(Request $request, User $patient): JsonResponse
    {
        if ($patient->role !== 'patient') {
            return response()->json(['message' => 'Patient not found.'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$patient->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $patient->update($validated);

        return response()->json([
            'message' => 'Patient updated successfully',
            'patient' => $patient->fresh(),
        ]);
    }

    public function deletePatient(User $patient): JsonResponse
    {
        if ($patient->role !== 'patient') {
            return response()->json(['message' => 'Patient not found.'], 404);
        }

        $patient->delete();

        return response()->json(['message' => 'Patient deleted successfully']);
    }

    public function allAppointments(): JsonResponse
    {
        return response()->json([
            'appointments' => Appointment::with(['doctor:id,name,email,specialty', 'patient:id,name,email'])->latest()->get(),
        ]);
    }

    public function appointmentById(Appointment $appointment): JsonResponse
    {
        return response()->json([
            'appointment' => $appointment->load(['doctor:id,name,email', 'patient:id,name,email']),
        ]);
    }

    public function editAppointment(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'appointment_date' => ['sometimes', 'date'],
            'appointment_time' => ['sometimes', 'date_format:H:i'],
            'status' => ['sometimes', Rule::in(Appointment::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        $oldStatus = $appointment->status;
        $appointment->update($validated);

        if ($oldStatus !== $appointment->status) {
            InAppNotification::send(
                (int) $appointment->patient_id,
                'Appointment status updated',
                'Your appointment status is now: '.$appointment->status,
                'appointment_updated',
                ['appointment_id' => $appointment->id, 'status' => $appointment->status]
            );
            InAppNotification::send(
                (int) $appointment->doctor_id,
                'Appointment status updated by admin',
                'Appointment status is now: '.$appointment->status,
                'appointment_updated',
                ['appointment_id' => $appointment->id, 'status' => $appointment->status]
            );
        }

        return response()->json([
            'message' => 'Appointment updated successfully',
            'appointment' => $appointment->fresh()->load(['doctor:id,name,email', 'patient:id,name,email']),
        ]);
    }

    public function deleteAppointment(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return response()->json(['message' => 'Appointment deleted successfully']);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'stats' => [
                'total_doctors' => User::where('role', 'doctor')->count(),
                'total_patients' => User::where('role', 'patient')->count(),
                'total_admins' => User::where('role', 'admin')->count(),
                'total_appointments' => Appointment::count(),
                'pending_appointments' => Appointment::where('status', 'pending')->count(),
                'completed_appointments' => Appointment::where('status', 'completed')->count(),
            ],
        ]);
    }

    public function invoicesReport(): JsonResponse
    {
        $invoices = Invoice::with([
            'doctor:id,name',
            'patient:id,name',
            'appointment:id,appointment_date,created_at',
        ])
            ->latest()
            ->get();

        $rows = $invoices->map(function (Invoice $invoice) {
            $appointmentDate = $invoice->appointment?->appointment_date;
            $dateStr = $appointmentDate instanceof \DateTimeInterface
                ? $appointmentDate->format('Y-m-d')
                : ($appointmentDate ?: optional($invoice->created_at)->toDateString());

            return [
                'id' => $invoice->invoice_number,
                'patient' => $invoice->patient?->name ?? '-',
                'doctor' => $invoice->doctor?->name ?? '-',
                'service' => $invoice->service,
                'amount' => (float) $invoice->amount,
                'date' => $dateStr,
                'status' => $invoice->status,
                'payment_method' => $invoice->payment_method,
            ];
        })->values();

        $paid = $rows->where('status', 'paid');
        $unpaid = $rows->where('status', 'unpaid');

        return response()->json([
            'invoices' => $rows,
            'summary' => [
                'total_revenue' => $paid->sum('amount'),
                'pending_amount' => $unpaid->sum('amount'),
                'invoices_count' => $rows->count(),
            ],
        ]);
    }

    public function getSettings(): JsonResponse
    {
        $settings = AdminSetting::query()->first();

        if (! $settings) {
            $settings = AdminSetting::query()->create([
                'site_name' => 'Lesahtak',
                'site_email' => 'admin@lesahtak.com',
                'site_phone' => '01012345678',
                'address' => 'Beni Suef, Egypt',
                'email_notifications' => true,
                'sms_notifications' => false,
                'new_appointment_alert' => true,
                'payment_alert' => true,
                'language' => 'ar',
                'max_appointments_per_day' => 20,
                'appointment_duration' => 30,
            ]);
        }

        return response()->json(['settings' => $settings->toApiArray()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'siteName' => ['required', 'string', 'max:255'],
            'siteEmail' => ['required', 'email', 'max:255'],
            'sitePhone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'],
            'emailNotifications' => ['required', 'boolean'],
            'smsNotifications' => ['required', 'boolean'],
            'newAppointmentAlert' => ['required', 'boolean'],
            'paymentAlert' => ['required', 'boolean'],
            'language' => ['required', 'in:ar,en'],
            'maxAppointmentsPerDay' => ['required', 'string', 'max:10'],
            'appointmentDuration' => ['required', 'string', 'max:10'],
        ]);

        $settings = AdminSetting::query()->first();
        if (! $settings) {
            $settings = new AdminSetting();
        }

        $settings->fill([
            'site_name' => $validated['siteName'],
            'site_email' => $validated['siteEmail'],
            'site_phone' => $validated['sitePhone'],
            'address' => $validated['address'],
            'email_notifications' => $validated['emailNotifications'],
            'sms_notifications' => $validated['smsNotifications'],
            'new_appointment_alert' => $validated['newAppointmentAlert'],
            'payment_alert' => $validated['paymentAlert'],
            'language' => $validated['language'],
            'max_appointments_per_day' => (int) $validated['maxAppointmentsPerDay'],
            'appointment_duration' => (int) $validated['appointmentDuration'],
        ]);
        $settings->save();

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => $settings->toApiArray(),
        ]);
    }

    public function aiKnowledgeList(): JsonResponse
    {
        $rows = AiKnowledgeEntry::query()->orderByDesc('priority')->orderBy('id')->get();

        return response()->json(['entries' => $rows]);
    }

    public function aiKnowledgeStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'triggers' => ['required', 'string', 'max:4000'],
            'response' => ['required', 'string', 'max:20000'],
            'role_context' => ['nullable', Rule::in(['patient', 'doctor', 'general'])],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $entry = AiKnowledgeEntry::create([
            'triggers' => $validated['triggers'],
            'response' => $validated['response'],
            'role_context' => $validated['role_context'] ?? null,
            'priority' => $validated['priority'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['entry' => $entry], 201);
    }

    public function aiKnowledgeUpdate(Request $request, AiKnowledgeEntry $entry): JsonResponse
    {
        $validated = $request->validate([
            'triggers' => ['sometimes', 'string', 'max:4000'],
            'response' => ['sometimes', 'string', 'max:20000'],
            'role_context' => ['nullable', Rule::in(['patient', 'doctor', 'general'])],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $entry->update($validated);

        return response()->json(['entry' => $entry->fresh()]);
    }

    public function aiKnowledgeDestroy(AiKnowledgeEntry $entry): JsonResponse
    {
        $entry->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function createStaff(Request $request, string $role, string $message): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'specialty' => ['nullable', 'string', 'max:191'],
            'governorate' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::create([
            ...$validated,
            'role' => $role,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => $message,
            'user' => $user,
        ], 201);
    }
}
