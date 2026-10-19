<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Sitemap;

use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\ResourceModel\Author\CollectionFactory as AuthorCollectionFactory;
use Zero\Blog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Zero\Blog\Model\ResourceModel\Post\CollectionFactory as PostCollectionFactory;

/**
 * The blog in Magento's XML sitemap (Marketing > Site Map): the index,
 * every visible post (with its featured image), category and author.
 * Tags are left out: thin pages that mostly repeat categories.
 */
class ItemProvider implements ItemProviderInterface
{
    public function __construct(
        private readonly SitemapItemInterfaceFactory $itemFactory,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly PostCollectionFactory $postCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly AuthorCollectionFactory $authorCollectionFactory
    ) {
    }

    /**
     * @param int $storeId
     * @return \Magento\Sitemap\Model\SitemapItemInterface[]
     */
    public function getItems($storeId)
    {
        $storeId = (int) $storeId;
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }
        $route = $this->config->route($storeId);
        $items = [];
        $posts = $this->postCollectionFactory->create()->addVisibleFilter($storeId, null, !$this->config->ownPostsOnly($storeId));
        $lastPost = null;
        $usedAuthors = [];
        foreach ($posts as $post) {
            $lastPost ??= (string) $post->getData('updated_at');
            $image = (string) $post->getData('image');
            $items[] = $this->item(
                $route . '/' . $post->getData('url_key'),
                (string) $post->getData('updated_at'),
                'post',
                $storeId,
                $image !== '' ? [
                    'collection' => [new \Magento\Framework\DataObject([
                        'url' => $this->storeManager->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . ltrim($image, '/'),
                        'caption' => (string) $post->getData('image_alt') ?: (string) $post->getData('title'),
                    ])],
                    'title' => (string) $post->getData('title'),
                    'thumbnail' => $this->storeManager->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . ltrim($image, '/'),
                ] : null
            );
            $usedAuthors[(int) $post->getData('author_id')] = true;
        }
        array_unshift($items, $this->item($route, $lastPost, 'index', $storeId));

        foreach ($this->categoryCollectionFactory->create()->addFieldToFilter('is_active', 1) as $category) {
            $items[] = $this->item($route . '/category/' . $category->getData('url_key'), (string) $category->getData('updated_at'), 'category', $storeId);
        }
        foreach ($this->authorCollectionFactory->create()->addFieldToFilter('is_active', 1) as $author) {
            if (isset($usedAuthors[(int) $author->getId()])) {
                $items[] = $this->item($route . '/author/' . $author->getData('url_key'), (string) $author->getData('updated_at'), 'category', $storeId);
            }
        }

        return $items;
    }

    private function item(string $path, ?string $updatedAt, string $type, int $storeId, ?array $images = null): \Magento\Sitemap\Model\SitemapItemInterface
    {
        return $this->itemFactory->create([
            'url' => $path,
            'updatedAt' => $updatedAt,
            'images' => $images !== null ? new \Magento\Framework\DataObject($images) : null,
            'priority' => $this->config->sitemapPriority($type === 'index' ? 'post' : $type, $storeId),
            'changeFrequency' => $this->config->sitemapChangefreq($type === 'index' ? 'post' : $type, $storeId),
        ]);
    }
}
