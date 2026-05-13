<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicalRecordAttachmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_upload_and_list_attachment(): void
    {
        Storage::fake('local');

        $patient = User::factory()->patient()->create();
        Sanctum::actingAs($patient);

        $file = UploadedFile::fake()->create('report.pdf', 120, 'application/pdf');

        $this->post('/api/medical-records/attachments', [
            'file' => $file,
            'category' => 'lab',
        ])->assertCreated()
            ->assertJsonPath('attachment.category', 'lab');

        $this->getJson('/api/medical-records')
            ->assertOk()
            ->assertJsonCount(1, 'attachments');
    }

    public function test_patient_cannot_delete_another_users_attachment(): void
    {
        Storage::fake('local');

        $a = User::factory()->patient()->create();
        $b = User::factory()->patient()->create();

        Sanctum::actingAs($a);
        $file = UploadedFile::fake()->create('x.pdf', 100, 'application/pdf');
        $id = $this->post('/api/medical-records/attachments', [
            'file' => $file,
            'category' => 'report',
        ])->assertCreated()->json('attachment.id');

        Sanctum::actingAs($b);
        $this->deleteJson("/api/medical-records/attachments/{$id}")->assertNotFound();
    }
}
