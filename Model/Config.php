<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Stores > Configuration > Zero > Blog, per store view.
 */
class Config
{
    private const PREFIX = 'zero_blog/';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->flag('general/enabled', $storeId);
    }

    /** The blog's path: example.com/<route>/... */
    public function route(?int $storeId = null): string
    {
        $route = trim(strtolower($this->value('general/route', $storeId)), '/');

        return preg_match('#^[a-z0-9][a-z0-9_-]*(/[a-z0-9][a-z0-9_-]*)*$#', $route) ? $route : 'blog';
    }

    public function title(?int $storeId = null): string
    {
        return $this->value('general/title', $storeId) ?: 'Blog';
    }

    public function metaTitle(?int $storeId = null): string
    {
        return $this->value('general/meta_title', $storeId);
    }

    public function metaDescription(?int $storeId = null): string
    {
        return $this->value('general/meta_description', $storeId);
    }

    public function introduction(?int $storeId = null): string
    {
        return $this->value('general/introduction', $storeId);
    }

    public function postsPerPage(?int $storeId = null): int
    {
        return max(1, min(60, (int) $this->value('general/posts_per_page', $storeId) ?: 12));
    }

    public function commentsEnabled(?int $storeId = null): bool
    {
        return $this->flag('comments/enabled', $storeId);
    }


    public function rssEnabled(?int $storeId = null): bool
    {
        return $this->flag('rss/enabled', $storeId);
    }

    public function sitemapPriority(string $type, ?int $storeId = null): string
    {
        return $this->value('sitemap/' . $type . '_priority', $storeId) ?: '0.5';
    }

    public function sitemapChangefreq(string $type, ?int $storeId = null): string
    {
        return $this->value('sitemap/' . $type . '_changefreq', $storeId) ?: 'weekly';
    }

    public function showOnProductPage(?int $storeId = null): bool
    {
        return $this->flag('products/show_on_product_page', $storeId);
    }

    /** Publisher logo for structured data; empty: the store's header logo. */
    public function publisherLogo(?int $storeId = null): string
    {
        return $this->value('seo/publisher_logo', $storeId);
    }

    private function value(string $path, ?int $storeId): string
    {
        return trim((string) $this->scopeConfig->getValue(self::PREFIX . $path, ScopeInterface::SCOPE_STORE, $storeId));
    }

    private function flag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . $path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
