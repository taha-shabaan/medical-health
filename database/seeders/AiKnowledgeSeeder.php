<?php

namespace Database\Seeders;

use App\Models\AiKnowledgeEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class AiKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/ai_knowledge.json');
        if (! File::exists($path)) {
            return;
        }

        $data = json_decode((string) File::get($path), true);
        if (! is_array($data)) {
            return;
        }

        if (AiKnowledgeEntry::query()->exists()) {
            return;
        }

        foreach ($data as $row) {
            if (empty($row['triggers']) || empty($row['response'])) {
                continue;
            }
            AiKnowledgeEntry::create([
                'triggers' => (string) $row['triggers'],
                'response' => (string) $row['response'],
                'role_context' => $row['role_context'] ?? null,
                'priority' => (int) ($row['priority'] ?? 0),
                'is_active' => (bool) ($row['is_active'] ?? true),
            ]);
        }
    }
}
