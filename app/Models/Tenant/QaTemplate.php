<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QaTemplate extends Model
{
    use HasFactory;

    protected $connection = 'tenant';
    protected $table = 'qa_templates';

    protected $fillable = [
        'name',
        'description',
        'items',
        'is_active',
    ];

    protected $casts = [
        'items' => 'array',
        'is_active' => 'boolean',
    ];

    public function inspections(): HasMany
    {
        return $this->hasMany(QaInspection::class, 'qa_template_id');
    }
}
