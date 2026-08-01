<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inspection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'inspection_schedule_id',
        'inspection_request_id',
        'establishment_id',
        'inspector_id',
        'inspection_date',
        'status',
        'overall_assessment',
        'recommendations',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(InspectionSchedule::class, 'inspection_schedule_id');
    }

    public function inspectionRequest(): BelongsTo
    {
        return $this->belongsTo(InspectionRequest::class, 'inspection_request_id');
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class);
    }

    public function clearances(): HasMany
    {
        return $this->hasMany(Clearance::class);
    }
}
