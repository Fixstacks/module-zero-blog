<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Page;

use Magento\Framework\View\Result\Page;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Blog\Model\Author;
use Zero\Blog\Model\Category;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\Post;
use Zero\Blog\Model\Tag;
use Zero\Blog\Model\Url;

/**
 * A blog page's head and trail: title, meta description, canonical URL,
 * robots, breadcrumbs. Open Graph and structured data: Block\Head.
 */
class Meta
{
    public const DESCRIPTION_LENGTH = 160;

    public function __construct(
        private readonly Current $current,
        private readonly Posts $posts,
        private readonly Lookup $lookup,
        private readonly Config $config,
        private readonly Url $url,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function lastPage(): int
    {
        return $this->current->type() === Current::POST ? 1 : $this->posts->lastPage();
    }

    public function apply(Page $page): void
    {
        $page->addDefaultHandle();
        $config = $page->getConfig();
        $config->getTitle()->set($this->title());
        $config->setMetaTitle($this->title());
        $description = $this->description();
        if ($description !== '') {
            $config->setDescription($description);
        }
        $config->addRemotePageAsset($this->canonical(), 'canonical', ['attributes' => ['rel' => 'canonical']]);
        $robots = $this->robots();
        if ($robots !== '') {
            $config->setRobots($robots);
        }
        $config->addBodyClass('zero-blog');

        $breadcrumbs = $page->getLayout()->getBlock('breadcrumbs');
        if ($breadcrumbs) {
            foreach ($this->breadcrumbs() as $name => $crumb) {
                $breadcrumbs->addCrumb($name, ['label' => $crumb['label'], 'title' => $crumb['label'], 'link' => $crumb['link']]);
            }
        }
    }

    /** The visible heading (h1) of the page. */
    public function heading(): string
    {
        $entity = $this->current->entity();

        return match (true) {
            $entity instanceof Post, $entity instanceof Category, $entity instanceof Tag => (string) $entity->getData('title'),
            $entity instanceof Author => (string) $entity->getData('name'),
            default => $this->config->title($this->storeId()),
        };
    }

    public function title(): string
    {
        $entity = $this->current->entity();
        $title = $entity !== null && (string) $entity->getData('meta_title') !== ''
            ? (string) $entity->getData('meta_title')
            : ($entity === null && $this->config->metaTitle($this->storeId()) !== '' ? $this->config->metaTitle($this->storeId()) : $this->heading());
        if ($entity instanceof Category || $entity instanceof Tag || $entity instanceof Author) {
            $title .= ' | ' . $this->config->title($this->storeId());
        }

        return $this->current->page() > 1 ? (string) __('%1 - Page %2', $title, $this->current->page()) : $title;
    }

    public function description(): string
    {
        $entity = $this->current->entity();
        $text = match (true) {
            $entity === null => $this->config->metaDescription($this->storeId()) ?: $this->config->introduction($this->storeId()),
            (string) $entity->getData('meta_description') !== '' => (string) $entity->getData('meta_description'),
            $entity instanceof Post => (string) $entity->getData('excerpt'),
            $entity instanceof Author => (string) $entity->getData('bio'),
            default => (string) $entity->getData('description'),
        };

        return $this->shorten($text, self::DESCRIPTION_LENGTH);
    }

    public function canonical(): string
    {
        $entity = $this->current->entity();
        $page = $this->current->page();

        return match (true) {
            $entity instanceof Post => (string) $entity->getData('canonical_url') ?: $this->url->post($entity),
            $entity instanceof Category => $this->url->category($entity, null, $page),
            $entity instanceof Tag => $this->url->tag($entity, null, $page),
            $entity instanceof Author => $this->url->author($entity, null, $page),
            default => $this->url->index(null, $page),
        };
    }

    public function robots(): string
    {
        $entity = $this->current->entity();

        return $entity instanceof Post ? (string) $entity->getData('meta_robots') : '';
    }

    /**
     * Home > Blog > [Category >] Post.
     *
     * @return array<string, array{label: string, link: string}>
     */
    public function breadcrumbs(): array
    {
        $entity = $this->current->entity();
        $crumbs = ['home' => ['label' => (string) __('Home'), 'link' => $this->storeManager->getStore()->getBaseUrl()]];
        $blog = $this->config->title($this->storeId());
        if ($entity === null) {
            return $crumbs + ['blog' => ['label' => $blog, 'link' => '']];
        }
        $crumbs['blog'] = ['label' => $blog, 'link' => $this->url->index()];
        if ($entity instanceof Post) {
            $category = $this->lookup->categoriesOf($entity->getCategoryIds())[0] ?? null;
            if ($category) {
                $crumbs['blog_category'] = ['label' => (string) $category->getData('title'), 'link' => $this->url->category($category)];
            }
        }
        $crumbs['blog_current'] = ['label' => $this->heading(), 'link' => ''];

        return $crumbs;
    }

    public function shorten(string $text, int $length): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > $length * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,.;:") . '…';
    }

    private function storeId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }
}
