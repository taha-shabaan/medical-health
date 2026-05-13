<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Message extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'citations',
        'retrieved_chunks',
        'confidence_score',
        'disclaimer_shown',
    ];

    protected function casts(): array
    {
        return [
            'citations' => 'array',
            'retrieved_chunks' => 'array',
            'confidence_score' => 'decimal:4',
            'disclaimer_shown' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function auditLog(): HasOne
    {
        return $this->hasOne(AuditLog::class);
    }
}
