<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'checklist_id',
        'category',
        'title',
        'description',
        'sort_order',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function inspectionResults(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }
}
