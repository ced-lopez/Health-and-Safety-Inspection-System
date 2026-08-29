<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Checklist extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'category',
        'version',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('sort_order');
    }

    /**
     * Map an inspection request category slug to the establishment category
     * that owns the applicable compliance checklist.
     */
    public static function categoryForInspectionCategory(?string $slug): ?string
    {
        return match ($slug) {
            'business_establishments' => 'food_establishment',
            'piggery' => 'piggery',
            'poultry' => 'poultry',
            'animal_raising_dogs' => 'dog_raising_kennel',
            default => null,
        };
    }

    /**
     * Active checklists applicable for an establishment category. Core
     * cross-cutting checklists remain applicable to every category.
     */
    public static function forEstablishmentCategory(?string $category)
    {
        $query = static::query()->where('is_active', true);

        if ($category) {
            $query->where(function ($q) use ($category) {
                $q->where('category', $category)
                    ->orWhereIn('category', ['health_sanitation', 'fire_safety', 'workplace_safety']);
            });
        }

        return $query->with('items')->orderBy('category');
    }
}
