<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The blog's URLs (Controller\Router reads the same shapes):
 *
 *   /blog                 the index (?p=2: page 2)
 *   /blog/<post>          a post
 *   /blog/category/<key>  /blog/tag/<key>  /blog/author/<key>
 *   /blog/rss.xml         the feed
 *
 * "blog" is the configured route.
 */
class Url
{
    public const CATEGORY = 'category';
    public const TAG = 'tag';
    public const AUTHOR = 'author';
    public const RSS = 'rss.xml';

    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function index(?int $storeId = null, int $page = 1): string
    {
        return $this->base($storeId) . ($page > 1 ? '?p=' . $page : '');
    }

    public function post(Post $post, ?int $storeId = null): string
    {
        return $this->base($storeId) . '/' . rawurlencode((string) $post->getData('url_key'));
    }

    public function category(Category $category, ?int $storeId = null, int $page = 1): string
    {
        return $this->list(self::CATEGORY, (string) $category->getData('url_key'), $storeId, $page);
    }

    public function tag(Tag $tag, ?int $storeId = null, int $page = 1): string
    {
        return $this->list(self::TAG, (string) $tag->getData('url_key'), $storeId, $page);
    }

    public function author(Author $author, ?int $storeId = null, int $page = 1): string
    {
        return $this->list(self::AUTHOR, (string) $author->getData('url_key'), $storeId, $page);
    }

    public function rss(?int $storeId = null): string
    {
        return $this->base($storeId) . '/' . self::RSS;
    }

    /** Store-relative path of a post, as url_rewrite stores targets. */
    public function postPath(Post $post, ?int $storeId = null): string
    {
        return $this->config->route($storeId) . '/' . (string) $post->getData('url_key');
    }

    private function list(string $type, string $key, ?int $storeId, int $page): string
    {
        return $this->base($storeId) . '/' . $type . '/' . rawurlencode($key) . ($page > 1 ? '?p=' . $page : '');
    }

    private function base(?int $storeId): string
    {
        /** @var StoreInterface $store */
        $store = $this->storeManager->getStore($storeId);

        return $store->getBaseUrl() . $this->config->route((int) $store->getId());
    }
}
