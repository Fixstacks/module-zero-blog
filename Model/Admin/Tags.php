<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Admin;

use Zero\Blog\Model\ResourceModel\Tag as TagResource;
use Zero\Blog\Model\TagFactory;
use Zero\Blog\Model\UrlKey;

/**
 * A post's tags as typed in its form ("running, gear, how to"): existing
 * tags by title or URL key, new ones created.
 */
class Tags
{
    public function __construct(
        private readonly TagResource $tagResource,
        private readonly TagFactory $tagFactory,
        private readonly UrlKey $urlKey
    ) {
    }

    /**
     * @return int[]
     */
    public function idsFor(string $titles): array
    {
        $ids = [];
        $connection = $this->tagResource->getConnection();
        foreach (array_unique(array_filter(array_map('trim', explode(',', $titles)))) as $title) {
            $title = mb_substr($title, 0, 255);
            $key = $this->urlKey->fromText($title);
            if ($key === '') {
                continue;
            }
            $id = $connection->fetchOne(
                $connection->select()->from($this->tagResource->getMainTable(), ['tag_id'])
                    ->where('url_key = ? OR title = ?', $key, $title)->limit(1)
            );
            if ($id === false) {
                $tag = $this->tagFactory->create()->setData(['title' => $title, 'url_key' => $key]);
                $this->tagResource->save($tag);
                $id = $tag->getId();
            }
            $ids[] = (int) $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param int[] $ids
     */
    public function titlesOf(array $ids): string
    {
        if (!$ids) {
            return '';
        }
        $connection = $this->tagResource->getConnection();
        $titles = $connection->fetchPairs(
            $connection->select()->from($this->tagResource->getMainTable(), ['tag_id', 'title'])->where('tag_id IN (?)', $ids)
        );

        return implode(', ', array_filter(array_map(static fn (int $id) => $titles[$id] ?? null, $ids)));
    }
}
