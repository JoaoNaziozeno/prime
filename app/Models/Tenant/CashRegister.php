<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\CashRegisterType;
use App\Enums\Tenant\CashSessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashRegister extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'cash_registers';

    protected $fillable = [
        'branch_id',
        'name',
        'type',
        'is_central',
        'is_active',
        'max_cash_limit',
        'metadata',
    ];

    protected $casts = [
        'type' => CashRegisterType::class,
        'is_central' => 'boolean',
        'is_active' => 'boolean',
        'max_cash_limit' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(CashSession::class)
            ->whereIn('status', [CashSessionStatus::OPEN->value, CashSessionStatus::CLOSING->value])
            ->latest('opened_at');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCentral($query)
    {
        return $query->where('is_central', true);
    }

    public function scopeUserStations($query)
    {
        return $query->where('is_central', false);
    }
}
