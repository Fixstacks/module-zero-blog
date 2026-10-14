<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Page;

use Zero\Blog\Model\Author;
use Zero\Blog\Model\Category;
use Zero\Blog\Model\ResourceModel\Author\CollectionFactory as AuthorCollectionFactory;
use Zero\Blog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Zero\Blog\Model\ResourceModel\Tag\CollectionFactory as TagCollectionFactory;
use Zero\Blog\Model\Tag;

/**
 * Authors, categories and tags by id, each list read once per request
 * (a page shows a dozen posts, each with its author and categories).
 */
class Lookup
{
    /** @var array<int, Author>|null */
    private ?array $authors = null;
    /** @var array<int, Category>|null */
    private ?array $categories = null;
    /** @var array<int, Tag>|null */
    private ?array $tags = null;

    public function __construct(
        private readonly AuthorCollectionFactory $authorCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly TagCollectionFactory $tagCollectionFactory
    ) {
    }

    public function author(int $id): ?Author
    {
        if ($this->authors === null) {
            $this->authors = $this->authorCollectionFactory->create()->addFieldToFilter('is_active', 1)->getItems();
        }

        return $this->authors[$id] ?? null;
    }

    /**
     * Enabled categories, by position.
     *
     * @return array<int, Category>
     */
    public function categories(): array
    {
        if ($this->categories === null) {
            $this->categories = $this->categoryCollectionFactory->create()
                ->addFieldToFilter('is_active', 1)
                ->setOrder('position', 'ASC')
                ->setOrder('title', 'ASC')
                ->getItems();
        }

        return $this->categories;
    }

    /**
     * @param int[] $ids
     * @return Category[]
     */
    public function categoriesOf(array $ids): array
    {
        return array_values(array_filter(array_map(fn (int $id) => $this->categories()[$id] ?? null, $ids)));
    }

    /**
     * @param int[] $ids
     * @return Tag[]
     */
    public function tagsOf(array $ids): array
    {
        if ($this->tags === null) {
            $this->tags = $this->tagCollectionFactory->create()->setOrder('title', 'ASC')->getItems();
        }

        return array_values(array_filter(array_map(fn (int $id) => $this->tags[$id] ?? null, $ids)));
    }
}
