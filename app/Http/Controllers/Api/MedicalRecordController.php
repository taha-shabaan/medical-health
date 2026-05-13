<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $record = MedicalRecord::firstOrCreate(
            ['patient_id' => $userId],
            []
        );

        $attachments = MedicalRecordAttachment::query()
            ->where('patient_id', $userId)
            ->latest()
            ->get()
            ->map(fn (MedicalRecordAttachment $a) => $a->toApiArray())
            ->values();

        return response()->json([
            'medical_record' => $record,
            'attachments' => $attachments,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blood_pressure' => ['nullable', 'string', 'max:32'],
            'blood_sugar' => ['nullable', 'string', 'max:32'],
            'body_weight' => ['nullable', 'string', 'max:32'],
            'body_temperature' => ['nullable', 'string', 'max:32'],
            'allergies' => ['nullable', 'string'],
            'chronic_conditions' => ['nullable', 'string'],
            'medications' => ['nullable', 'string'],
            'surgeries' => ['nullable', 'string'],
            'family_history' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $record = MedicalRecord::updateOrCreate(
            ['patient_id' => $request->user()->id],
            $validated
        );

        return response()->json([
            'message' => 'Medical record updated successfully',
            'medical_record' => $record,
        ]);
    }
}
