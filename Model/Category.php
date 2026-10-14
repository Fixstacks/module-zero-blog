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
 * A blog category. Saving or deleting it refreshes every blog page in the page
 * cache (they all list categorys or link to them).
 */
class Category extends AbstractModel implements IdentityInterface
{
    public const CACHE_TAG = 'zero_blog_category';

    /** @var string */
    protected $_eventPrefix = 'zero_blog_category';

    /** @var string */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Category::class);
    }

    /**
     * @return string[]
     */
    public function getIdentities()
    {
        return [Post::LIST_TAG, self::CACHE_TAG . '_' . $this->getId()];
    }
}
