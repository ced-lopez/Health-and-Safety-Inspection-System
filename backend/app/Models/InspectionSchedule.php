<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'establishment_id',
        'inspector_id',
        'scheduled_by',
        'scheduled_date',
        'scheduled_time',
        'scheduled_at',
        'schedule_type',
        'server_version',
        'inspection_request_id',
        'inspection_assignment_id',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'scheduled_at' => 'datetime',
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

    public function request(): BelongsTo
    {
        return $this->belongsTo(InspectionRequest::class, 'inspection_request_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(InspectionAssignment::class, 'inspection_assignment_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }
}
