<?php

namespace App\Services\Tenant;

use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductSerial;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\InventoryLog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseOrderService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function create(array $data, string $userId): PurchaseOrder
    {
        return DB::connection('tenant')->transaction(function () use ($data, $userId) {
            $po = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $data['branch_id'],
                'status' => PurchaseOrder::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $totalAmount = 0;

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty = (float) $item['quantity'];
                $cost = (float) $item['unit_cost'];
                $totalCost = $qty * $cost;
                $totalAmount += $totalCost;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'quantity_received' => 0,
                    'unit_cost' => $cost,
                    'total_cost' => $totalCost,
                ]);
            }

            $po->update(['total_amount' => $totalAmount]);

            return $po->load('items.product');
        });
    }

    public function update(PurchaseOrder $po, array $data, string $userId): PurchaseOrder
    {
        return DB::connection('tenant')->transaction(function () use ($po, $data, $userId) {
            if ($po->status !== PurchaseOrder::STATUS_DRAFT) {
                throw new \Exception('Apenas ordens de compra em rascunho podem ser atualizadas.');
            }

            $po->update([
                'supplier_id' => $data['supplier_id'] ?? $po->supplier_id,
                'branch_id' => $data['branch_id'] ?? $po->branch_id,
                'notes' => $data['notes'] ?? $po->notes,
                'updated_by' => $userId,
            ]);

            if (isset($data['items'])) {
                // Delete existing items
                $po->items()->delete();

                $totalAmount = 0;
                foreach ($data['items'] as $item) {
                    $product = Product::findOrFail($item['product_id']);
                    $qty = (float) $item['quantity'];
                    $cost = (float) $item['unit_cost'];
                    $totalCost = $qty * $cost;
                    $totalAmount += $totalCost;

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'quantity_received' => 0,
                        'unit_cost' => $cost,
                        'total_cost' => $totalCost,
                    ]);
                }
                $po->update(['total_amount' => $totalAmount]);
            }

            return $po->load('items.product');
        });
    }

    public function approve(PurchaseOrder $po, string $userId): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new \Exception('Apenas ordens de compra em rascunho podem ser aprovadas.');
        }

        $po->update([
            'status' => PurchaseOrder::STATUS_APPROVED,
            'ordered_at' => now(),
            'updated_by' => $userId,
        ]);

        return $po;
    }

    public function cancel(PurchaseOrder $po, string $userId): PurchaseOrder
    {
        if ($po->status === PurchaseOrder::STATUS_RECEIVED) {
            throw new \Exception('Ordens de compra já recebidas não podem ser canceladas.');
        }

        $po->update([
            'status' => PurchaseOrder::STATUS_CANCELLED,
            'updated_by' => $userId,
        ]);

        return $po;
    }

    public function receive(PurchaseOrder $po, array $receiptData, string $userId): PurchaseOrder
    {
        return DB::connection('tenant')->transaction(function () use ($po, $receiptData, $userId) {
            if ($po->status !== PurchaseOrder::STATUS_APPROVED) {
                throw new \Exception('Apenas ordens de compra aprovadas podem ser recebidas.');
            }

            foreach ($receiptData['received_items'] as $itemData) {
                $item = PurchaseOrderItem::findOrFail($itemData['purchase_order_item_id']);
                $qtyReceived = (float) $itemData['quantity_received'];

                if ($qtyReceived <= 0) {
                    continue;
                }

                $product = $item->product;

                // Update item received quantity
                $item->increment('quantity_received', $qtyReceived);

                // Record movement in inventory using InventoryService
                $this->inventoryService->recordMovement($product, InventoryLog::TYPE_INBOUND, (int) $qtyReceived, [
                    'unit_cost' => $item->unit_cost,
                    'unit_price' => $product->unit_price,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => $po->id,
                    'notes' => "Recebimento da Ordem de Compra #{$po->id}",
                ]);

                // Handle Serialized Products
                if ($product->is_serialized) {
                    $serials = $itemData['serials'] ?? [];
                    if (count($serials) !== (int) $qtyReceived) {
                        throw new InvalidArgumentException("O produto {$product->name} exige exatamente " . (int) $qtyReceived . " números de série.");
                    }

                    foreach ($serials as $serialNumber) {
                        // Check if serial already exists for this product
                        $exists = ProductSerial::where('product_id', $product->id)
                            ->where('serial_number', $serialNumber)
                            ->exists();

                        if ($exists) {
                            throw new \Exception("O número de série {$serialNumber} já existe para o produto {$product->name}.");
                        }

                        ProductSerial::create([
                            'product_id' => $product->id,
                            'serial_number' => $serialNumber,
                            'status' => ProductSerial::STATUS_AVAILABLE,
                            'purchase_order_item_id' => $item->id,
                        ]);
                    }
                }

                // Handle Batched Products
                if ($product->has_batches) {
                    $batchNumber = $itemData['batch_number'] ?? null;
                    if (empty($batchNumber)) {
                        throw new InvalidArgumentException("O produto {$product->name} exige a especificação de um número de lote.");
                    }

                    $expirationDate = $itemData['expiration_date'] ?? null;

                    $batch = ProductBatch::where('product_id', $product->id)
                        ->where('batch_number', $batchNumber)
                        ->first();

                    if ($batch) {
                        $batch->increment('current_quantity', $qtyReceived);
                    } else {
                        ProductBatch::create([
                            'product_id' => $product->id,
                            'batch_number' => $batchNumber,
                            'expiration_date' => $expirationDate,
                            'initial_quantity' => $qtyReceived,
                            'current_quantity' => $qtyReceived,
                            'purchase_order_item_id' => $item->id,
                        ]);
                    }
                }
            }

            // Move status to received
            $po->update([
                'status' => PurchaseOrder::STATUS_RECEIVED,
                'received_at' => now(),
                'updated_by' => $userId,
            ]);

            return $po->load('items.product');
        });
    }
}
