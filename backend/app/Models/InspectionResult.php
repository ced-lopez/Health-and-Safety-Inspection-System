<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionResult extends Model
{
    protected $fillable = [
        'inspection_id',
        'checklist_item_id',
        'compliance_status',
        'remarks',
        'evidence_paths',
        'assessed_by',
    ];

    protected function casts(): array
    {
        return [
            'evidence_paths' => 'array',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }
}
