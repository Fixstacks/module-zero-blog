<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Import;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\ClientFactory;
use Psr\Log\LoggerInterface;
use Zero\Blog\Model\Author;
use Zero\Blog\Model\Category;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\Post;
use Zero\Blog\Model\Redirects;
use Zero\Blog\Model\ResourceModel\Post as PostResource;
use Zero\Blog\Model\Tag;
use Zero\Blog\Model\UrlKey;
use Zero\Blog\Model\Admin\Entities;
use Zero\Cms\Model\Content\Repository;

/**
 * Writes what a Model\Import\SourceInterface read: authors, categories and
 * tags (an existing one with the same URL key is reused), then posts. A
 * post's body becomes one Zero CMS "HTML" component (cleaned on save), its
 * featured image is copied into media/zero_blog/import, and each of its old
 * addresses redirects (301) to the new one. Posts whose URL key exists are
 * skipped, so running it again only adds what is new.
 */
class Importer
{
    private const IMAGE_DIR = 'zero_blog/import';

    /** @var callable|null */
    private $log = null;

    public function __construct(
        private readonly Entities $entities,
        private readonly UrlKey $urlKey,
        private readonly PostResource $postResource,
        private readonly Repository $contentRepository,
        private readonly Redirects $redirects,
        private readonly Config $config,
        private readonly Filesystem $filesystem,
        private readonly ClientFactory $httpClientFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array{authors: array, categories: array, tags: array, posts: array} $data
     * @param array{dry_run?: bool, images?: bool, redirects?: bool} $options
     * @param callable|null $log fn(string $line)
     * @return array{posts: int, skipped: int, authors: int, categories: int, tags: int, redirects: int}
     */
    public function import(array $data, array $options = [], ?callable $log = null): array
    {
        $this->log = $log;
        $dryRun = !empty($options['dry_run']);
        $stats = ['posts' => 0, 'skipped' => 0, 'authors' => 0, 'categories' => 0, 'tags' => 0, 'redirects' => 0];

        // Only authors with posts (Magefan's are all admin users).
        $used = array_flip(array_map('strval', array_column($data['posts'] ?? [], 'author')));
        $authorRows = array_filter($data['authors'] ?? [], static fn ($id) => isset($used[(string) $id]), ARRAY_FILTER_USE_KEY);
        $authors = $this->terms(Entities::AUTHOR, 'name', $authorRows, $dryRun, $stats['authors']);
        $categories = $this->terms(Entities::CATEGORY, 'title', $data['categories'] ?? [], $dryRun, $stats['categories']);
        $tags = $this->terms(Entities::TAG, 'title', $data['tags'] ?? [], $dryRun, $stats['tags']);
        $route = $this->config->route();

        foreach ($data['posts'] ?? [] as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $key = $this->urlKey->isValid((string) ($row['url_key'] ?? '')) ? (string) $row['url_key'] : $this->urlKey->fromText($title);
            if ($title === '' || $key === '') {
                continue;
            }
            if (in_array($key, UrlKey::RESERVED, true)) {
                $key .= '-post';
            }
            $stores = array_map('intval', $row['stores'] ?: [0]);
            if ($this->postResource->urlKeyTakenBy($key, $stores, 0) !== null) {
                $stats['skipped']++;
                $this->say('skip (exists): ' . $key);
                continue;
            }
            $this->say(($dryRun ? 'would import: ' : 'import: ') . $key);
            if ($dryRun) {
                $stats['posts']++;
                continue;
            }
            /** @var Post $post */
            $post = $this->entities->create(Entities::POST);
            $post->setData([
                'title' => mb_substr($title, 0, 255),
                'url_key' => $key,
                'excerpt' => mb_substr((string) ($row['excerpt'] ?? ''), 0, 1000) ?: null,
                'image' => !empty($options['images']) || !str_starts_with((string) ($row['image'] ?? ''), 'http')
                    ? $this->image((string) ($row['image'] ?? '')) : null,
                'image_alt' => mb_substr((string) ($row['image_alt'] ?? ''), 0, 255) ?: null,
                'author_id' => $authors[$row['author'] ?? ''] ?? null,
                'is_active' => (int) ($row['is_active'] ?? 0),
                'published_at' => (string) ($row['published_at'] ?? '') ?: gmdate('Y-m-d H:i:s'),
                'allow_comments' => 1,
                'meta_title' => mb_substr((string) ($row['meta_title'] ?? ''), 0, 255) ?: null,
                'meta_description' => mb_substr((string) ($row['meta_description'] ?? ''), 0, 500) ?: null,
                'store_ids' => $stores,
                'category_ids' => array_values(array_filter(array_map(static fn ($id) => $categories[$id] ?? null, (array) ($row['categories'] ?? [])))),
                'tag_ids' => array_values(array_filter(array_map(static fn ($id) => $tags[$id] ?? null, (array) ($row['tags'] ?? [])))),
            ]);
            $this->postResource->save($post);
            $html = (string) ($row['html'] ?? '');
            if (trim($html) !== '') {
                $this->contentRepository->save(Post::CONTENT_TYPE, (int) $post->getId(), 0, [
                    'components' => [['type' => 'html', 'data' => ['html' => $html]]],
                ], 'import', 'Imported');
            }
            if (!array_key_exists('redirects', $options) || $options['redirects']) {
                foreach ((array) ($row['old_paths'] ?? []) as $old) {
                    $old = trim(rawurldecode((string) $old), '/');
                    if ($old !== '' && $old !== $route . '/' . $key) {
                        $this->redirects->add($old, $route . '/' . $key, $stores, (int) $post->getId());
                        $stats['redirects']++;
                    }
                }
            }
            $stats['posts']++;
        }

        return $stats;
    }

    /**
     * Source id => our id, creating what isn't there yet (by URL key).
     *
     * @return array<string|int, int>
     */
    private function terms(string $entity, string $nameField, array $rows, bool $dryRun, int &$created): array
    {
        $ids = [];
        $resource = $this->entities->resource($entity);
        $connection = $resource->getConnection();
        foreach ($rows as $sourceId => $row) {
            $name = trim((string) ($row['name'] ?? $row['title'] ?? ''));
            $key = (string) ($row['url_key'] ?? '');
            $key = $this->urlKey->isValid($key) ? $key : $this->urlKey->fromText($name);
            if ($name === '' || $key === '') {
                continue;
            }
            $existing = $connection->fetchOne(
                $connection->select()->from($resource->getMainTable(), [$this->entities->idField($entity)])->where('url_key = ?', $key)
            );
            if ($existing !== false) {
                $ids[$sourceId] = (int) $existing;
                continue;
            }
            $created++;
            if ($dryRun) {
                $ids[$sourceId] = 0;
                continue;
            }
            $model = $this->entities->create($entity);
            $values = [$nameField => mb_substr($name, 0, 255), 'url_key' => $key];
            foreach (['description', 'meta_title', 'meta_description', 'bio'] as $field) {
                if (!empty($row[$field])) {
                    $values[$field] = (string) $row[$field];
                }
            }
            if ($model instanceof Category || $model instanceof Author) {
                $values['is_active'] = 1;
            }
            $model->setData($values);
            $resource->save($model);
            $ids[$sourceId] = (int) $model->getId();
        }

        return $ids;
    }

    /**
     * Copies a featured image into media/zero_blog/import: from a URL, or
     * from another place in pub/media. Returns the media path, or null.
     */
    private function image(string $source): ?string
    {
        if ($source === '') {
            return null;
        }
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', basename((string) parse_url($source, PHP_URL_PATH))) ?: 'image.jpg';
        if (!preg_match('/\.(jpe?g|png|gif)$/i', $name)) {
            return null; // the storefront resizes these formats only
        }
        $target = self::IMAGE_DIR . '/' . $name;
        try {
            if (!$media->isFile($target)) {
                if (preg_match('#^https?://#i', $source)) {
                    $client = $this->httpClientFactory->create();
                    $client->setTimeout(20);
                    $client->get($source);
                    if ($client->getStatus() !== 200 || $client->getBody() === '') {
                        $this->say('image not downloaded: ' . $source);

                        return null;
                    }
                    $media->writeFile($target, $client->getBody());
                } elseif ($media->isFile(ltrim($source, '/')) && !str_contains($source, '..')) {
                    $media->copyFile(ltrim($source, '/'), $target);
                } else {
                    $this->say('image not found: ' . $source);

                    return null;
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('Zero_Blog import: image ' . $source, ['exception' => $e]);

            return null;
        }

        return '/' . $target;
    }

    private function say(string $line): void
    {
        if ($this->log) {
            ($this->log)($line);
        }
    }
}
