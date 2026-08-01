<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequirementRule extends Model
{
    protected $fillable = [
        'inspection_category_id',
        'application_type_id',
        'sub_path',
        'document_type',
        'document_name',
        'is_required',
        'requires_expiration_check',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'requires_expiration_check' => 'boolean',
        ];
    }

    public function inspectionCategory(): BelongsTo
    {
        return $this->belongsTo(InspectionCategory::class);
    }

    public function applicationType(): BelongsTo
    {
        return $this->belongsTo(ApplicationType::class);
    }
}
