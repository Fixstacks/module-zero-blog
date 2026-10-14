<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\ResourceModel\Author;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zero\Blog\Model\Author;
use Zero\Blog\Model\ResourceModel\Author as Resource;

class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'author_id';

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Author::class, Resource::class);
    }
}
