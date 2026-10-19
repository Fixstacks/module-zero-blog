<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Zero\Banner\Model\ImageResizer;
use Zero\Blog\Model\Category;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\Page\Lookup;
use Zero\Blog\Model\Page\Posts;
use Zero\Blog\Model\Url;

/**
 * The store view's newest posts for a template outside the blog (a theme's
 * homepage "From the journal"): title, excerpt, link, first category and the
 * featured image cropped to the template's ratio. Nothing when the blog is off.
 */
class LatestPosts implements ArgumentInterface
{
    private const WIDTHS = [480, 800, 1200];

    public function __construct(
        private readonly Posts $posts,
        private readonly Lookup $lookup,
        private readonly Url $url,
        private readonly Config $config,
        private readonly ImageResizer $imageResizer
    ) {
    }

    /**
     * @param int $limit
     * @param int[] $ratio [width, height] of the cards' pictures
     * @return array<int, array{title: string, excerpt: string, url: string, category: string,
     *     image: string, srcset: string, alt: string}>
     */
    public function cards(int $limit = 3, array $ratio = [4, 3]): array
    {
        if (!$this->config->isEnabled()) {
            return [];
        }
        $cards = [];
        foreach ($this->posts->latest($limit) as $post) {
            $path = (string) $post->getData('image');
            $variants = $path !== '' ? $this->imageResizer->getVariants($path, $ratio, self::WIDTHS) : null;
            /** @var Category|null $category */
            $category = $this->lookup->categoriesOf($post->getCategoryIds())[0] ?? null;
            $cards[] = [
                'title' => (string) $post->getData('title'),
                'excerpt' => (string) $post->getData('excerpt'),
                'url' => $this->url->post($post),
                'category' => $category ? (string) $category->getData('title') : '',
                'image' => $variants ? (string) end($variants)['url'] : '',
                'srcset' => $variants ? implode(', ', array_map(static fn ($v) => $v['url'] . ' ' . $v['width'] . 'w', $variants)) : '',
                'alt' => (string) $post->getData('image_alt'),
            ];
        }

        return $cards;
    }

    /** The blog's front page, or '' when the blog is off. */
    public function indexUrl(): string
    {
        return $this->config->isEnabled() ? $this->url->index() : '';
    }
}
