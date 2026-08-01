<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'phone',
        'address',
        'age',
        'password',
        'is_active',
        'email_verified_at',
        'verification_code_hash',
        'verification_code_expires_at',
        'verification_channel',
        'verification_attempts',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_code_expires_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'verification_attempts' => 'integer',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(...$roles): bool
    {
        // Never lazy-load the role relation during authorization checks.
        if (! $this->relationLoaded('role')) {
            return false;
        }

        $roleRelation = $this->getRelation('role');

        if (! $roleRelation) {
            return false;
        }

        $roles = collect($roles)->flatten()->all();

        return in_array($roleRelation->slug, $roles, true);
    }

    public function inspectionSchedulesAsInspector(): HasMany
    {
        return $this->hasMany(InspectionSchedule::class, 'inspector_id');
    }

    public function inspectionsAsInspector(): HasMany
    {
        return $this->hasMany(Inspection::class, 'inspector_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function inspectionRequests(): HasMany
    {
        return $this->hasMany(InspectionRequest::class, 'resident_id');
    }

    public function establishments(): HasMany
    {
        return $this->hasMany(Establishment::class, 'resident_id');
    }

    public function inspectionAssignmentsAsInspector(): HasMany
    {
        return $this->hasMany(InspectionAssignment::class, 'inspector_id');
    }

    public function inspectionAssignmentsAsAssigner(): HasMany
    {
        return $this->hasMany(InspectionAssignment::class, 'assigned_by');
    }

    public function reviewedRequests(): HasMany
    {
        return $this->hasMany(InspectionRequest::class, 'reviewed_by');
    }

    public function mobileSyncRecords(): HasMany
    {
        return $this->hasMany(MobileSyncRecord::class, 'inspector_id');
    }
}
