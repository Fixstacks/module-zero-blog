<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Import\Source;

/**
 * Magefan Blog (magefan_blog_* tables). Authors are admin users in its free
 * edition (magefan_blog_author in the paid ones). Old URLs: blog/post/{key}
 * unless --old-pattern says otherwise.
 */
class Magefan extends AbstractDatabase
{
    public function defaultOldPattern(): string
    {
        return 'blog/post/{key}';
    }

    public function read(array $options): array
    {
        $this->requireTable('magefan_blog_post');
        $pattern = (string) ($options['old_pattern'] ?? $this->defaultOldPattern());

        $authors = [];
        foreach ($this->rows('magefan_blog_author') as $row) {
            $authors[(int) $row['author_id']] = [
                'name' => trim($this->pick($row, ['title', 'name']) ?: $this->pick($row, ['firstname']) . ' ' . $this->pick($row, ['lastname'])),
                'url_key' => (string) $this->pick($row, ['identifier', 'url_key']),
                'bio' => strip_tags((string) $this->pick($row, ['content', 'short_content'])),
            ];
        }
        if (!$authors) {
            foreach ($this->rows('admin_user') as $row) {
                $authors[(int) $row['user_id']] = ['name' => trim($row['firstname'] . ' ' . $row['lastname']), 'url_key' => '', 'bio' => ''];
            }
        }
        $categories = [];
        foreach ($this->rows('magefan_blog_category') as $row) {
            $categories[(int) $row['category_id']] = [
                'title' => (string) $row['title'],
                'url_key' => (string) $this->pick($row, ['identifier', 'url_key']),
                'description' => strip_tags($this->directives((string) $this->pick($row, ['content']))),
                'meta_title' => (string) $this->pick($row, ['meta_title']),
                'meta_description' => (string) $this->pick($row, ['meta_description']),
            ];
        }
        $tags = [];
        foreach ($this->rows('magefan_blog_tag') as $row) {
            $tags[(int) $row['tag_id']] = ['title' => (string) $row['title'], 'url_key' => (string) $this->pick($row, ['identifier', 'url_key'])];
        }
        $postCategories = $this->links('magefan_blog_post_category', 'post_id', 'category_id');
        $postTags = $this->links('magefan_blog_post_tag', 'post_id', 'tag_id');
        $postStores = $this->links('magefan_blog_post_store', 'post_id', 'store_id');

        $posts = [];
        foreach ($this->rows('magefan_blog_post') as $row) {
            $id = (int) $row['post_id'];
            $key = (string) $this->pick($row, ['identifier', 'url_key']);
            $image = (string) $this->pick($row, ['featured_img', 'og_img']);
            $posts[] = [
                'title' => (string) $row['title'],
                'url_key' => $key,
                'html' => $this->directives((string) $this->pick($row, ['content'])),
                'excerpt' => trim(strip_tags($this->directives((string) $this->pick($row, ['short_content', 'meta_description'])))),
                'published_at' => $this->date($this->pick($row, ['publish_time', 'creation_time'])),
                'is_active' => (int) $this->pick($row, ['is_active'], 0),
                'author' => (int) $this->pick($row, ['author_id'], 0),
                'categories' => $postCategories[$id] ?? [],
                'tags' => $postTags[$id] ?? [],
                'stores' => $this->stores($postStores[$id] ?? []),
                'image' => $image !== '' ? '/' . ltrim($image, '/') : '',
                'image_alt' => (string) $this->pick($row, ['featured_img_alt']),
                'meta_title' => (string) $this->pick($row, ['meta_title']),
                'meta_description' => (string) $this->pick($row, ['meta_description']),
                'old_paths' => $this->oldPaths($pattern, $key),
            ];
        }

        return ['authors' => $authors, 'categories' => $categories, 'tags' => $tags, 'posts' => $posts];
    }
}
