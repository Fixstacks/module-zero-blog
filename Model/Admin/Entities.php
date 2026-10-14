<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Admin;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\ObjectManagerInterface;
use Zero\Blog\Model;

/**
 * The blog's admin entities (posts, categories, tags, authors, comments):
 * their classes, id field and labels, and a factory for each. Shared by the
 * generic admin actions in Controller\Adminhtml\AbstractEntity.
 */
class Entities
{
    public const POST = 'post';
    public const CATEGORY = 'category';
    public const TAG = 'tag';
    public const AUTHOR = 'author';
    public const COMMENT = 'comment';

    private const MAP = [
        self::POST => [Model\Post::class, Model\ResourceModel\Post::class, Model\ResourceModel\Post\Collection::class, 'post_id', 'Post', 'Posts'],
        self::CATEGORY => [Model\Category::class, Model\ResourceModel\Category::class, Model\ResourceModel\Category\Collection::class, 'category_id', 'Category', 'Categories'],
        self::TAG => [Model\Tag::class, Model\ResourceModel\Tag::class, Model\ResourceModel\Tag\Collection::class, 'tag_id', 'Tag', 'Tags'],
        self::AUTHOR => [Model\Author::class, Model\ResourceModel\Author::class, Model\ResourceModel\Author\Collection::class, 'author_id', 'Author', 'Authors'],
        self::COMMENT => [Model\Comment::class, Model\ResourceModel\Comment::class, Model\ResourceModel\Comment\Collection::class, 'comment_id', 'Comment', 'Comments'],
    ];

    public function __construct(private readonly ObjectManagerInterface $objectManager)
    {
    }

    public function create(string $entity): AbstractModel
    {
        return $this->objectManager->create(self::MAP[$entity][0]);
    }

    public function resource(string $entity): AbstractDb
    {
        return $this->objectManager->get(self::MAP[$entity][1]);
    }

    public function collection(string $entity): AbstractCollection
    {
        return $this->objectManager->create(self::MAP[$entity][2]);
    }

    public function idField(string $entity): string
    {
        return self::MAP[$entity][3];
    }

    public function label(string $entity): string
    {
        return self::MAP[$entity][4];
    }

    public function pluralLabel(string $entity): string
    {
        return self::MAP[$entity][5];
    }

    /**
     * Loads one, or returns a new empty model for id 0.
     */
    public function load(string $entity, int $id): AbstractModel
    {
        $model = $this->create($entity);
        if ($id) {
            $this->resource($entity)->load($model, $id);
        }

        return $model;
    }
}
