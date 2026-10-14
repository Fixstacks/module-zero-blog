<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Zero\Blog\Model\ResourceModel\Author\CollectionFactory;

class Authors implements OptionSourceInterface
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray()
    {
        $options = [['value' => '', 'label' => (string) __('No author')]];
        foreach ($this->collectionFactory->create()->setOrder('name', 'ASC') as $author) {
            $options[] = ['value' => (string) $author->getId(), 'label' => (string) $author->getData('name')];
        }

        return $options;
    }
}
