<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Import\Source;

/**
 * Amasty Blog Pro (amasty_blog_* tables). Its status: 2 published, 3
 * scheduled, other values draft. Store-specific texts (newer versions keep
 * them in *_store tables) are read from All Store Views. Old URLs:
 * blog/{key}.html unless --old-pattern says otherwise.
 */
class Amasty extends AbstractDatabase
{
    private const PUBLISHED = [2, 3];

    public function defaultOldPattern(): string
    {
        return 'blog/{key}.html';
    }

    public function read(array $options): array
    {
        $this->requireTable('amasty_blog_posts');
        $pattern = (string) ($options['old_pattern'] ?? $this->defaultOldPattern());

        $authors = [];
        foreach ($this->rows('amasty_blog_author') as $row) {
            $authors[(int) $row['author_id']] = [
                'name' => (string) $this->pick($row, ['name']),
                'url_key' => (string) $this->pick($row, ['url_key']),
                'bio' => strip_tags((string) $this->pick($row, ['description', 'short_description'])),
            ];
        }
        $categories = [];
        foreach ($this->rows('amasty_blog_categories') as $row) {
            $row = $this->merge($row, $this->storeRow('amasty_blog_categories_store', 'category_id', (int) $row['category_id']));
            $categories[(int) $row['category_id']] = [
                'title' => (string) $this->pick($row, ['name', 'title']),
                'url_key' => (string) $this->pick($row, ['url_key']),
                'description' => strip_tags((string) $this->pick($row, ['description', 'meta_description'])),
                'meta_title' => (string) $this->pick($row, ['meta_title']),
                'meta_description' => (string) $this->pick($row, ['meta_description']),
            ];
        }
        $tags = [];
        foreach ($this->rows('amasty_blog_tags') as $row) {
            $row = $this->merge($row, $this->storeRow('amasty_blog_tags_store', 'tag_id', (int) $row['tag_id']));
            $tags[(int) $row['tag_id']] = ['title' => (string) $this->pick($row, ['name', 'title']), 'url_key' => (string) $this->pick($row, ['url_key'])];
        }
        $postCategories = $this->links('amasty_blog_posts_category', 'post_id', 'category_id');
        $postTags = $this->links('amasty_blog_posts_tag', 'post_id', 'tag_id');
        $postStores = $this->links('amasty_blog_posts_store', 'post_id', 'store_id');

        $posts = [];
        foreach ($this->rows('amasty_blog_posts') as $row) {
            $id = (int) $row['post_id'];
            $row = $this->merge($row, $this->storeRow('amasty_blog_posts_store', 'post_id', $id));
            $key = (string) $this->pick($row, ['url_key']);
            $image = (string) $this->pick($row, ['post_thumbnail', 'list_thumbnail']);
            $posts[] = [
                'title' => (string) $this->pick($row, ['title']),
                'url_key' => $key,
                'html' => $this->directives((string) $this->pick($row, ['full_content', 'content'])),
                'excerpt' => trim(strip_tags($this->directives((string) $this->pick($row, ['short_content'])))),
                'published_at' => $this->date($this->pick($row, ['published_at', 'created_at'])),
                'is_active' => in_array((int) $this->pick($row, ['status'], 0), self::PUBLISHED, true) ? 1 : 0,
                'author' => (int) $this->pick($row, ['author_id'], 0),
                'categories' => $postCategories[$id] ?? [],
                'tags' => $postTags[$id] ?? [],
                'stores' => $this->stores(array_values(array_diff($postStores[$id] ?? [], [0])) ?: []),
                'image' => $image !== '' ? '/amasty/blog/' . ltrim($image, '/') : '',
                'image_alt' => (string) $this->pick($row, ['thumbnail_alt', 'post_thumbnail_alt']),
                'meta_title' => (string) $this->pick($row, ['meta_title']),
                'meta_description' => (string) $this->pick($row, ['meta_description']),
                'old_paths' => $this->oldPaths($pattern, $key),
            ];
        }

        return ['authors' => $authors, 'categories' => $categories, 'tags' => $tags, 'posts' => $posts];
    }

    /**
     * The row's filled values, then the store row's for what is empty.
     */
    private function merge(array $row, array $store): array
    {
        return array_filter($row, static fn ($v) => $v !== null && $v !== '') + $store;
    }

    /**
     * The All Store Views row of a *_store table that holds texts (not just
     * the link to a store view), else [].
     *
     * @return array<string, mixed>
     */
    private function storeRow(string $table, string $idField, int $id): array
    {
        if (!$this->hasTable($table)) {
            return [];
        }
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->resource->getTableName($table))
                ->where($idField . ' = ?', $id)->where('store_id = ?', 0)->limit(1)
        );

        return is_array($row) && count($row) > 2 ? $row : [];
    }
}
