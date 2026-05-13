<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Rag\EmbeddingService;
use App\Services\Rag\GeminiLlmService;
use App\Services\Rag\PineconeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_and_list_conversations(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Conversation::create([
            'user_id' => $otherUser->id,
            'session_token' => str_repeat('z', 64),
        ]);

        Sanctum::actingAs($user);

        $create = $this->postJson('/api/chat/conversations', [
            'metadata' => ['topic' => 'hypertension'],
        ])->assertCreated();

        $conversationId = $create->json('conversation.id');

        $list = $this->getJson('/api/chat/conversations')
            ->assertOk()
            ->json('conversations');

        $this->assertCount(1, $list);
        $this->assertSame($conversationId, $list[0]['id']);
    }

    public function test_user_cannot_view_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $owner->id,
            'session_token' => str_repeat('a', 64),
        ]);

        Sanctum::actingAs($viewer);

        $this->getJson("/api/chat/conversations/{$conversation->id}")
            ->assertForbidden();
    }

    public function test_send_validates_message_constraints(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'session_token' => str_repeat('b', 64),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/chat/send', [
            'conversation_id' => $conversation->id,
            'message' => '   ',
        ])->assertStatus(422);

        $this->postJson('/api/chat/send', [
            'conversation_id' => $conversation->id,
            'message' => str_repeat('x', config('rag.message_max_length') + 1),
        ])->assertStatus(422);
    }

    public function test_send_stores_user_and_assistant_messages_with_citations_and_audit_log(): void
    {
        config(['rag.enabled' => true]);

        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'session_token' => str_repeat('c', 64),
        ]);

        Sanctum::actingAs($user);

        $this->app->instance(EmbeddingService::class, new class extends EmbeddingService {
            public function __construct() {}

            public function embed(string $text): array
            {
                return array_fill(0, 768, 0.01);
            }
        });

        $this->app->instance(PineconeService::class, new class extends PineconeService {
            public function __construct() {}

            public function searchMultiNamespace(array $vector, array $namespaces = ['medical', 'drugs', 'guidelines'], int $topK = 10): array
            {
                return [[
                    'id' => 'source-1',
                    'score' => 0.92,
                    'namespace' => 'medical',
                    'payload' => [
                        'document_title' => 'Hypertension Guideline',
                        'page_number' => 12,
                        'content' => 'Common symptoms may include headaches and dizziness.',
                        'source_id' => 'source-1',
                    ],
                ]];
            }
        });

        $this->app->instance(GeminiLlmService::class, new class extends GeminiLlmService {
            public function __construct() {}

            public function streamChat(array $messages, callable $onChunk): void
            {
                $onChunk('Hypertension may present with headaches.');
                $onChunk("\n\n---\n⚠️ MEDICAL DISCLAIMER: This response is for informational purposes only. Always consult a qualified healthcare professional before making any clinical decision.\n---");
            }
        });

        $response = $this
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->post('/api/chat/send', [
                'conversation_id' => $conversation->id,
                'message' => 'What are common hypertension symptoms?',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('"done":true', $response->streamedContent());

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]->role);
        $this->assertSame('assistant', $messages[1]->role);
        $this->assertNotEmpty($messages[1]->citations);
        $this->assertNotEmpty($messages[1]->retrieved_chunks);
        $this->assertSame('success', $messages[1]->auditLog?->model_response_status);
    }

    public function test_send_answers_when_no_knowledge_base_matches_are_found(): void
    {
        config(['rag.enabled' => true]);

        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'session_token' => str_repeat('d', 64),
        ]);

        Sanctum::actingAs($user);

        $this->app->instance(EmbeddingService::class, new class extends EmbeddingService {
            public function __construct() {}

            public function embed(string $text): array
            {
                return array_fill(0, 768, 0.01);
            }
        });

        $this->app->instance(PineconeService::class, new class extends PineconeService {
            public function __construct() {}

            public function searchMultiNamespace(array $vector, array $namespaces = ['medical', 'drugs', 'guidelines'], int $topK = 10): array
            {
                return [];
            }
        });

        $this->app->instance(GeminiLlmService::class, new class extends GeminiLlmService {
            public function __construct() {}

            public function streamChat(array $messages, callable $onChunk): void
            {
                \PHPUnit\Framework\Assert::assertStringContainsString(
                    'No external knowledge-base snippets are available',
                    end($messages)['content']
                );
                \PHPUnit\Framework\Assert::assertStringNotContainsString('EVIDENCE ONLY', $messages[0]['content']);

                $onChunk('This is general educational guidance.');
            }
        });

        $response = $this
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->post('/api/chat/send', [
                'conversation_id' => $conversation->id,
                'message' => 'What can cause a headache?',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('This is general educational guidance.', $response->streamedContent());

        $assistantMessage = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->first();

        $this->assertNotNull($assistantMessage);
        $this->assertSame([], $assistantMessage->citations);
        $this->assertSame('success', $assistantMessage->auditLog?->model_response_status);
    }

    public function test_send_uses_prompt_only_chat_when_rag_is_disabled(): void
    {
        config(['rag.enabled' => false]);

        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'session_token' => str_repeat('f', 64),
        ]);

        Sanctum::actingAs($user);

        $this->app->instance(EmbeddingService::class, new class extends EmbeddingService {
            public function __construct() {}

            public function embed(string $text): array
            {
                throw new \RuntimeException('Embedding should not be called when RAG is disabled.');
            }
        });

        $this->app->instance(PineconeService::class, new class extends PineconeService {
            public function __construct() {}

            public function searchMultiNamespace(array $vector, array $namespaces = ['medical', 'drugs', 'guidelines'], int $topK = 10): array
            {
                throw new \RuntimeException('Pinecone should not be called when RAG is disabled.');
            }
        });

        $this->app->instance(GeminiLlmService::class, new class extends GeminiLlmService {
            public function __construct() {}

            public function streamChat(array $messages, callable $onChunk): void
            {
                \PHPUnit\Framework\Assert::assertStringContainsString(
                    'Knowledge-base retrieval is disabled',
                    end($messages)['content']
                );

                $onChunk('Prompt-only answer works.');
            }
        });

        $response = $this
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->post('/api/chat/send', [
                'conversation_id' => $conversation->id,
                'message' => 'Can you explain fever basics?',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('Prompt-only answer works.', $response->streamedContent());

        $assistantMessage = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->first();

        $this->assertNotNull($assistantMessage);
        $this->assertSame([], $assistantMessage->retrieved_chunks);
        $this->assertSame(0, $assistantMessage->auditLog?->source_count);
        $this->assertNull($assistantMessage->auditLog?->query_vector);
    }

    public function test_send_continues_when_rag_retrieval_fails(): void
    {
        config(['rag.enabled' => true]);

        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'session_token' => str_repeat('e', 64),
        ]);

        Sanctum::actingAs($user);

        $this->app->instance(EmbeddingService::class, new class extends EmbeddingService {
            public function __construct() {}

            public function embed(string $text): array
            {
                throw new \RuntimeException('Gemini is down');
            }
        });

        $this->app->instance(GeminiLlmService::class, new class extends GeminiLlmService {
            public function __construct() {}

            public function streamChat(array $messages, callable $onChunk): void
            {
                \PHPUnit\Framework\Assert::assertStringContainsString(
                    'Knowledge-base retrieval is unavailable',
                    end($messages)['content']
                );

                $onChunk('I can still answer safely without retrieved documents.');
            }
        });

        $response = $this
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->post('/api/chat/send', [
                'conversation_id' => $conversation->id,
                'message' => 'Test retrieval failure path',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('I can still answer safely', $response->streamedContent());

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]->role);
        $this->assertSame('assistant', $messages[1]->role);
        $this->assertSame('success', $messages[1]->auditLog?->model_response_status);
        $this->assertSame(0, $messages[1]->auditLog?->source_count);
    }
}
