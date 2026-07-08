<?php

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductCategory;
use App\Models\Tenant\Service;
use App\Models\Tenant\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Product Model', function () {

    test('create product with factory', function () {
        $product = Product::factory()->create();

        expect($product)->toBeInstanceOf(Product::class);
        expect($product->sku)->not->toBeEmpty();
        expect($product->unit_price)->toBeGreaterThan(0);
    });

    test('product belongs to category', function () {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->forCategory($category)->create();

        expect($product->category_id)->toBe($category->id);
        expect($product->category)->toBeInstanceOf(ProductCategory::class);
    });

    test('product margin calculation is correct', function () {
        $product = Product::factory()->create([
            'unit_price' => 1000,
            'cost_price' => 600,
        ]);

        $margin = $product->getMarginPercentage();
        $marginAmount = $product->getMarginAmount();

        expect($margin)->toBeCloseTo(66.67, 0.1);
        expect($marginAmount)->toBe(400.0);
    });

    test('product stock status correctly identified', function () {
        $outOfStock = Product::factory()->outOfStock()->create();
        $lowStock = Product::factory()->lowStock()->create();
        $inStock = Product::factory()->create(['stock_quantity' => 100]);

        expect($outOfStock->isOutOfStock())->toBeTrue();
        expect($lowStock->isLowStock())->toBeTrue();
        expect($inStock->getStockStatus())->toBe('in_stock');
    });

    test('product can sell quantity', function () {
        $product = Product::factory()->create(['stock_quantity' => 10]);

        expect($product->canSell(5))->toBeTrue();
        expect($product->canSell(10))->toBeTrue();
        expect($product->canSell(15))->toBeFalse();
    });

    test('scopes filter products correctly', function () {
        Product::factory(3)->active()->create();
        Product::factory(2)->inactive()->create();
        Product::factory(2)->lowStock()->create();

        expect(Product::active()->count())->toBe(5);
        expect(Product::lowStock()->count())->toBe(2);
    });

    test('search finds products by name', function () {
        Product::factory()->create(['name' => 'Filtro de Ar']);
        Product::factory()->create(['name' => 'Bateria']);

        $results = Product::searchable('Filtro')->get();

        expect($results->count())->toBe(1);
    });

    test('product metadata can be stored', function () {
        $product = Product::factory()->create();

        $product->setMetadataValue('supplier', 'Fornecedor XYZ');
        $product->save();

        expect($product->getMetadataValue('supplier'))->toBe('Fornecedor XYZ');
    });

});

describe('ProductCategory Model', function () {

    test('create category with factory', function () {
        $category = ProductCategory::factory()->create();

        expect($category)->toBeInstanceOf(ProductCategory::class);
        expect($category->slug)->not->toBeEmpty();
    });

    test('category slug is automatically generated', function () {
        $category = ProductCategory::factory()->create(['name' => 'Peças Diesel']);

        expect($category->slug)->toBe('pecas-diesel');
    });

    test('category has many products', function () {
        $category = ProductCategory::factory()->create();
        Product::factory(5)->forCategory($category)->create();

        expect($category->products()->count())->toBe(5);
    });

    test('active scope filters categories', function () {
        ProductCategory::factory(2)->active()->create();
        ProductCategory::factory(1)->inactive()->create();

        expect(ProductCategory::active()->count())->toBe(2);
    });

});

describe('Service Model', function () {

    test('create service with factory', function () {
        $service = Service::factory()->create();

        expect($service)->toBeInstanceOf(Service::class);
        expect($service->code)->not->toBeEmpty();
        expect($service->base_price)->toBeGreaterThan(0);
    });

    test('service belongs to category', function () {
        $category = ServiceCategory::factory()->create();
        $service = Service::factory()->forCategory($category)->create();

        expect($service->category_id)->toBe($category->id);
        expect($service->category)->toBeInstanceOf(ServiceCategory::class);
    });

    test('service price with markup', function () {
        $service = Service::factory()->create(['base_price' => 1000]);

        $priceWith10Percent = $service->getPriceWithMarkup(10);

        expect($priceWith10Percent)->toBe(1100.0);
    });

    test('service estimated cost per hour', function () {
        $service = Service::factory()->create([
            'base_price' => 400,
            'estimated_hours' => 4,
        ]);

        $costPerHour = 400 / 4;

        expect($service->base_price / $service->estimated_hours)->toBe($costPerHour);
    });

    test('scopes filter services correctly', function () {
        Service::factory(3)->active()->create();
        Service::factory(2)->inactive()->create();

        expect(Service::active()->count())->toBe(3);
    });

    test('search finds services', function () {
        Service::factory()->create(['name' => 'Revisão Motor', 'code' => 'S001']);
        Service::factory()->create(['name' => 'Troca de Óleo', 'code' => 'S002']);

        $results = Service::searchable('Motor')->get();

        expect($results->count())->toBe(1);
    });

    test('service metadata can be stored', function () {
        $service = Service::factory()->create();

        $service->setMetadataValue('requires_tools', ['chave_inglesa', 'chave_phillips']);
        $service->save();

        expect($service->getMetadataValue('requires_tools'))->toHaveCount(2);
    });

});

describe('ServiceCategory Model', function () {

    test('create category with factory', function () {
        $category = ServiceCategory::factory()->create();

        expect($category)->toBeInstanceOf(ServiceCategory::class);
        expect($category->slug)->not->toBeEmpty();
    });

    test('category slug is automatically generated', function () {
        $category = ServiceCategory::factory()->create(['name' => 'Reparos Diesel']);

        expect($category->slug)->toBe('reparos-diesel');
    });

    test('category has many services', function () {
        $category = ServiceCategory::factory()->create();
        Service::factory(3)->forCategory($category)->create();

        expect($category->services()->count())->toBe(3);
    });

    test('active scope filters categories', function () {
        ServiceCategory::factory(2)->active()->create();
        ServiceCategory::factory(1)->inactive()->create();

        expect(ServiceCategory::active()->count())->toBe(2);
    });

});
