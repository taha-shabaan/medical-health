<?php

namespace App\Services\Rag;

class PromptBuilderService
{
    /**
    * System prompt tuned for a practical medical chatbot.
    */
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a medical information assistant for a healthcare appointment app.
Your goal is to be useful, clear, and safe for patients and clinicians.

Follow these rules without exception:

1. USE AVAILABLE CONTEXT: If knowledge-base snippets are provided, use them
    to improve the answer. Add citations only for claims that come directly
    from those snippets, using [Source: <title>, p.<page>].

2. NO CONTEXT IS OK: If no snippets are provided, answer from general medical
    knowledge in an educational way. Do not claim that the answer came from the
    knowledge base.

3. NO CLINICAL DECISIONS: Do not provide a definitive diagnosis, personalized
    treatment plan, or specific medication dosage. Encourage the user to consult
    a licensed clinician for decisions about diagnosis, treatment, medication,
    urgent symptoms, or worsening conditions.

4. TRIAGE SAFETY: For potentially urgent symptoms, advise seeking urgent or
    emergency care. Keep guidance conservative and practical.

5. TONE: Respond in clear, professional language. Match the user's language
    when possible.

6. DISCLAIMER: End every response with this exact block — no exceptions:

---
⚠️ MEDICAL DISCLAIMER: This response is provided for informational and
educational purposes only. It does not constitute medical advice, diagnosis,
or treatment. Always consult a qualified and licensed healthcare professional
before making any clinical decision.
---
PROMPT;

    /**
     * Build the full message array for Gemini.
     *
     * Structure:
     *   [0] system  → safety rules + disclaimer requirement
     *   [1..N] user/assistant → last 6 conversation turns (3 exchanges)
     *   [N+1] user  → optional retrieved context + current question
     */
    public function build(string $query, array $retrievedChunks, array $history = [], ?string $retrievalStatus = null): array
    {
        $context = $this->formatChunks($retrievedChunks);
        $status = $retrievalStatus ?: (
            $retrievedChunks === []
                ? 'No knowledge-base snippets were retrieved.'
                : 'Knowledge-base snippets were retrieved.'
        );

        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
        ];

        // Append recent conversation history (last 6 turns = 3 back-and-forth exchanges)
        foreach (array_slice($history, -config('rag.history_turns', 6)) as $turn) {
            $messages[] = [
                'role'    => $turn['role'],
                'content' => $turn['content'],
            ];
        }

        // Final user message: inject optional retrieved evidence + question.
        $messages[] = [
            'role'    => 'user',
            'content' => "KNOWLEDGE BASE STATUS:\n{$status}\n\nCONTEXT SNIPPETS:\n\n{$context}\n\n---\n\nQUESTION: {$query}",
        ];

        return $messages;
    }

    private function formatChunks(array $chunks): string
    {
        if (empty($chunks)) {
            return "No external knowledge-base snippets are available for this question. Answer from general medical knowledge, stay educational, and do not invent citations.";
        }

        return collect($chunks)
            ->map(fn($chunk, $i) => sprintf(
                "[%d] Document: %s | Page: %s | Namespace: %s | Score: %.3f\n\n%s",
                $i + 1,
                $chunk['payload']['document_title'] ?? 'Unknown',
                $chunk['payload']['page_number']    ?? 'N/A',
                $chunk['namespace']                 ?? 'medical',
                $chunk['score'],
                $chunk['payload']['content']        ?? ''
            ))
            ->implode("\n\n---\n\n");
    }
}
