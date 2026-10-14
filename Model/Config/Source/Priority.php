<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Sitemap priorities 0.1 to 1.0.
 */
class Priority implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray()
    {
        return array_map(static fn (int $i) => ['value' => sprintf('%.1f', $i / 10), 'label' => sprintf('%.1f', $i / 10)], range(10, 1));
    }
}
