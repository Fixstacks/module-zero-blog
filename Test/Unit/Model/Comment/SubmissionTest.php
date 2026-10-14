<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Test\Unit\Model\Comment;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validator\EmailAddress;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Zero\Blog\Model\Comment;
use Zero\Blog\Model\Comment\Submission;
use Zero\Blog\Model\CommentFactory;
use Zero\Blog\Model\Post;
use Zero\Blog\Model\ResourceModel\Comment as CommentResource;

#[AllowMockObjectsWithoutExpectations]
class SubmissionTest extends TestCase
{
    /** @var array<int, array> */
    private array $saved = [];
    /** @var array<string, string> */
    private array $cache = [];

    private function submission(): Submission
    {
        $factory = $this->createStub(CommentFactory::class);
        $factory->method('create')->willReturnCallback(function () {
            $comment = $this->getMockBuilder(Comment::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
            return $comment;
        });
        $resource = $this->createStub(CommentResource::class);
        $resource->method('save')->willReturnCallback(function (Comment $comment) use ($resource) {
            $this->saved[] = $comment->getData();
            return $resource;
        });
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn ($key) => $this->cache[$key] ?? false);
        $cache->method('save')->willReturnCallback(function ($value, $key) {
            $this->cache[$key] = $value;
            return true;
        });
        $email = $this->createStub(EmailAddress::class);
        $email->method('isValid')->willReturnCallback(fn ($value) => (bool) filter_var($value, FILTER_VALIDATE_EMAIL));

        return new Submission($factory, $resource, $cache, $email);
    }

    private function post(): Post
    {
        $post = $this->getMockBuilder(Post::class)->disableOriginalConstructor()->onlyMethods(['getId'])->getMock();
        $post->method('getId')->willReturn(5);

        return $post;
    }

    public function testStoredAsPendingWithoutMarkup(): void
    {
        $this->assertTrue($this->submission()->submit($this->post(), 1, [
            'name' => ' <b>Ann</b> ', 'email' => 'ann@example.test', 'comment' => "Nice <script>x</script>post\r\nThanks",
        ], '10.0.0.1'));
        $this->assertSame(['post_id' => 5, 'store_id' => 1, 'customer_id' => null, 'author_name' => 'Ann',
            'author_email' => 'ann@example.test', 'content' => "Nice xpost\nThanks", 'status' => Comment::STATUS_PENDING], $this->saved[0]);
    }

    public function testBotsAndLinkSpamAreDroppedQuietly(): void
    {
        $submission = $this->submission();
        $this->assertFalse($submission->submit($this->post(), 1, ['name' => 'Bot', 'email' => 'b@example.test', 'comment' => 'Hi', 'website' => 'x'], '10.0.0.2'));
        $this->assertFalse($submission->submit($this->post(), 1, [
            'name' => 'Spam', 'email' => 's@example.test', 'comment' => 'https://a.x https://b.x www.c.x https://d.x',
        ], '10.0.0.2'));
        $this->assertSame([], $this->saved);
    }

    public function testFiveCommentsPerTenMinutesPerVisitor(): void
    {
        $submission = $this->submission();
        $input = ['name' => 'Ann', 'email' => 'ann@example.test', 'comment' => 'Hello'];
        for ($i = 0; $i < 5; $i++) {
            $submission->submit($this->post(), 1, $input, '10.0.0.3');
        }
        $submission->submit($this->post(), 1, $input, '10.0.0.4');
        $this->assertCount(6, $this->saved);
        $this->expectException(LocalizedException::class);
        $submission->submit($this->post(), 1, $input, '10.0.0.3');
    }

    public function testInvalidInputIsExplained(): void
    {
        $this->expectException(LocalizedException::class);
        $this->submission()->submit($this->post(), 1, ['name' => 'Ann', 'email' => 'not-an-email', 'comment' => 'Hello'], '10.0.0.5');
    }
}
