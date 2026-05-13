<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'user_ip',
        'query_hash',
        'query_vector',
        'retrieved_source_ids',
        'llm_model',
        'prompt_tokens',
        'completion_tokens',
        'source_count',
        'latency_ms',
        'model_response_status',
        'safety_triggered',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'query_vector' => 'array',
            'retrieved_source_ids' => 'array',
            'safety_triggered' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
