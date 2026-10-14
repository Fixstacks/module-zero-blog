<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Block;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use Zero\Blog\Model\Author;
use Zero\Blog\Model\Category;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\Page\Current;
use Zero\Blog\Model\Page\Meta;
use Zero\Blog\Model\Post;
use Zero\Blog\Model\Tag;
use Zero\Blog\ViewModel\Blog;
use Zero\Core\Model\Social\Config as SocialConfig;

/**
 * A blog page's Open Graph/Twitter tags (unless off in Zero > Theme > Sharing
 * in Social Media), RSS link and JSON-LD (BlogPosting, Blog, CollectionPage,
 * ProfilePage, BreadcrumbList), plus its page cache tags.
 */
class Head extends Template implements IdentityInterface
{
    public function __construct(
        Context $context,
        private readonly Current $current,
        private readonly Meta $meta,
        private readonly Config $config,
        private readonly Blog $blog,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SocialConfig $socialConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string[]
     */
    public function getIdentities()
    {
        $entity = $this->current->entity();

        return $entity instanceof IdentityInterface ? $entity->getIdentities() : [Post::LIST_TAG];
    }

    /**
     * @return array<string, string> property => content (og:* and article:*)
     */
    public function openGraph(): array
    {
        if (!$this->socialConfig->isEnabled()) {
            return [];
        }
        $entity = $this->current->entity();
        $tags = [
            'og:site_name' => $this->siteName(),
            'og:type' => $entity instanceof Post ? 'article' : ($entity instanceof Author ? 'profile' : 'website'),
            'og:title' => $this->meta->title(),
            'og:description' => $this->meta->description(),
            'og:url' => $this->meta->canonical(),
            'og:locale' => (string) $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE),
        ];
        $image = $this->image();
        if ($image !== '') {
            $tags['og:image'] = $image;
            if ($entity instanceof Post && (string) $entity->getData('image_alt') !== '') {
                $tags['og:image:alt'] = (string) $entity->getData('image_alt');
            }
        }
        if ($entity instanceof Post) {
            $tags['article:published_time'] = $this->blog->isoDate((string) $entity->getData('published_at'));
            $tags['article:modified_time'] = $this->blog->isoDate((string) $entity->getData('updated_at'));
            $author = $this->blog->author($entity);
            if ($author) {
                $tags['article:author'] = $this->blog->authorUrl($author);
            }
            $section = $this->blog->categories($entity)[0]['title'] ?? '';
            if ($section !== '') {
                $tags['article:section'] = $section;
            }
        }

        return array_filter($tags, static fn ($v) => $v !== '');
    }

    /**
     * article:tag repeats, one per tag.
     *
     * @return string[]
     */
    public function articleTags(): array
    {
        $entity = $this->current->entity();

        return $entity instanceof Post && $this->socialConfig->isEnabled() ? array_column($this->blog->tags($entity), 'title') : [];
    }

    /**
     * @return array<string, string> name => content
     */
    public function twitter(): array
    {
        if (!$this->socialConfig->isEnabled()) {
            return [];
        }

        return array_filter([
            'twitter:card' => $this->image() !== '' ? 'summary_large_image' : 'summary',
            'twitter:site' => $this->socialConfig->twitterSite(),
            'twitter:title' => $this->meta->title(),
            'twitter:description' => $this->meta->description(),
            'twitter:image' => $this->image(),
        ], static fn ($v) => $v !== '');
    }

    public function rssUrl(): string
    {
        return $this->blog->rssUrl();
    }

    public function rssTitle(): string
    {
        return $this->siteName() . ' - ' . $this->config->title();
    }

    /**
     * The page's JSON-LD graph.
     */
    public function jsonLd(): string
    {
        $entity = $this->current->entity();
        $graph = [];
        $url = $this->meta->canonical();

        if ($entity instanceof Post) {
            $graph[] = $this->blogPosting($entity, $url);
        } elseif ($entity instanceof Author) {
            $graph[] = [
                '@type' => 'ProfilePage',
                '@id' => $url,
                'url' => $url,
                'name' => $this->meta->title(),
                'mainEntity' => $this->person($entity),
            ];
        } elseif ($entity instanceof Category || $entity instanceof Tag) {
            $graph[] = [
                '@type' => 'CollectionPage',
                '@id' => $url,
                'url' => $url,
                'name' => $this->meta->heading(),
                'description' => $this->meta->description(),
                'hasPart' => $this->postList(),
            ];
        } else {
            $graph[] = [
                '@type' => 'Blog',
                '@id' => $this->blog->indexUrl(),
                'url' => $url,
                'name' => $this->meta->heading(),
                'description' => $this->meta->description(),
                'publisher' => $this->publisher(),
                'blogPost' => $this->postList(),
            ];
        }
        $graph[] = $this->breadcrumbList();

        return (string) json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_map([$this, 'clean'], $graph)],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    private function blogPosting(Post $post, string $url): array
    {
        $author = $this->blog->author($post);
        $body = $this->blog->body($post, $this->getLayout());
        $comments = $this->blog->commentsEnabled($post) ? count($this->blog->comments($post)) : 0;

        return [
            '@type' => 'BlogPosting',
            '@id' => $url . '#article',
            'mainEntityOfPage' => $url,
            'url' => $url,
            'headline' => mb_substr((string) $post->getData('title'), 0, 110),
            'description' => $this->meta->description(),
            'image' => $this->image() !== '' ? [$this->image()] : null,
            'datePublished' => $this->blog->isoDate((string) $post->getData('published_at')),
            'dateModified' => $this->blog->isoDate((string) $post->getData('updated_at')),
            'author' => $author ? $this->person($author) : $this->publisher(),
            'publisher' => $this->publisher(),
            'articleSection' => array_column($this->blog->categories($post), 'title') ?: null,
            'keywords' => implode(', ', array_column($this->blog->tags($post), 'title')) ?: null,
            'wordCount' => count(preg_split('/\s+/u', trim(strip_tags($body)), -1, PREG_SPLIT_NO_EMPTY) ?: []),
            'timeRequired' => 'PT' . $this->blog->readingTime($post) . 'M',
            'commentCount' => $comments ?: null,
            'inLanguage' => str_replace('_', '-', (string) $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE)),
        ];
    }

    private function person(Author $author): array
    {
        return [
            '@type' => 'Person',
            'name' => (string) $author->getData('name'),
            'url' => $this->blog->authorUrl($author),
            'jobTitle' => (string) $author->getData('job_title') ?: null,
            'description' => $this->meta->shorten((string) $author->getData('bio'), 300) ?: null,
            'image' => $this->blog->imageUrl((string) $author->getData('image')) ?: null,
            'sameAs' => array_values($this->blog->authorLinks($author)) ?: null,
        ];
    }

    private function publisher(): array
    {
        $logo = $this->config->publisherLogo();
        if ($logo === '') {
            $src = (string) $this->scopeConfig->getValue('design/header/logo_src', ScopeInterface::SCOPE_STORE);
            $logo = $src !== '' ? $this->_storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'logo/' . $src : '';
        }

        return [
            '@type' => 'Organization',
            'name' => $this->siteName(),
            'url' => $this->_storeManager->getStore()->getBaseUrl(),
            'logo' => $logo !== '' ? ['@type' => 'ImageObject', 'url' => $logo] : null,
        ];
    }

    /**
     * The listed posts, as short BlogPosting entries.
     */
    private function postList(): array
    {
        return array_map(fn (Post $post) => [
            '@type' => 'BlogPosting',
            'headline' => (string) $post->getData('title'),
            'url' => $this->blog->postUrl($post),
            'datePublished' => $this->blog->isoDate((string) $post->getData('published_at')),
        ], $this->blog->posts());
    }

    private function breadcrumbList(): array
    {
        $items = [];
        $position = 1;
        foreach ($this->meta->breadcrumbs() as $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $crumb['label'],
                'item' => $crumb['link'] ?: $this->meta->canonical(),
            ];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    private function image(): string
    {
        $entity = $this->current->entity();
        if ($entity instanceof Post || $entity instanceof Author) {
            return $this->blog->imageUrl((string) $entity->getData('image'));
        }
        $first = $this->blog->posts()[0] ?? null;
        $image = $first ? $this->blog->imageUrl((string) $first->getData('image')) : '';

        return $image ?: $this->socialConfig->defaultImage();
    }

    private function siteName(): string
    {
        return (string) ($this->scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE)
            ?: $this->_storeManager->getStore()->getFrontendName());
    }

    /**
     * Drops empty values (schema.org validators flag them), recursively.
     */
    private function clean(array $data): array
    {
        $list = array_is_list($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->clean($value);
            }
            if ($value === null || $value === '' || $value === []) {
                unset($data[$key]);
            }
        }

        return $list ? array_values($data) : $data;
    }
}
