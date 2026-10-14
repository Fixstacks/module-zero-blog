<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Zero\Blog\Model\ResourceModel\Category\CollectionFactory;

class Categories implements OptionSourceInterface
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray()
    {
        $options = [];
        foreach ($this->collectionFactory->create()->setOrder('position', 'ASC')->setOrder('title', 'ASC') as $category) {
            $options[] = ['value' => (string) $category->getId(), 'label' => (string) $category->getData('title')];
        }

        return $options;
    }
}
