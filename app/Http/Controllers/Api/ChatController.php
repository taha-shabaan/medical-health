<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatRequest;
use App\Models\Conversation;
use App\Models\DocumentSource;
use App\Models\Message;
use App\Services\Rag\EmbeddingService;
use App\Services\Rag\GeminiLlmService;
use App\Services\Rag\PineconeService;
use App\Services\Rag\PromptBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatController extends Controller
{
    public function __construct(
        private EmbeddingService $embedder,
        private PineconeService $pinecone,
        private PromptBuilderService $promptBuilder,
        private GeminiLlmService $gemini,
    ) {}

    public function storeConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'metadata' => ['nullable', 'array'],
        ]);

        $conversation = $request->user()->conversations()->create([
            'session_token' => Str::random(64),
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'message' => 'Conversation created successfully.',
            'conversation' => $conversation,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()->conversations()
            ->with(['messages' => fn ($query) => $query->latest()->limit(1)])
            ->withCount('messages')
            ->latest()
            ->get();

        return response()->json([
            'conversations' => $conversations,
        ]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load(['messages.auditLog']);

        return response()->json([
            'conversation' => $conversation,
        ]);
    }

    public function send(ChatRequest $request): StreamedResponse|JsonResponse
    {
        $conversation = Conversation::findOrFail($request->validated('conversation_id'));
        $this->authorize('interact', $conversation);

        $query = $request->validated('message');
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->toArray();

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $query,
        ]);

        $startedAt = microtime(true);

        $vector = null;
        $rawHits = [];
        $retrievalStatus = null;

        if (config('rag.enabled', false)) {
            try {
                $vector = $this->embedder->embed($query);
                $rawHits = $this->pinecone->searchMultiNamespace(
                    vector: $vector,
                    namespaces: config('rag.namespaces', ['medical', 'drugs', 'guidelines']),
                    topK: config('rag.top_k', 10),
                );
            } catch (Throwable $e) {
                $retrievalStatus = 'Knowledge-base retrieval is unavailable. Answer from general medical knowledge without citations.';
                report($e);
            }
        } else {
            $retrievalStatus = 'Knowledge-base retrieval is disabled. Answer from general medical knowledge without citations.';
        }

        $filtered = array_filter(
            $rawHits,
            fn (array $hit): bool => ($hit['score'] ?? 0) >= config('rag.min_score', 0.65)
        );
        $topHits = array_slice(
            array_values($filtered),
            0,
            config('rag.final_top_k', 5)
        );

        $messages = $this->promptBuilder->build($query, $topHits, $history, $retrievalStatus);
        $citations = $this->buildCitations($topHits);

        return new StreamedResponse(
            function () use (
                $messages,
                $conversation,
                $citations,
                $topHits,
                $request,
                $query,
                $startedAt,
                $vector
            ): void {
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('X-Accel-Buffering: no');

                $fullResponse = '';

                try {
                    $this->gemini->streamChat(
                        $messages,
                        function (string $chunk) use (&$fullResponse): void {
                            $fullResponse .= $chunk;
                            echo 'data: ' . json_encode(['delta' => $chunk]) . "\n\n";
                        }
                    );

                    if (!str_contains($fullResponse, 'MEDICAL DISCLAIMER')) {
                        $disclaimer = "\n\n---\n⚠️ MEDICAL DISCLAIMER: This response is for informational purposes only. Always consult a qualified healthcare professional before making any clinical decision.\n---";
                        $fullResponse .= $disclaimer;
                        echo 'data: ' . json_encode(['delta' => $disclaimer]) . "\n\n";
                    }

                    /** @var Message $assistantMessage */
                    $assistantMessage = $conversation->messages()->create([
                        'role' => 'assistant',
                        'content' => $fullResponse,
                        'citations' => $citations,
                        'retrieved_chunks' => array_map(
                            fn (array $hit): array => $hit['payload'] ?? [],
                            $topHits
                        ),
                        'confidence_score' => $this->calculateConfidence($topHits),
                        'disclaimer_shown' => true,
                    ]);

                    $assistantMessage->auditLog()->create([
                        'user_ip' => (string) $request->ip(),
                        'query_hash' => hash('sha256', $query),
                        'query_vector' => $vector,
                        'retrieved_source_ids' => $this->extractSourceIds($topHits),
                        'llm_model' => config('services.gemini.llm_model'),
                        'source_count' => count($topHits),
                        'latency_ms' => $this->elapsedMs($startedAt),
                        'model_response_status' => 'success',
                        'safety_triggered' => false,
                        'created_at' => now(),
                    ]);

                    echo 'data: ' . json_encode([
                        'done' => true,
                        'citations' => $citations,
                        'model' => config('services.gemini.llm_model'),
                    ]) . "\n\n";
                } catch (Throwable $e) {
                    report($e);

                    $errorText = 'The AI assistant could not complete this response right now.';

                    /** @var Message $assistantMessage */
                    $assistantMessage = $conversation->messages()->create([
                        'role' => 'assistant',
                        'content' => $errorText,
                        'citations' => [],
                        'retrieved_chunks' => array_map(
                            fn (array $hit): array => $hit['payload'] ?? [],
                            $topHits
                        ),
                        'disclaimer_shown' => false,
                    ]);

                    $assistantMessage->auditLog()->create([
                        'user_ip' => (string) $request->ip(),
                        'query_hash' => hash('sha256', $query),
                        'query_vector' => $vector,
                        'retrieved_source_ids' => $this->extractSourceIds($topHits),
                        'llm_model' => config('services.gemini.llm_model'),
                        'source_count' => count($topHits),
                        'latency_ms' => $this->elapsedMs($startedAt),
                        'model_response_status' => 'failed',
                        'safety_triggered' => false,
                        'created_at' => now(),
                    ]);

                    echo 'data: ' . json_encode([
                        'error' => $errorText,
                        'done' => true,
                    ]) . "\n\n";
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }

    private function buildCitations(array $topHits): array
    {
        $sourceIds = collect($topHits)
            ->map(fn (array $hit) => $hit['payload']['document_source_id'] ?? $hit['payload']['source_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        $sources = DocumentSource::query()
            ->whereIn('id', $sourceIds)
            ->get()
            ->keyBy('id');

        return array_map(function (array $hit) use ($sources): array {
            $payload = $hit['payload'] ?? [];
            $sourceId = $payload['document_source_id'] ?? $payload['source_id'] ?? null;
            $documentSource = $sourceId ? $sources->get($sourceId) : null;

            return [
                'source' => $payload['document_title'] ?? $documentSource?->title ?? 'Unknown',
                'page' => $payload['page_number'] ?? 'N/A',
                'score' => round((float) ($hit['score'] ?? 0), 4),
                'namespace' => $hit['namespace'] ?? null,
                'document_source_id' => $sourceId,
            ];
        }, $topHits);
    }

    private function extractSourceIds(array $topHits): array
    {
        return array_values(array_filter(array_map(
            fn (array $hit) => $hit['payload']['document_source_id'] ?? $hit['payload']['source_id'] ?? $hit['id'] ?? null,
            $topHits
        )));
    }

    private function calculateConfidence(array $topHits): ?float
    {
        if ($topHits === []) {
            return null;
        }

        $scores = array_map(
            fn (array $hit): float => (float) ($hit['score'] ?? 0),
            $topHits
        );

        return round(array_sum($scores) / count($scores), 4);
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

}
