<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\ResourceModel\Comment;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface;

/**
 * The admin comment grid: comments with their post's title.
 */
class Grid extends SearchResult
{
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        $mainTable = 'zero_blog_comment',
        $resourceModel = \Zero\Blog\Model\ResourceModel\Comment::class
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel);
    }

    /**
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['post' => $this->getTable('zero_blog_post')],
            'post.post_id = main_table.post_id',
            ['post_title' => 'post.title']
        );
        $this->addFilterToMap('post_title', 'post.title')
            ->addFilterToMap('created_at', 'main_table.created_at')
            ->addFilterToMap('status', 'main_table.status');

        return $this;
    }
}
