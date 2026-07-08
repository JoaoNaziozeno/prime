<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Service;
use App\Models\Tenant\ServiceCategory;
use App\DTOs\Tenant\CreateServiceDTO;
use App\DTOs\Tenant\UpdateServiceDTO;
use Illuminate\Support\Collection;

class ServiceService
{
    public function create(CreateServiceDTO $dto): Service
    {
        // Validate category exists
        ServiceCategory::findOrFail($dto->category_id);

        $service = Service::create([
            'category_id' => $dto->category_id,
            'name' => $dto->name,
            'code' => $dto->code,
            'description' => $dto->description,
            'base_price' => $dto->base_price ?? 0,
            'estimated_hours' => $dto->estimated_hours,
            'is_active' => true,
        ]);

        return $service;
    }

    public function update(Service $service, UpdateServiceDTO $dto): Service
    {
        $data = [];

        if ($dto->name !== null) {
            $data['name'] = $dto->name;
        }

        if ($dto->description !== null) {
            $data['description'] = $dto->description;
        }

        if ($dto->base_price !== null) {
            $data['base_price'] = $dto->base_price;
        }

        if ($dto->estimated_hours !== null) {
            $data['estimated_hours'] = $dto->estimated_hours;
        }

        if ($dto->is_active !== null) {
            $data['is_active'] = $dto->is_active;
        }

        $service->update($data);

        return $service;
    }

    public function delete(Service $service): bool
    {
        return $service->delete();
    }

    public function getByCategory(string $categoryId): Collection
    {
        return Service::byCategory($categoryId)->active()->get();
    }

    public function search(string $term): Collection
    {
        return Service::searchable($term)->active()->get();
    }

    public function getServiceStats(): array
    {
        return [
            'total' => Service::count(),
            'active' => Service::active()->count(),
            'by_category' => ServiceCategory::withCount('services')->get(),
        ];
    }

    public function getPriceWithMarkup(Service $service, float $markupPercentage): float
    {
        return $service->getPriceWithMarkup($markupPercentage);
    }

    public function getEstimatedCostPerHour(Service $service): float
    {
        if (!$service->estimated_hours || $service->estimated_hours == 0) {
            return $service->base_price;
        }

        return $service->base_price / $service->estimated_hours;
    }
}
