<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Page;

use Magento\Framework\DB\Select;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Blog\Model\Author;
use Zero\Blog\Model\Category;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\Post;
use Zero\Blog\Model\ResourceModel\Post\Collection;
use Zero\Blog\Model\ResourceModel\Post\CollectionFactory;
use Zero\Blog\Model\Tag;

/**
 * Visible posts of this store view: the current list page's, a post's
 * related ones, a product's.
 */
class Posts
{
    private ?Collection $page = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Current $current,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * The current list page's posts (index, category, tag, author).
     */
    public function page(): Collection
    {
        if ($this->page === null) {
            $collection = $this->visible();
            $entity = $this->current->entity();
            match (true) {
                $entity instanceof Category => $collection->addCategoryFilter((int) $entity->getId()),
                $entity instanceof Tag => $collection->addTagFilter((int) $entity->getId()),
                $entity instanceof Author => $collection->addAuthorFilter((int) $entity->getId()),
                default => null,
            };
            $collection->setPageSize($this->config->postsPerPage($this->storeId()))->setCurPage($this->current->page());
            $this->page = $collection;
        }

        return $this->page;
    }

    public function total(): int
    {
        return (int) $this->page()->getSize();
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total() / $this->config->postsPerPage($this->storeId())));
    }

    /**
     * Posts sharing a category with this one, then the newest, up to $limit.
     *
     * @return Post[]
     */
    public function related(Post $post, int $limit = 3): array
    {
        $found = [];
        if ($post->getCategoryIds()) {
            $collection = $this->visible()->addFieldToFilter('main_table.post_id', ['neq' => $post->getId()]);
            $collection->getSelect()->where(
                'EXISTS (?)',
                $collection->getConnection()->select()
                    ->from(['c' => $collection->getTable('zero_blog_post_category')], ['post_id'])
                    ->where('c.post_id = main_table.post_id')
                    ->where('c.category_id IN (?)', $post->getCategoryIds())
            );
            $found = $collection->setPageSize($limit)->getItems();
        }
        if (count($found) < $limit) {
            $more = $this->visible()
                ->addFieldToFilter('main_table.post_id', ['nin' => array_merge([(int) $post->getId()], array_keys($found))])
                ->setPageSize($limit - count($found));
            $found += $more->getItems();
        }

        return array_values($found);
    }

    /**
     * @return Post[]
     */
    public function forProduct(int $productId, int $limit = 3): array
    {
        return array_values($this->visible()->addProductFilter([$productId])->setPageSize($limit)->getItems());
    }

    /**
     * @return Post[]
     */
    public function latest(int $limit): array
    {
        return array_values($this->visible()->setPageSize($limit)->getItems());
    }

    /**
     * Ids of the categories with a post the store view shows.
     *
     * @return int[]
     */
    public function usedCategoryIds(): array
    {
        $collection = $this->visible();
        $select = $collection->getSelect()
            ->reset(Select::COLUMNS)
            ->reset(Select::ORDER)
            ->join(['pc' => $collection->getTable('zero_blog_post_category')], 'pc.post_id = main_table.post_id', ['category_id'])
            ->distinct(true);

        return array_map('intval', $collection->getConnection()->fetchCol($select));
    }

    private function visible(): Collection
    {
        return $this->collectionFactory->create()->addVisibleFilter($this->storeId(), null, !$this->config->ownPostsOnly($this->storeId()))->addLinks();
    }

    private function storeId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }
}
