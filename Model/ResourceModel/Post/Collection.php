<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\ResourceModel\Post;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zero\Blog\Model\Post;
use Zero\Blog\Model\ResourceModel\Post as Resource;

/**
 * Posts with storefront filters. Link lists (store_ids, category_ids,
 * tag_ids, product_ids) are loaded for every post with addLinks().
 */
class Collection extends AbstractCollection
{
    /** @var string */
    protected $_idFieldName = 'post_id';

    private bool $withLinks = false;

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Post::class, Resource::class);
    }

    /**
     * Published, date reached, in the store view (and, unless
     * $withAllStoreViews is false, the posts set to All Store Views), newest first.
     *
     * @return $this
     */
    public function addVisibleFilter(int $storeId, ?string $nowUtc = null, bool $withAllStoreViews = true): self
    {
        $this->addFieldToFilter('main_table.is_active', 1)
            ->addFieldToFilter('main_table.published_at', ['lteq' => $nowUtc ?? gmdate('Y-m-d H:i:s')]);
        $this->getSelect()->where(
            'EXISTS (?)',
            $this->getConnection()->select()
                ->from(['s' => $this->getTable('zero_blog_post_store')], ['post_id'])
                ->where('s.post_id = main_table.post_id')
                ->where('s.store_id IN (?)', $withAllStoreViews ? [0, $storeId] : [$storeId])
        );
        $this->getSelect()->order(['main_table.published_at DESC', 'main_table.post_id DESC']);

        return $this;
    }

    /**
     * @return $this
     */
    public function addCategoryFilter(int $categoryId): self
    {
        return $this->addLinkFilter('zero_blog_post_category', 'category_id', [$categoryId]);
    }

    /**
     * @return $this
     */
    public function addTagFilter(int $tagId): self
    {
        return $this->addLinkFilter('zero_blog_post_tag', 'tag_id', [$tagId]);
    }

    /**
     * @param int[] $productIds
     * @return $this
     */
    public function addProductFilter(array $productIds): self
    {
        return $this->addLinkFilter('zero_blog_post_product', 'product_id', $productIds ?: [0]);
    }

    /**
     * @return $this
     */
    public function addAuthorFilter(int $authorId): self
    {
        return $this->addFieldToFilter('main_table.author_id', $authorId);
    }

    /**
     * @return $this
     */
    public function addLinks(): self
    {
        $this->withLinks = true;

        return $this;
    }

    /**
     * @param int[] $ids
     * @return $this
     */
    private function addLinkFilter(string $table, string $column, array $ids): self
    {
        $this->getSelect()->where(
            'EXISTS (?)',
            $this->getConnection()->select()
                ->from(['l' => $this->getTable($table)], ['post_id'])
                ->where('l.post_id = main_table.post_id')
                ->where('l.' . $column . ' IN (?)', $ids)
        );

        return $this;
    }

    /**
     * @return $this
     */
    protected function _afterLoad()
    {
        if ($this->withLinks) {
            /** @var Resource $resource */
            $resource = $this->getResource();
            foreach ($this->_items as $post) {
                foreach ($resource->links((int) $post->getId()) as $key => $ids) {
                    $post->setData($key, $ids);
                    $post->setOrigData($key, $ids);
                }
            }
        }

        return parent::_afterLoad();
    }
}
