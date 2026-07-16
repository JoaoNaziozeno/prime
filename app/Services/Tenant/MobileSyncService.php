<?php

namespace App\Services\Tenant;

use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\Service;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MobileSyncService
{
    protected array $modelMapping = [
        'orders' => OrderOfService::class,
        'vehicles' => Vehicle::class,
        'customers' => Customer::class,
        'products' => Product::class,
        'services' => Service::class,
    ];

    public function pullChanges(?string $lastSyncAt, array $models): array
    {
        $results = [];
        $parsedTime = null;

        if ($lastSyncAt) {
            try {
                $parsedTime = Carbon::parse($lastSyncAt);
            } catch (\Exception $e) {
                $parsedTime = null;
            }
        }

        foreach ($models as $modelKey) {
            if (!isset($this->modelMapping[$modelKey])) {
                continue;
            }

            $modelClass = $this->modelMapping[$modelKey];
            
            // Check if model uses SoftDeletes
            $query = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses($modelClass))
                ? $modelClass::withTrashed()
                : $modelClass::query();

            if ($parsedTime) {
                $query->where('updated_at', '>', $parsedTime->format('Y-m-d H:i:s'));
            }

            $results[$modelKey] = $query->get()->toArray();
        }

        return [
            'sync_timestamp' => now()->toIso8601String(),
            'changes' => $results,
        ];
    }

    public function pushChanges(array $changes, string $userId): array
    {
        return DB::connection('tenant')->transaction(function () use ($changes, $userId) {
            $success = [];
            $conflicts = [];

            foreach ($changes as $change) {
                $modelKey = $change['model'] ?? null;
                $id = $change['id'] ?? null;
                $action = $change['action'] ?? 'upsert';
                $clientData = $change['data'] ?? [];
                $clientUpdatedAtStr = $change['updated_at'] ?? null;

                if (!$modelKey || !$id || !isset($this->modelMapping[$modelKey])) {
                    continue;
                }

                $modelClass = $this->modelMapping[$modelKey];
                $record = $modelClass::find($id);

                if ($record) {
                    // Check for conflict using Last-Write-Wins
                    $serverUpdatedAt = $record->updated_at;
                    $clientUpdatedAt = $clientUpdatedAtStr ? Carbon::parse($clientUpdatedAtStr) : null;

                    if ($clientUpdatedAt && $serverUpdatedAt && $serverUpdatedAt->greaterThan($clientUpdatedAt)) {
                        // Conflict detected! Server has a newer version.
                        $conflicts[] = [
                            'model' => $modelKey,
                            'id' => $id,
                            'server_data' => $record->toArray(),
                        ];
                        continue;
                    }

                    // No conflict. Apply update.
                    // Clean attributes that shouldn't be directly updated
                    unset($clientData['id'], $clientData['created_at'], $clientData['updated_at']);
                    
                    $record->update($clientData);

                    $success[] = [
                        'model' => $modelKey,
                        'id' => $id,
                    ];
                } else {
                    // Record does not exist. Create it (e.g. offline order)
                    // Set primary key manually since we sync ID
                    $clientData['id'] = $id;
                    
                    $newRecord = $modelClass::create($clientData);

                    $success[] = [
                        'model' => $modelKey,
                        'id' => $id,
                    ];
                }
            }

            return [
                'success' => $success,
                'conflicts' => $conflicts,
            ];
        });
    }
}
