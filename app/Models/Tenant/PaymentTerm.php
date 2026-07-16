<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTerm extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'payment_terms';

    protected $fillable = [
        'name',
        'days_until_due',
        'is_active',
    ];

    protected $casts = [
        'days_until_due' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'payment_term_id');
    }
}
