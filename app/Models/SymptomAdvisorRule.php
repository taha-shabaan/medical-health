<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SymptomAdvisorRule extends Model
{
    protected $fillable = [
        'slug',
        'type',
        'match_mode',
        'label_ar',
        'message_ar',
        'keywords',
        'db_specialty_terms',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'db_specialty_terms' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
