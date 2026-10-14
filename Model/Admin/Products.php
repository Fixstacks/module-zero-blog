<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Admin;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;

/**
 * A post's products as SKUs in its form (comma or line separated, in order).
 */
class Products
{
    public function __construct(private readonly ProductResource $productResource)
    {
    }

    /**
     * @return array{ids: int[], unknown: string[]}
     */
    public function idsFor(string $skus): array
    {
        $list = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\n]+/', $skus) ?: []))));
        if (!$list) {
            return ['ids' => [], 'unknown' => []];
        }
        $found = array_change_key_case($this->productResource->getProductsIdsBySkus($list), CASE_LOWER);
        $ids = [];
        $unknown = [];
        foreach ($list as $sku) {
            if (isset($found[strtolower($sku)])) {
                $ids[] = (int) $found[strtolower($sku)];
            } else {
                $unknown[] = $sku;
            }
        }

        return ['ids' => array_values(array_unique($ids)), 'unknown' => $unknown];
    }

    /**
     * @param int[] $ids
     */
    public function skusOf(array $ids): string
    {
        if (!$ids) {
            return '';
        }
        $skus = array_column($this->productResource->getProductsSku($ids), 'sku', 'entity_id');

        return implode(', ', array_filter(array_map(static fn (int $id) => $skus[$id] ?? null, $ids)));
    }
}
