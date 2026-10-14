<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Plugin;

use Zero\Blog\Model\Post;
use Zero\Blog\Model\ResourceModel\Post as PostResource;
use Zero\Cms\Model\Content\Repository;

/**
 * When a post's body is saved in the Zero CMS editor, its reading time
 * (for lists and the post header) is counted again from the text: every
 * text value of every component, at 220 words a minute.
 */
class UpdateReadingTime
{
    private const WORDS_PER_MINUTE = 220;

    public function __construct(private readonly PostResource $postResource)
    {
    }

    /**
     * @param Repository $subject
     * @param array $result What was saved
     * @param string $type
     * @param int $entityId
     * @param int $storeId
     * @return array
     */
    public function afterSave(Repository $subject, array $result, string $type, int $entityId, int $storeId): array
    {
        if ($type === Post::CONTENT_TYPE && $storeId === 0) {
            $this->postResource->getConnection()->update(
                $this->postResource->getMainTable(),
                ['reading_time' => self::minutes($result)],
                ['post_id = ?' => $entityId]
            );
        }

        return $result;
    }

    public static function minutes(array $content): int
    {
        $words = 0;
        array_walk_recursive($content, static function ($value, $key) use (&$words) {
            if (is_string($value) && !in_array($key, ['type', 'uid', 'path', 'url', 'source'], true)) {
                $words += count(preg_split('/\s+/u', trim(strip_tags($value)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
            }
        });

        return max(1, (int) round($words / self::WORDS_PER_MINUTE));
    }
}
