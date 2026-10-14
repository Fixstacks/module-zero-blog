<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Zero\Blog\Model\Comment;

class CommentStatus implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function toOptionArray()
    {
        return [
            ['value' => Comment::STATUS_PENDING, 'label' => (string) __('Pending')],
            ['value' => Comment::STATUS_APPROVED, 'label' => (string) __('Approved')],
            ['value' => Comment::STATUS_REJECTED, 'label' => (string) __('Rejected')],
        ];
    }
}
