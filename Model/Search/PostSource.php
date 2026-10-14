<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Search;

use Zero\Blog\Model\Config;
use Zero\Blog\Model\ResourceModel\Post\CollectionFactory;
use Zero\Blog\Model\Url;
use Zero\Search\Model\SourceInterface;
use Zero\Search\Model\Sources;

/**
 * For Zero_Search: the store view's published posts whose title has every
 * word of the query, newest first.
 */
class PostSource implements SourceInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Config $config,
        private readonly Url $url
    ) {
    }

    public function title(): string
    {
        return (string) __('Blog');
    }

    public function find(string $query, int $storeId, int $limit): array
    {
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }
        $collection = $this->collectionFactory->create()->addVisibleFilter($storeId)->setPageSize($limit);
        foreach (Sources::likePatterns($query) as $pattern) {
            $collection->addFieldToFilter('main_table.title', ['like' => $pattern]);
        }
        $items = [];
        foreach ($collection as $post) {
            $items[] = ['name' => (string) $post->getData('title'), 'url' => $this->url->post($post, $storeId)];
        }

        return $items;
    }
}
