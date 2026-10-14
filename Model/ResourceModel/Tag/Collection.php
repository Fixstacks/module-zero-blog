<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\ResourceModel\Tag;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zero\Blog\Model\Tag;
use Zero\Blog\Model\ResourceModel\Tag as Resource;

class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'tag_id';

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Tag::class, Resource::class);
    }
}
