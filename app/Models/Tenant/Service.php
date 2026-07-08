<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    protected $connection = 'tenant';
    protected $table = 'services';

    protected $fillable = [
        'category_id',
        'name',
        'code',
        'description',
        'base_price',
        'estimated_hours',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'estimated_hours' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Relationships
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function scopeSearchable($query, $term)
    {
        return $query->where('name', 'like', "%{$term}%")
            ->orWhere('code', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%");
    }

    /**
     * Methods
     */
    public function getPrice(?string $branchId = null): float
    {
        // Can be extended to support branch-specific pricing
        return (float) $this->base_price;
    }

    public function getPriceWithMarkup(float $markupPercentage): float
    {
        return $this->base_price * (1 + ($markupPercentage / 100));
    }

    public function getEstimatedCost(int $quantity = 1, ?float $hoursWorked = null): float
    {
        $hoursCost = $hoursWorked ? $hoursWorked * ($this->base_price / max($this->estimated_hours, 1)) : 0;
        return $this->base_price + $hoursCost;
    }

    /**
     * Metadata helpers
     */
    public function setMetadataValue($key, $value): self
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        return $this;
    }

    public function getMetadataValue($key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }
}
