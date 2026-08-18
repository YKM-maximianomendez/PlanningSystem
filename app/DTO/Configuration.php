<?php

namespace App\DTO;

use App\DTO\Configuration\Adjustment;
use App\DTO\Configuration\Customer;
use App\DTO\Configuration\Material;
use App\DTO\Configuration\MDI;
use App\DTO\Configuration\Product;
use App\Enums\ForecastStrategy;
use App\Enums\ShopCalendar;
use Illuminate\Contracts\Support\Arrayable;

final class Configuration implements Arrayable
{
    /**
     * @param  Product[]  $products
     * @param  Adjustment[]  $adjustments
     */
    public function __construct(
        public int $level,
        public Material $material,
        public MDI $mdi,
        public array $products,
        public Product $planningProduct,
        public Customer $customer,
        public ForecastStrategy $forecastStrategy,
        public ShopCalendar $calendar,
        public array $adjustments = [],
        public ?Product $blankProduct = null
    ) {}

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'material' => $this->material->toArray(),
            'products' => array_map(fn (Product $product) => $product->toArray(), $this->products),
            'mdi' => $this->mdi->toArray(),
            'customer' => $this->customer->toArray(),
            'forecastStrategy' => $this->forecastStrategy->value,
            'calendar' => $this->calendar->value,
            'adjustments' => array_map(fn (Adjustment $adjustment) => [
                'adjustmentId' => $adjustment->adjustmentId,
                'adjustmentCode' => $adjustment->adjustmentCode,
                'adjustmentValue' => $adjustment->adjustmentValue,
            ], $this->adjustments),
            'planning' => [
                'product' => $this->planningProduct->toArray(),
                'blankProduct' => $this->blankProduct?->toArray(),
            ],
        ];
    }

    public static function getPlanningProduct(array $products, int $level, int $mdiId): ?Product
    {
        return collect($products)
            ->filter(fn (Product $product) => $product->level === $level && $product->mdiId === $mdiId)
            ->sortBy(fn (Product $product) => $product->lastCycleCount?->getTheoricalStock() ?? 0)
            ->first();
    }
}
