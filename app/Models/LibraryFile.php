<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryFile extends Model
{
    public const DISK = 'local';

    protected $fillable = [
        'user_id',
        'name',
        'original_filename',
        'path',
        'mime',
        'size',
        'is_public',
        'token',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
