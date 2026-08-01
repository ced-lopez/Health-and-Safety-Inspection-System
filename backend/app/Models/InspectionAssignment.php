<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionAssignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'inspection_request_id',
        'inspector_id',
        'assigned_by',
        'status',
        'assigned_at',
        'downloaded_at',
        'submitted_at',
        'server_version',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'submitted_at' => 'datetime',
            'server_version' => 'integer',
        ];
    }

    public function inspectionRequest(): BelongsTo
    {
        return $this->belongsTo(InspectionRequest::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(InspectionSchedule::class, 'inspection_assignment_id')->latestOfMany();
    }

    public function mobileSyncRecords(): HasMany
    {
        return $this->hasMany(MobileSyncRecord::class);
    }
}
