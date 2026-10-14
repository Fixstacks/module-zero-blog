<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * A reader's comment on a post. Shown once approved; approving or removing
 * one refreshes its post's page.
 */
class Comment extends AbstractModel implements IdentityInterface
{
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    /** @var string */
    protected $_eventPrefix = 'zero_blog_comment';

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Comment::class);
    }

    /**
     * Its post's page, when the comment shows there (or did): a new pending
     * comment doesn't purge anything.
     *
     * @return string[]
     */
    public function getIdentities()
    {
        $shown = (int) $this->getData('status') === self::STATUS_APPROVED
            || (int) $this->getOrigData('status') === self::STATUS_APPROVED;

        return $shown ? [Post::CACHE_TAG . '_' . (int) $this->getData('post_id')] : [];
    }
}
