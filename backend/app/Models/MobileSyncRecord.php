<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileSyncRecord extends Model
{
    protected $fillable = [
        'client_uuid',
        'inspector_id',
        'inspection_assignment_id',
        'entity_type',
        'entity_id',
        'operation',
        'status',
        'payload',
        'conflict_payload',
        'error_code',
        'error_message',
        'client_recorded_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'json',
            'conflict_payload' => 'json',
            'client_recorded_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function inspectionAssignment(): BelongsTo
    {
        return $this->belongsTo(InspectionAssignment::class);
    }
}
