<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PostStatus implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function toOptionArray()
    {
        return [
            ['value' => 1, 'label' => (string) __('Published')],
            ['value' => 0, 'label' => (string) __('Draft')],
        ];
    }
}
