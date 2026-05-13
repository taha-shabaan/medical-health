<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicalRecordAttachmentController extends Controller
{
    private const CATEGORIES = ['report', 'lab', 'imaging', 'other'];

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:12288'],
            'category' => ['required', 'string', 'in:'.implode(',', self::CATEGORIES)],
        ]);

        $user = $request->user();
        $file = $request->file('file');

        $record = MedicalRecord::firstOrCreate(
            ['patient_id' => $user->id],
            []
        );

        $ext = $file->getClientOriginalExtension();
        $storedName = Str::uuid()->toString().($ext !== '' ? '.'.$ext : '');
        $directory = 'medical-attachments/'.$user->id;
        $path = $file->storeAs($directory, $storedName, 'local');

        $attachment = MedicalRecordAttachment::create([
            'patient_id' => $user->id,
            'medical_record_id' => $record->id,
            'category' => $validated['category'],
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
        ]);

        return response()->json([
            'message' => 'File uploaded successfully',
            'attachment' => $attachment->toApiArray(),
        ], 201);
    }

    public function destroy(Request $request, MedicalRecordAttachment $attachment): JsonResponse
    {
        $this->authorizeAttachment($request, $attachment);

        if (Storage::disk('local')->exists($attachment->path)) {
            Storage::disk('local')->delete($attachment->path);
        }
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted']);
    }

    public function download(Request $request, MedicalRecordAttachment $attachment): StreamedResponse|JsonResponse
    {
        $this->authorizeAttachment($request, $attachment);

        if (! Storage::disk('local')->exists($attachment->path)) {
            return response()->json(['message' => 'File not found on server.'], 404);
        }

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    private function authorizeAttachment(Request $request, MedicalRecordAttachment $attachment): void
    {
        if ((int) $attachment->patient_id !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
