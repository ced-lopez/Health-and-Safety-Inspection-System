<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'inspection_id', 'establishment_id', 'inspection_result_id',
    'reported_by', 'assigned_to', 'title', 'description',
    'severity', 'status', 'correction_deadline', 'resolved_at', 'resolved_by',
])]
class Violation extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'correction_deadline' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function inspectionResult(): BelongsTo
    {
        return $this->belongsTo(InspectionResult::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ViolationEvidence::class);
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
