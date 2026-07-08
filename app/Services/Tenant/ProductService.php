<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductCategory;
use App\DTOs\Tenant\CreateProductDTO;
use App\DTOs\Tenant\UpdateProductDTO;
use Illuminate\Support\Collection;

class ProductService
{
    public function create(CreateProductDTO $dto): Product
    {
        // Validate category exists
        ProductCategory::findOrFail($dto->category_id);

        $product = Product::create([
            'category_id' => $dto->category_id,
            'name' => $dto->name,
            'sku' => $dto->sku,
            'description' => $dto->description,
            'unit_price' => $dto->unit_price ?? 0,
            'cost_price' => $dto->cost_price,
            'stock_quantity' => $dto->stock_quantity,
            'min_stock_level' => $dto->min_stock_level,
            'max_stock_level' => $dto->max_stock_level,
            'unit_of_measure' => $dto->unit_of_measure,
            'is_active' => true,
        ]);

        return $product;
    }

    public function update(Product $product, UpdateProductDTO $dto): Product
    {
        $data = [];

        if ($dto->name !== null) {
            $data['name'] = $dto->name;
        }

        if ($dto->description !== null) {
            $data['description'] = $dto->description;
        }

        if ($dto->unit_price !== null) {
            $data['unit_price'] = $dto->unit_price;
        }

        if ($dto->cost_price !== null) {
            $data['cost_price'] = $dto->cost_price;
        }

        if ($dto->min_stock_level !== null) {
            $data['min_stock_level'] = $dto->min_stock_level;
        }

        if ($dto->max_stock_level !== null) {
            $data['max_stock_level'] = $dto->max_stock_level;
        }

        if ($dto->is_active !== null) {
            $data['is_active'] = $dto->is_active;
        }

        $product->update($data);

        return $product;
    }

    public function delete(Product $product): bool
    {
        return $product->delete();
    }

    public function adjustStock(Product $product, int $quantity, string $reason): Product
    {
        $product->increment('stock_quantity', $quantity);
        
        $product->setMetadataValue('last_adjustment', [
            'quantity' => $quantity,
            'reason' => $reason,
            'adjusted_at' => now()->toIso8601String(),
        ]);
        $product->save();

        return $product;
    }

    public function getByCategory(string $categoryId): Collection
    {
        return Product::byCategory($categoryId)->active()->get();
    }

    public function getLowStockProducts(): Collection
    {
        return Product::lowStock()->get();
    }

    public function getOutOfStockProducts(): Collection
    {
        return Product::outOfStock()->get();
    }

    public function search(string $term): Collection
    {
        return Product::searchable($term)->active()->get();
    }

    public function getProductStats(): array
    {
        return [
            'total' => Product::count(),
            'active' => Product::active()->count(),
            'low_stock' => Product::lowStock()->count(),
            'out_of_stock' => Product::outOfStock()->count(),
            'total_value' => Product::active()->get()->sum(function ($p) {
                return $p->stock_quantity * $p->unit_price;
            }),
        ];
    }
}
