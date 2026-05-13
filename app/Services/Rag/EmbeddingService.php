<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * EmbeddingService
 *
 * Uses Google Gemini embeddings via the Generative Language API.
 *
 * Key facts:
 *  - Output : configured dimensionality, 768 by default
 *  - Metric : cosine similarity (Pinecone index must use cosine + matching dimensions)
 *  - Free   : yes — free tier allows up to 1,500 requests/min
 *  - taskType: RETRIEVAL_QUERY  → query-time embeds (asymmetric, optimised for search)
 *              RETRIEVAL_DOCUMENT → ingestion-time embeds (optimised for storage)
 */
class EmbeddingService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $dimensions;

    public function __construct()
    {
        $this->apiKey     = config('services.gemini.key');
        $this->model      = config('services.gemini.embed_model');
        $this->baseUrl    = config('services.gemini.base_url');
        $this->dimensions = (int) config('services.gemini.embed_dimensions', 768);
    }

    // -------------------------------------------------------------------------
    // Single embed — called at query time in ChatController
    // -------------------------------------------------------------------------

    /**
        * Embed a single query string.
     *
     * Uses RETRIEVAL_QUERY task type — Gemini optimises the vector
     * specifically for semantic search (asymmetric retrieval).
     * Result is Redis-cached by SHA-256 for 1 hour.
     */
    public function embed(string $text): array
    {
        $this->ensureConfigured();

        $text     = $this->normalize($text);
        $cacheKey = 'gemini_embed:' . hash('sha256', $text);

        return Cache::remember($cacheKey, 3600, function () use ($text) {
            $response = Http::timeout(15)
                ->post(
                    "{$this->baseUrl}/{$this->model}:embedContent?key={$this->apiKey}",
                    [
                        'model'    => $this->model,
                        'content'  => [
                            'parts' => [['text' => $text]],
                        ],
                        'taskType' => 'RETRIEVAL_QUERY',
                        'outputDimensionality' => $this->dimensions,
                    ]
                );

            if ($response->failed()) {
                Log::error('Gemini embed failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \RuntimeException('Gemini embedding error: ' . $response->body());
            }

            $values = $response->json('embedding.values');

            if (!is_array($values) || count($values) !== $this->dimensions) {
                throw new \RuntimeException(
                    'Unexpected embedding dimension: ' . count((array) $values)
                );
            }

            return $values;
        });
    }

    // -------------------------------------------------------------------------
    // Batch embed — called during PDF ingestion in IngestPdfJob
    // -------------------------------------------------------------------------

    /**
        * Embed multiple document chunks.
     *
     * Uses RETRIEVAL_DOCUMENT task type — optimised for storing chunks
     * that will later be retrieved against RETRIEVAL_QUERY vectors.
     * Batches up to 100 texts per API call (Gemini batchEmbedContents limit).
     */
    public function embedBatch(array $texts): array
    {
        $this->ensureConfigured();

        $chunks  = array_chunk($texts, 100);
        $results = [];

        foreach ($chunks as $chunk) {
            $requests = array_map(
                fn($text) => [
                    'model'    => $this->model,
                    'content'  => [
                        'parts' => [['text' => $this->normalize($text)]],
                    ],
                    'taskType' => 'RETRIEVAL_DOCUMENT',
                    'outputDimensionality' => $this->dimensions,
                ],
                $chunk
            );

            $response = Http::timeout(60)
                ->post(
                    "{$this->baseUrl}/{$this->model}:batchEmbedContents?key={$this->apiKey}",
                    ['requests' => $requests]
                );

            if ($response->failed()) {
                Log::error('Gemini batch embed failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \RuntimeException('Gemini batch embed error: ' . $response->body());
            }

            foreach ($response->json('embeddings') as $embedding) {
                $results[] = $embedding['values']; // float[768]
            }
        }

        return $results; // float[N][768]
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Strip PII and truncate before embedding.
     * text-embedding-004 max context: 2,048 tokens (~8,000 chars).
     */
    private function normalize(string $text): string
    {
        $text = preg_replace('/\b\d{3}-\d{2}-\d{4}\b/',                        '[SSN]',   $text);
        $text = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '[EMAIL]', $text);
        $text = preg_replace('/\b(\+?\d[\d\s\-]{8,13}\d)\b/',                  '[PHONE]', $text);

        return mb_substr(trim($text), 0, 8000);
    }

    private function ensureConfigured(): void
    {
        if (!$this->apiKey || !$this->model || !$this->baseUrl || $this->dimensions < 1) {
            throw new \RuntimeException('Gemini embedding service is not configured.');
        }
    }
}
