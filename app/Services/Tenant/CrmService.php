<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Lead;
use App\Models\Tenant\LeadActivity;
use App\Models\Tenant\Customer;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class CrmService
{
    /**
     * Create a new Lead.
     */
    public function createLead(array $data, string $userId): Lead
    {
        return Lead::create([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'source' => $data['source'] ?? null,
            'status' => $data['status'] ?? Lead::STATUS_NEW,
            'estimated_value' => $data['estimated_value'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ]);
    }

    /**
     * Update an existing Lead.
     */
    public function updateLead(Lead $lead, array $data): Lead
    {
        $lead->update(array_filter([
            'branch_id' => $data['branch_id'] ?? null,
            'name' => $data['name'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'source' => $data['source'] ?? null,
            'status' => $data['status'] ?? null,
            'estimated_value' => $data['estimated_value'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn($v) => !is_null($v)));

        return $lead->fresh();
    }

    /**
     * Create an activity for a Lead.
     */
    public function createActivity(Lead $lead, array $data, string $userId): LeadActivity
    {
        return LeadActivity::create([
            'lead_id' => $lead->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'completed_at' => $data['completed_at'] ?? null,
            'created_by' => $userId,
        ]);
    }

    /**
     * Mark an activity as completed.
     */
    public function completeActivity(LeadActivity $activity): LeadActivity
    {
        $activity->update([
            'completed_at' => now(),
        ]);

        return $activity->fresh();
    }

    /**
     * Convert a Lead into a Customer.
     */
    public function convert(Lead $lead, string $userId): Customer
    {
        if ($lead->status === Lead::STATUS_CONVERTED) {
            throw ValidationException::withMessages([
                'lead' => 'Este lead já foi convertido em cliente.',
            ]);
        }

        return DB::transaction(function () use ($lead, $userId) {
            $customer = Customer::create([
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'type' => $lead->company_name ? Customer::TYPE_COMPANY : Customer::TYPE_INDIVIDUAL,
                'status' => Customer::STATUS_ACTIVE,
                'metadata' => $lead->company_name ? ['company_name' => $lead->company_name] : null,
                'notes' => "Convertido a partir do Lead #{$lead->id}. Observações: {$lead->notes}",
                'created_by' => $userId,
            ]);

            $lead->update([
                'status' => Lead::STATUS_CONVERTED,
                'converted_customer_id' => $customer->id,
            ]);

            $this->createActivity($lead, [
                'type' => LeadActivity::TYPE_NOTE,
                'title' => 'Lead convertido em Cliente',
                'description' => "O lead foi convertido com sucesso em Cliente com ID #{$customer->id}.",
                'completed_at' => now(),
            ], $userId);

            return $customer;
        });
    }
}
