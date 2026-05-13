<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PineconeService
{
    private string $host;
    private string $apiKey;
    private string $index;

    public function __construct()
    {
        $this->apiKey = config('services.pinecone.key');
        $this->index  = config('services.pinecone.index');
        $this->host   = rtrim((string) config('services.pinecone.host'), '/');
    }

    /**
     * Semantic search — returns top-K matches with metadata.
     */
    public function search(
        array  $vector,
        int    $topK = 10,
        array  $filter = [],
        string $namespace = 'medical'
    ): array {
        $this->ensureConfigured();

        $body = [
            'vector'          => $vector,
            'topK'            => $topK,
            'includeMetadata' => true,
            'includeValues'   => false,
            'namespace'       => $namespace,
        ];

        if (!empty($filter)) {
            $body['filter'] = $filter;
        }

        $response = Http::withHeaders([
                'Api-Key'      => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(8)
            ->post("{$this->host}/query", $body);

        if ($response->failed()) {
            Log::error('Pinecone search failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Pinecone search error: ' . $response->body());
        }

        $matches = $response->json('matches') ?? [];

        // Normalize to same shape as previous Qdrant responses
        return array_map(fn($match) => [
            'id'         => $match['id'],
            'score'      => $match['score'],
            'payload'    => $match['metadata'] ?? [],
            'namespace'  => $namespace,
        ], $matches);
    }

    /**
     * Search across multiple namespaces (replaces multi-collection Qdrant logic).
     * Pinecone uses namespaces instead of collections within one index.
     */
    public function searchMultiNamespace(
        array $vector,
        array $namespaces = ['medical', 'drugs', 'guidelines'],
        int   $topK = 10
    ): array {
        $allResults = [];

        foreach ($namespaces as $ns) {
            $hits = $this->search($vector, $topK, [], $ns);
            $allResults = array_merge($allResults, $hits);
        }

        // Global re-sort by score, return top-K
        usort($allResults, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($allResults, 0, $topK);
    }

    /**
     * Upsert vectors (used by ingestion jobs).
     * Each vector: ['id' => string, 'values' => float[], 'metadata' => [...]]
     */
    public function upsert(array $vectors, string $namespace = 'medical'): void
    {
        $this->ensureConfigured();

        // Pinecone allows max 100 vectors per upsert request
        $chunks = array_chunk($vectors, 100);

        foreach ($chunks as $chunk) {
            $response = Http::withHeaders([
                    'Api-Key'      => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post("{$this->host}/vectors/upsert", [
                    'vectors'   => $chunk,
                    'namespace' => $namespace,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Pinecone upsert error: ' . $response->body());
            }
        }
    }

    /**
     * Delete vectors by IDs or filter.
     */
    public function delete(array $ids, string $namespace = 'medical'): void
    {
        $this->ensureConfigured();

        Http::withHeaders(['Api-Key' => $this->apiKey])
            ->delete("{$this->host}/vectors/delete", [
                'ids'       => $ids,
                'namespace' => $namespace,
            ]);
    }

    /**
     * Get index stats (vector count, dimensions, namespaces).
     */
    public function stats(): array
    {
        $this->ensureConfigured();

        $response = Http::withHeaders(['Api-Key' => $this->apiKey])
            ->get("{$this->host}/describe_index_stats");

        return $response->json();
    }

    private function ensureConfigured(): void
    {
        if (!$this->apiKey || !$this->host || !$this->index) {
            throw new \RuntimeException('Pinecone service is not configured.');
        }
    }
}
