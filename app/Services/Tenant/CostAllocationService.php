<?php

namespace App\Services\Tenant;

use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\OrderProductLine;
use App\Models\Tenant\OrderServiceLine;
use App\Models\Tenant\Product;
use App\Models\Tenant\Service;
use App\Models\Tenant\InventoryLog;
use App\Models\Tenant\ProductSerial;
use App\Models\Tenant\ProductBatch;
use App\DTOs\Tenant\CreateOrderProductLineDTO;
use App\DTOs\Tenant\UpdateOrderProductLineDTO;
use App\DTOs\Tenant\CreateOrderServiceLineDTO;
use App\DTOs\Tenant\UpdateOrderServiceLineDTO;
use Illuminate\Support\Facades\DB;

class CostAllocationService
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    /**
     * Add a product line to the order
     */
    public function addProductLine(OrderOfService $order, CreateOrderProductLineDTO $dto, string $userId): OrderProductLine
    {
        $product = Product::findOrFail($dto->product_id);

        $unitPrice = $dto->unit_price ?? (float) $product->unit_price;
        $costPrice = $dto->cost_price ?? (float) $product->cost_price;
        $totalPrice = $dto->quantity * $unitPrice;
        $totalCost = $dto->quantity * $costPrice;

        return DB::connection('tenant')->transaction(function () use ($order, $product, $dto, $unitPrice, $costPrice, $totalPrice, $totalCost, $userId) {
            // Advanced Inventory Validation
            if ($product->is_serialized) {
                $serials = $dto->serials ?? [];
                if (count($serials) !== (int) $dto->quantity) {
                    throw new \InvalidArgumentException("O produto {$product->name} exige exatamente " . (int) $dto->quantity . " números de série.");
                }

                $availableCount = ProductSerial::where('product_id', $product->id)
                    ->whereIn('serial_number', $serials)
                    ->where('status', ProductSerial::STATUS_AVAILABLE)
                    ->count();

                if ($availableCount !== count($serials)) {
                    throw new \Exception("Um ou mais números de série informados estão indisponíveis.");
                }
            }

            $batch = null;
            if ($product->has_batches) {
                if (empty($dto->product_batch_id)) {
                    throw new \InvalidArgumentException("O produto {$product->name} exige a especificação de um lote.");
                }

                $batch = ProductBatch::where('product_id', $product->id)
                    ->where('id', $dto->product_batch_id)
                    ->firstOrFail();

                if ($batch->isExpired()) {
                    throw new \Exception("O lote {$batch->batch_number} do produto {$product->name} está vencido.");
                }

                if ($order->isInProgress()) {
                    if ($batch->current_quantity < $dto->quantity) {
                        throw new \Exception("O lote {$batch->batch_number} tem estoque insuficiente.");
                    }
                }
            }

            $line = OrderProductLine::create([
                'order_of_service_id' => $order->id,
                'product_id' => $product->id,
                'product_batch_id' => $product->has_batches ? $dto->product_batch_id : null,
                'quantity' => $dto->quantity,
                'unit_price' => $unitPrice,
                'cost_price' => $costPrice,
                'total_price' => $totalPrice,
                'total_cost' => $totalCost,
                'notes' => $dto->notes,
                'metadata' => $dto->metadata,
                'created_by' => $userId,
            ]);

            // Allocate Serials
            if ($product->is_serialized) {
                $serialStatus = ($order->isInProgress() || $order->isCompleted() || $order->isApproved())
                    ? ProductSerial::STATUS_SOLD
                    : ProductSerial::STATUS_RESERVED;

                ProductSerial::where('product_id', $product->id)
                    ->whereIn('serial_number', $dto->serials)
                    ->update([
                        'status' => $serialStatus,
                        'order_product_line_id' => $line->id,
                    ]);
            }

            // Allocate Batches
            if ($product->has_batches && $order->isInProgress() && $batch) {
                $batch->decrement('current_quantity', $dto->quantity);
            }

            // If order is in progress, perform stock deduction immediately
            if ($order->isInProgress()) {
                $this->inventoryService->recordMovement($product, InventoryLog::TYPE_OUTBOUND, (int) $dto->quantity, [
                    'reference_type' => 'order_product_line',
                    'reference_id' => $line->id,
                    'notes' => "Baixa de estoque por alocação na OS #{$order->reference_number}",
                ]);
            }

            $this->recalculateOrderTotals($order);

            return $line;
        });
    }

    /**
     * Update an existing product line
     */
    public function updateProductLine(OrderProductLine $line, UpdateOrderProductLineDTO $dto, string $userId): OrderProductLine
    {
        return DB::connection('tenant')->transaction(function () use ($line, $dto, $userId) {
            $order = $line->order;
            $product = $line->product;

            $oldQuantity = (float) $line->quantity;
            $quantity = $dto->quantity !== null ? $dto->quantity : $oldQuantity;
            $unitPrice = $dto->unit_price !== null ? $dto->unit_price : (float) $line->unit_price;
            $costPrice = $dto->cost_price !== null ? $dto->cost_price : (float) $line->cost_price;

            $totalPrice = $quantity * $unitPrice;
            $totalCost = $quantity * $costPrice;

            // Get currently allocated serials
            $oldSerials = [];
            if ($product->is_serialized) {
                $oldSerials = ProductSerial::where('order_product_line_id', $line->id)
                    ->pluck('serial_number')
                    ->toArray();
            }

            $newSerials = $dto->serials ?? $oldSerials;

            // Check serial quantity
            if ($product->is_serialized) {
                if (count($newSerials) !== (int) $quantity) {
                    throw new \InvalidArgumentException("O produto {$product->name} exige exatamente " . (int) $quantity . " números de série.");
                }

                // Check availability of any newly added serials
                $addedSerials = array_diff($newSerials, $oldSerials);
                if (!empty($addedSerials)) {
                    $availableCount = ProductSerial::where('product_id', $product->id)
                        ->whereIn('serial_number', $addedSerials)
                        ->where('status', ProductSerial::STATUS_AVAILABLE)
                        ->count();

                    if ($availableCount !== count($addedSerials)) {
                        throw new \Exception("Um ou mais números de série informados estão indisponíveis.");
                    }
                }
            }

            // Batch selection
            $newBatchId = $dto->product_batch_id !== null ? $dto->product_batch_id : $line->product_batch_id;
            if ($product->has_batches) {
                if (empty($newBatchId)) {
                    throw new \InvalidArgumentException("O produto {$product->name} exige a especificação de um lote.");
                }

                $newBatch = ProductBatch::where('product_id', $product->id)
                    ->where('id', $newBatchId)
                    ->firstOrFail();

                if ($newBatch->isExpired()) {
                    throw new \Exception("O lote {$newBatch->batch_number} do produto {$product->name} está vencido.");
                }

                // Check stock on new batch if order is in progress
                if ($order->isInProgress()) {
                    $availableStock = (float) $newBatch->current_quantity;
                    if ($newBatchId === $line->product_batch_id) {
                        $availableStock += $oldQuantity;
                    }
                    if ($availableStock < $quantity) {
                        throw new \Exception("O lote {$newBatch->batch_number} tem estoque insuficiente.");
                    }
                }
            }

            // Deallocate old serials
            if ($product->is_serialized) {
                ProductSerial::where('order_product_line_id', $line->id)
                    ->update([
                        'status' => ProductSerial::STATUS_AVAILABLE,
                        'order_product_line_id' => null,
                    ]);
            }

            // Restore old batch stock (if order is in progress)
            if ($product->has_batches && $line->product_batch_id && $order->isInProgress()) {
                $oldBatch = ProductBatch::find($line->product_batch_id);
                if ($oldBatch) {
                    $oldBatch->increment('current_quantity', $oldQuantity);
                }
            }

            // Update line details
            $line->update([
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'cost_price' => $costPrice,
                'total_price' => $totalPrice,
                'total_cost' => $totalCost,
                'product_batch_id' => $product->has_batches ? $newBatchId : null,
                'notes' => $dto->notes !== null ? $dto->notes : $line->notes,
                'metadata' => $dto->metadata !== null ? $dto->metadata : $line->metadata,
            ]);

            // Allocate new serials
            if ($product->is_serialized) {
                $serialStatus = ($order->isInProgress() || $order->isCompleted() || $order->isApproved())
                    ? ProductSerial::STATUS_SOLD
                    : ProductSerial::STATUS_RESERVED;

                ProductSerial::where('product_id', $product->id)
                    ->whereIn('serial_number', $newSerials)
                    ->update([
                        'status' => $serialStatus,
                        'order_product_line_id' => $line->id,
                    ]);
            }

            // Allocate new batch stock (if order is in progress)
            if ($product->has_batches && $order->isInProgress()) {
                $newBatch = ProductBatch::find($newBatchId);
                if ($newBatch) {
                    $newBatch->decrement('current_quantity', $quantity);
                }
            }

            // Adjust inventory if the order is in progress
            if ($order && $order->isInProgress()) {
                $diff = $quantity - $oldQuantity;
                if ($diff > 0) {
                    // Deduct more stock
                    $this->inventoryService->recordMovement($product, InventoryLog::TYPE_OUTBOUND, (int) $diff, [
                        'reference_type' => 'order_product_line',
                        'reference_id' => $line->id,
                        'notes' => "Baixa adicional por ajuste de quantidade na OS #{$order->reference_number}",
                    ]);
                } elseif ($diff < 0) {
                    // Refund some stock
                    $this->inventoryService->recordMovement($product, InventoryLog::TYPE_INBOUND, (int) abs($diff), [
                        'reference_type' => 'order_product_line',
                        'reference_id' => $line->id,
                        'notes' => "Estorno parcial por ajuste de quantidade na OS #{$order->reference_number}",
                    ]);
                }
            }

            $line->setMetadataValue('updated_by', $userId);
            $line->setMetadataValue('updated_at', now()->toIso8601String());
            $line->save();

            if ($order) {
                $this->recalculateOrderTotals($order);
            }

            return $line;
        });
    }

    /**
     * Remove a product line
     */
    public function removeProductLine(OrderProductLine $line, string $userId): bool
    {
        return DB::connection('tenant')->transaction(function () use ($line, $userId) {
            $order = $line->order;
            $product = $line->product;

            // If order is in progress, refund the inventory
            if ($order && $order->isInProgress() && $product) {
                $this->inventoryService->recordMovement($product, InventoryLog::TYPE_INBOUND, (int) $line->quantity, [
                    'reference_type' => 'order_product_line',
                    'reference_id' => $line->id,
                    'notes' => "Estorno de estoque por remoção de item na OS #{$order->reference_number}",
                ]);
            }

            // Deallocate serials
            if ($product && $product->is_serialized) {
                ProductSerial::where('order_product_line_id', $line->id)
                    ->update([
                        'status' => ProductSerial::STATUS_AVAILABLE,
                        'order_product_line_id' => null,
                    ]);
            }

            // Restore batch quantity
            if ($product && $product->has_batches && $line->product_batch_id && $order && $order->isInProgress()) {
                $batch = ProductBatch::find($line->product_batch_id);
                if ($batch) {
                    $batch->increment('current_quantity', (float) $line->quantity);
                }
            }

            $line->delete();

            if ($order) {
                $this->recalculateOrderTotals($order);
            }

            return true;
        });
    }

    /**
     * Add a service line to the order
     */
    public function addServiceLine(OrderOfService $order, CreateOrderServiceLineDTO $dto, string $userId): OrderServiceLine
    {
        $service = Service::findOrFail($dto->service_id);

        $unitPrice = $dto->unit_price ?? (float) $service->base_price;
        $costPrice = $dto->cost_price ?? 0.0; // labor/mão de obra cost defaults to 0
        $totalPrice = $dto->quantity * $unitPrice;
        $totalCost = $dto->quantity * $costPrice;

        return DB::connection('tenant')->transaction(function () use ($order, $service, $dto, $unitPrice, $costPrice, $totalPrice, $totalCost, $userId) {
            $line = OrderServiceLine::create([
                'order_of_service_id' => $order->id,
                'service_id' => $service->id,
                'assigned_to' => $dto->assigned_to,
                'quantity' => $dto->quantity,
                'unit_price' => $unitPrice,
                'cost_price' => $costPrice,
                'total_price' => $totalPrice,
                'total_cost' => $totalCost,
                'status' => OrderServiceLine::STATUS_PENDING,
                'notes' => $dto->notes,
                'metadata' => $dto->metadata,
                'created_by' => $userId,
            ]);

            $this->recalculateOrderTotals($order);

            return $line;
        });
    }

    /**
     * Update an existing service line
     */
    public function updateServiceLine(OrderServiceLine $line, UpdateOrderServiceLineDTO $dto, string $userId): OrderServiceLine
    {
        return DB::connection('tenant')->transaction(function () use ($line, $dto, $userId) {
            $order = $line->order;

            $quantity = $dto->quantity !== null ? $dto->quantity : (float) $line->quantity;
            $unitPrice = $dto->unit_price !== null ? $dto->unit_price : (float) $line->unit_price;
            $costPrice = $dto->cost_price !== null ? $dto->cost_price : (float) $line->cost_price;

            $totalPrice = $quantity * $unitPrice;
            $totalCost = $quantity * $costPrice;

            $data = [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'cost_price' => $costPrice,
                'total_price' => $totalPrice,
                'total_cost' => $totalCost,
                'assigned_to' => $dto->assigned_to !== null ? $dto->assigned_to : $line->assigned_to,
                'notes' => $dto->notes !== null ? $dto->notes : $line->notes,
                'metadata' => $dto->metadata !== null ? $dto->metadata : $line->metadata,
            ];

            if ($dto->status !== null) {
                $data['status'] = $dto->status;
                if ($dto->status === OrderServiceLine::STATUS_IN_PROGRESS && !$line->isInProgress()) {
                    $data['started_at'] = now();
                } elseif ($dto->status === OrderServiceLine::STATUS_COMPLETED && !$line->isCompleted()) {
                    $data['completed_at'] = now();
                    $data['hours_spent'] = $quantity;
                }
            }

            $line->update($data);

            $line->setMetadataValue('updated_by', $userId);
            $line->setMetadataValue('updated_at', now()->toIso8601String());
            $line->save();

            if ($order) {
                $this->recalculateOrderTotals($order);
            }

            return $line;
        });
    }

    /**
     * Remove a service line
     */
    public function removeServiceLine(OrderServiceLine $line, string $userId): bool
    {
        return DB::connection('tenant')->transaction(function () use ($line, $userId) {
            $order = $line->order;
            $line->delete();

            if ($order) {
                $this->recalculateOrderTotals($order);
            }

            return true;
        });
    }

    /**
     * Recalculate totals for the order of service
     */
    private function recalculateOrderTotals(OrderOfService $order): void
    {
        // Force relationships to reload to get accurate sums
        $order->load(['productLines', 'serviceLines']);

        $totalBilled = $order->total_billed_price;

        $order->update([
            'estimated_cost' => $totalBilled,
        ]);
    }
}
