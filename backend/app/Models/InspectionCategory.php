<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'requires_business_details',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_business_details' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function inspectionRequests(): HasMany
    {
        return $this->hasMany(InspectionRequest::class);
    }

    public function documentRequirementRules(): HasMany
    {
        return $this->hasMany(DocumentRequirementRule::class);
    }
}
