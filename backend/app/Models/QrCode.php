<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'qrable_type', 'qrable_id', 'code', 'is_active',
    'verification_count', 'last_verified_at',
])]
class QrCode extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_verified_at' => 'datetime',
        ];
    }

    public function qrable(): MorphTo
    {
        return $this->morphTo();
    }
}
