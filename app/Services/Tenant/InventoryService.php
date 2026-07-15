<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\InventoryLog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Registra uma movimentação de estoque
     */
    public function recordMovement(Product $product, string $type, int $quantity, array $options = []): InventoryLog
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('A quantidade de movimentação deve ser maior que zero.');
        }

        if (!in_array($type, [InventoryLog::TYPE_INBOUND, InventoryLog::TYPE_OUTBOUND, InventoryLog::TYPE_ADJUSTMENT, InventoryLog::TYPE_TRANSFER])) {
            throw new InvalidArgumentException('Tipo de movimentação inválido.');
        }

        return DB::connection('tenant')->transaction(function () use ($product, $type, $quantity, $options) {
            // Lock para evitar concorrência
            $product = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $newQuantity = $product->stock_quantity;

            if ($type === InventoryLog::TYPE_INBOUND) {
                $newQuantity += $quantity;
            } elseif ($type === InventoryLog::TYPE_OUTBOUND) {
                if ($product->stock_quantity < $quantity) {
                    throw new \Exception("Estoque insuficiente para o produto: {$product->name}. Disponível: {$product->stock_quantity}, Solicitado: {$quantity}");
                }
                $newQuantity -= $quantity;
            } elseif ($type === InventoryLog::TYPE_ADJUSTMENT) {
                $relativeQty = $options['relative_quantity'] ?? $quantity;
                $newQuantity += $relativeQty;
                if ($newQuantity < 0) {
                    throw new \Exception("Ajuste resulta em estoque negativo para o produto: {$product->name}. Disponível: {$product->stock_quantity}, Ajuste: {$relativeQty}");
                }
            }

            // Atualiza estoque do produto
            $product->update(['stock_quantity' => $newQuantity]);

            // Cria log de movimentação
            return InventoryLog::create([
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $newQuantity,
                'unit_cost' => $options['unit_cost'] ?? $product->cost_price,
                'unit_price' => $options['unit_price'] ?? $product->unit_price,
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
                'notes' => $options['notes'] ?? null,
            ]);
        });
    }

    /**
     * Realiza um ajuste manual direto de estoque
     */
    public function adjustStock(Product $product, int $newQuantity, ?string $notes = null): InventoryLog
    {
        if ($newQuantity < 0) {
            throw new InvalidArgumentException('A quantidade de estoque não pode ser negativa.');
        }

        return DB::connection('tenant')->transaction(function () use ($product, $newQuantity, $notes) {
            $product = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

            $oldQuantity = $product->stock_quantity;
            $diff = $newQuantity - $oldQuantity;

            $product->update(['stock_quantity' => $newQuantity]);

            return InventoryLog::create([
                'product_id' => $product->id,
                'type' => InventoryLog::TYPE_ADJUSTMENT,
                'quantity' => abs($diff),
                'balance_after' => $newQuantity,
                'unit_cost' => $product->cost_price,
                'unit_price' => $product->unit_price,
                'notes' => $notes ?? "Ajuste manual de estoque de {$oldQuantity} para {$newQuantity}",
            ]);
        });
    }
}
