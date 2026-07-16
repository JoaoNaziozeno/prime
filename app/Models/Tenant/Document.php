<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'documents';

    protected $fillable = [
        'branch_id',
        'documentable_type',
        'documentable_id',
        'title',
        'description',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'document_type',
        'expires_at',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'file_size' => 'integer',
        'expires_at' => 'date',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    // Expiration Helpers
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expires_at || $this->isExpired()) {
            return false;
        }

        return $this->expires_at->diffInDays(now()) <= $days;
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->toDateString());
    }

    public function scopeExpiringWithin($query, int $days = 30)
    {
        $today = now()->toDateString();
        $targetDate = now()->addDays($days)->toDateString();

        return $query->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$today, $targetDate]);
    }
}
