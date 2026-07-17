<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'establishment_id', 'inspector_id', 'scheduled_by',
    'scheduled_date', 'scheduled_time', 'status', 'notes',
])]
class InspectionSchedule extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
        ];
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }
}
