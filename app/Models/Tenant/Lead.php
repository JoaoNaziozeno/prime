<?php

namespace App\Models\Tenant;

use App\Models\Master\User;
use App\Traits\Tenant\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $connection = 'tenant';

    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_PROPOSAL_SENT = 'proposal_sent';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_LOST = 'lost';

    protected $fillable = [
        'branch_id',
        'name',
        'company_name',
        'email',
        'phone',
        'source',
        'status',
        'estimated_value',
        'assigned_to',
        'converted_customer_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'estimated_value' => 'float',
        'assigned_to' => 'integer',
        'converted_customer_id' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * Get the branch associated with the lead.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user assigned to this lead.
     */
    public function assignedTo(): BelongsTo
    {
        $instance = new User();
        $relation = $this->belongsTo(User::class, 'assigned_to');
        $relation->getQuery()->getQuery()->connection = $instance->getConnectionName();
        return $relation;
    }

    /**
     * Get the customer converted from this lead.
     */
    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    /**
     * Get the activities for this lead.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderBy('created_at', 'desc');
    }
}
