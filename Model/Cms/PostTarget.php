<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Cms;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Zero\Blog\Model\PostFactory;
use Zero\Blog\Model\ResourceModel\Post as PostResource;
use Zero\Blog\Model\Url;
use Zero\Cms\Model\Content\Target\TypeInterface;

/**
 * Blog posts in the Zero CMS editor: the body of a post is its Zero CMS
 * content (type zero_blog_post).
 */
class PostTarget implements TypeInterface
{
    public function __construct(
        private readonly PostFactory $postFactory,
        private readonly PostResource $postResource,
        private readonly Url $url,
        private readonly UrlInterface $backendUrl
    ) {
    }

    public function load(int $id): object
    {
        $post = $this->postFactory->create();
        $this->postResource->load($post, $id);
        if (!$post->getId()) {
            throw new NoSuchEntityException(__('This post no longer exists.'));
        }

        return $post;
    }

    public function title(object $entity): string
    {
        return (string) $entity->getData('title');
    }

    public function identifier(object $entity): string
    {
        return (string) $entity->getData('url_key');
    }

    public function storefrontUrl(object $entity, StoreInterface $store): string
    {
        return $entity->isVisible() ? $this->url->post($entity, (int) $store->getId()) : '';
    }

    public function backUrl(int $id): string
    {
        return $this->backendUrl->getUrl('zero_blog/post/edit', ['post_id' => $id]);
    }

    public function menu(): string
    {
        return 'Zero_Blog::posts';
    }
}
