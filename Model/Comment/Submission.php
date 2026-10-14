<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Comment;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validator\EmailAddress;
use Zero\Blog\Model\Comment;
use Zero\Blog\Model\CommentFactory;
use Zero\Blog\Model\Post;
use Zero\Blog\Model\ResourceModel\Comment as CommentResource;

/**
 * A reader's comment: checked, then stored as pending for moderation.
 *
 * Spam: a bot that fills the hidden "website" field gets a normal-looking
 * answer and nothing is stored; one visitor (by IP hash) can post 5 times in
 * 10 minutes; more than 3 links means rejected outright.
 */
class Submission
{
    public const MAX_LENGTH = 3000;
    private const RATE_LIMIT = 5;
    private const RATE_WINDOW = 600;
    private const MAX_LINKS = 3;

    public function __construct(
        private readonly CommentFactory $commentFactory,
        private readonly CommentResource $commentResource,
        private readonly CacheInterface $cache,
        private readonly EmailAddress $emailValidator
    ) {
    }

    /**
     * @param array{name?: mixed, email?: mixed, comment?: mixed, website?: mixed} $input
     * @return bool false: dropped as spam (the reader is told the same as for a stored one)
     * @throws LocalizedException The reader can fix it.
     */
    public function submit(Post $post, int $storeId, array $input, string $clientIp, ?int $customerId = null): bool
    {
        if (trim((string) ($input['website'] ?? '')) !== '') {
            return false;
        }
        $name = trim(strip_tags((string) ($input['name'] ?? '')));
        $email = trim((string) ($input['email'] ?? ''));
        $text = trim(str_replace("\r\n", "\n", strip_tags((string) ($input['comment'] ?? ''))));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new LocalizedException(__('Please enter your name (up to 100 characters).'));
        }
        if (!$this->emailValidator->isValid($email)) {
            throw new LocalizedException(__('Please enter a valid email address.'));
        }
        if (mb_strlen($text) < 2 || mb_strlen($text) > self::MAX_LENGTH) {
            throw new LocalizedException(__('Please write a comment of up to %1 characters.', self::MAX_LENGTH));
        }
        if (preg_match_all('#https?://|www\.#i', $text) > self::MAX_LINKS) {
            return false;
        }
        $key = 'zero_blog_comment_rate_' . hash('sha256', $clientIp);
        $count = (int) $this->cache->load($key);
        if ($count >= self::RATE_LIMIT) {
            throw new LocalizedException(__('Too many comments in a short time. Please try again in a few minutes.'));
        }
        $this->cache->save((string) ($count + 1), $key, [], self::RATE_WINDOW);

        /** @var Comment $comment */
        $comment = $this->commentFactory->create();
        $comment->setData([
            'post_id' => (int) $post->getId(),
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'author_name' => $name,
            'author_email' => $email,
            'content' => $text,
            'status' => Comment::STATUS_PENDING,
        ]);
        $this->commentResource->save($comment);

        return true;
    }
}
