<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentSource extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'title',
        'type',
        'version',
        'published_at',
        'vector_db',
        'index_name',
        'namespace',
        'file_hash',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
        ];
    }
}
