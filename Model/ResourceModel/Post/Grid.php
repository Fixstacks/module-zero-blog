<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\ResourceModel\Post;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface;

/**
 * The admin post grid: posts with their author's name and comment counts.
 */
class Grid extends SearchResult
{
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        $mainTable = 'zero_blog_post',
        $resourceModel = \Zero\Blog\Model\ResourceModel\Post::class
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel);
    }

    /**
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()
            ->joinLeft(['author' => $this->getTable('zero_blog_author')], 'author.author_id = main_table.author_id', ['author_name' => 'author.name'])
            ->columns(['pending_comments' => new \Zend_Db_Expr(sprintf(
                '(SELECT COUNT(*) FROM %s c WHERE c.post_id = main_table.post_id AND c.status = 0)',
                $this->getTable('zero_blog_comment')
            ))]);
        $this->addFilterToMap('post_id', 'main_table.post_id')
            ->addFilterToMap('title', 'main_table.title')
            ->addFilterToMap('author_name', 'author.name')
            ->addFilterToMap('updated_at', 'main_table.updated_at')
            ->addFilterToMap('is_active', 'main_table.is_active');

        return $this;
    }
}
