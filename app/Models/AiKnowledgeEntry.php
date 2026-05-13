<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AiKnowledgeEntry extends Model
{
    protected $fillable = [
        'triggers',
        'response',
        'role_context',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Include rows for $context (patient|doctor|general) or global (null). */
    public function scopeForRoleContext(Builder $query, string $context): Builder
    {
        return $query->where(function (Builder $q) use ($context) {
            $q->whereNull('role_context')
                ->orWhere('role_context', $context);
        });
    }
}
