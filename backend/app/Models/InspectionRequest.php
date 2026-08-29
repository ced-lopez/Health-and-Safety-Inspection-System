<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'request_number',
        'resident_id',
        'inspection_category_id',
        'application_type_id',
        'sub_path',
        'declared_animal_count',
        'establishment_id',
        'applicant_name',
        'applicant_age',
        'applicant_address',
        'contact_number',
        'email',
        'business_name',
        'remarks',
        'status',
        'preferred_schedule_at',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'applicant_age' => 'integer',
            'declared_animal_count' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'preferred_schedule_at' => 'datetime',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    public function inspectionCategory(): BelongsTo
    {
        return $this->belongsTo(InspectionCategory::class);
    }

    public function applicationType(): BelongsTo
    {
        return $this->belongsTo(ApplicationType::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function inspectionAssignment(): HasOne
    {
        return $this->hasOne(InspectionAssignment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(InspectionSchedule::class, 'inspection_request_id');
    }
}
