<?php

namespace Tests\Feature;

use App\Models\AiKnowledgeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiDatabaseKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_reply_comes_from_knowledge_table(): void
    {
        config(['services.ai.driver' => 'database']);

        AiKnowledgeEntry::create([
            'triggers' => 'موعد,حجز',
            'response' => 'رد_مخصص_من_القاعدة',
            'role_context' => 'patient',
            'priority' => 10,
            'is_active' => true,
        ]);

        $patient = User::factory()->patient()->create();
        Sanctum::actingAs($patient);

        $res = $this->postJson('/api/ai/messages', [
            'message' => 'أريد حجز موعد غداً',
            'role_context' => 'patient',
        ]);

        $res->assertOk()
            ->assertJsonPath('assistant_message.content', 'رد_مخصص_من_القاعدة');
    }

    public function test_fallback_when_no_trigger_matches(): void
    {
        config(['services.ai.driver' => 'database']);
        config(['services.ai.database_fallback' => 'لا_نتيجة']);

        $patient = User::factory()->patient()->create();
        Sanctum::actingAs($patient);

        $res = $this->postJson('/api/ai/messages', [
            'message' => 'نص عشوائي بدون كلمات مطابقة xyz123',
            'role_context' => 'patient',
        ]);

        $res->assertOk()
            ->assertJsonPath('assistant_message.content', 'لا_نتيجة');
    }
}
