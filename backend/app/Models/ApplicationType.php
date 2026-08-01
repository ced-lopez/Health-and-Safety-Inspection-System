<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicationType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
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
