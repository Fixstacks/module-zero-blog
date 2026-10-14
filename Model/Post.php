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
 * A blog post: title, URL key, excerpt, featured image, author, publish
 * date, SEO fields, and its store views, categories, tags and products
 * (store_ids, category_ids, tag_ids, product_ids; ResourceModel\Post keeps
 * them). The body is Zero CMS content of type self::CONTENT_TYPE.
 *
 * Cache: saving refreshes the post's page, every blog list, and the pages of
 * the products it is (or was) linked to.
 */
class Post extends AbstractModel implements IdentityInterface
{
    public const CACHE_TAG = 'zero_blog_post';
    /** Every blog page carries it: index, categories, tags, authors, posts, RSS. */
    public const LIST_TAG = 'zero_blog';
    /** A product page's "From the blog" block: zero_blog_product_<product id>. */
    public const PRODUCT_TAG = 'zero_blog_product';
    /** Zero CMS content type of the body. */
    public const CONTENT_TYPE = 'zero_blog_post';

    /** @var string */
    protected $_eventPrefix = 'zero_blog_post';

    /** @var string */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Post::class);
    }

    /**
     * @return string[]
     */
    public function getIdentities()
    {
        $tags = [self::LIST_TAG, self::CACHE_TAG . '_' . $this->getId()];
        $products = array_merge((array) $this->getData('product_ids'), (array) $this->getOrigData('product_ids'));
        foreach (array_unique(array_map('intval', $products)) as $productId) {
            $tags[] = self::PRODUCT_TAG . '_' . $productId;
        }

        return $tags;
    }

    /**
     * @return int[]
     */
    public function getStoreIds(): array
    {
        return array_map('intval', (array) $this->getData('store_ids'));
    }

    /**
     * @return int[]
     */
    public function getCategoryIds(): array
    {
        return array_map('intval', (array) $this->getData('category_ids'));
    }

    /**
     * @return int[]
     */
    public function getTagIds(): array
    {
        return array_map('intval', (array) $this->getData('tag_ids'));
    }

    /**
     * Linked products, in their order.
     *
     * @return int[]
     */
    public function getProductIds(): array
    {
        return array_map('intval', (array) $this->getData('product_ids'));
    }

    /**
     * Published and its date reached (UTC).
     */
    public function isVisible(?string $nowUtc = null): bool
    {
        $publishedAt = (string) $this->getData('published_at');

        return (int) $this->getData('is_active') === 1
            && $publishedAt !== ''
            && $publishedAt <= ($nowUtc ?? gmdate('Y-m-d H:i:s'));
    }

    public function visibleInStore(int $storeId): bool
    {
        $stores = $this->getStoreIds();

        return in_array(0, $stores, true) || in_array($storeId, $stores, true);
    }
}
