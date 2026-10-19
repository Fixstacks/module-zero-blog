<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\ViewModel;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Escaper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Banner\Model\ImageResizer;
use Zero\Blog\Model\Author;
use Zero\Blog\Model\Category;
use Zero\Blog\Model\Comment;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\Page\Current;
use Zero\Blog\Model\Page\Lookup;
use Zero\Blog\Model\Page\Meta;
use Zero\Blog\Model\Page\Posts;
use Zero\Blog\Model\Post;
use Zero\Blog\Model\ResourceModel\Comment\CollectionFactory as CommentCollectionFactory;
use Zero\Blog\Model\Tag;
use Zero\Blog\Model\Url;
use Zero\Cms\Model\Content\Repository;
use Zero\Cms\Model\Renderer;
use Zero\Cms\ViewModel\Component;

/**
 * What the blog templates show: the page, its posts as cards, a post's body
 * (Zero CMS content), table of contents, related posts, products, comments,
 * responsive images.
 */
class Blog implements ArgumentInterface
{
    private const CARD_WIDTHS = [480, 800, 1200];
    private const WORDS_PER_MINUTE = 220;

    /** @var array<int, string> */
    private array $bodies = [];

    public function __construct(
        private readonly Current $current,
        private readonly Posts $posts,
        private readonly Lookup $lookup,
        private readonly Meta $meta,
        private readonly Config $config,
        private readonly Url $url,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone,
        private readonly ImageResizer $imageResizer,
        private readonly Repository $contentRepository,
        private readonly Renderer $renderer,
        private readonly Component $component,
        private readonly ProductResource $productResource,
        private readonly CommentCollectionFactory $commentCollectionFactory,
        private readonly Escaper $escaper
    ) {
    }

    public function type(): string
    {
        return $this->current->type();
    }

    /**
     * @return Post|Category|Tag|Author|null
     */
    public function entity()
    {
        return $this->current->entity();
    }

    public function heading(): string
    {
        return $this->meta->heading();
    }

    /** The list page's introduction (plain text). */
    public function introduction(): string
    {
        $entity = $this->current->entity();

        return match (true) {
            $entity === null => $this->current->page() === 1 ? $this->config->introduction($this->storeId()) : '',
            $entity instanceof Author => '',
            default => (string) $entity->getData('description'),
        };
    }

    /**
     * @return Post[]
     */
    public function posts(): array
    {
        return array_values($this->posts->page()->getItems());
    }

    public function page(): int
    {
        return $this->current->page();
    }

    /**
     * Page links for the current list: null when it fits on one page.
     *
     * @return array{current: int, last: int, prev: string, next: string, pages: array<int, string>}|null
     */
    public function pagination(): ?array
    {
        $last = $this->posts->lastPage();
        if ($last <= 1) {
            return null;
        }
        $current = $this->current->page();
        $pages = [];
        foreach (range(1, $last) as $n) {
            if ($n === 1 || $n === $last || abs($n - $current) <= 2) {
                $pages[$n] = $this->listUrl($n);
            }
        }

        return [
            'current' => $current,
            'last' => $last,
            'prev' => $current > 1 ? $this->listUrl($current - 1) : '',
            'next' => $current < $last ? $this->listUrl($current + 1) : '',
            'pages' => $pages,
        ];
    }

    /**
     * Enabled categories with a post in this store view, as links, the
     * current one marked.
     *
     * @return array<int, array{title: string, url: string, current: bool}>
     */
    public function categoryNav(): array
    {
        $entity = $this->current->entity();
        // Only categories with a post this store view shows (categories are global).
        $used = $this->posts->usedCategoryIds();
        $nav = [];
        foreach ($this->lookup->categories() as $category) {
            if (!in_array((int) $category->getId(), $used, true)) {
                continue;
            }
            $nav[] = [
                'title' => (string) $category->getData('title'),
                'url' => $this->url->category($category),
                'current' => $entity instanceof Category && (int) $entity->getId() === (int) $category->getId(),
            ];
        }

        return $nav;
    }

    public function indexUrl(): string
    {
        return $this->url->index();
    }

    public function rssUrl(): string
    {
        return $this->config->rssEnabled($this->storeId()) ? $this->url->rss() : '';
    }

    public function postUrl(Post $post): string
    {
        return $this->url->post($post);
    }

    public function author(Post $post): ?Author
    {
        return $post->getData('author_id') ? $this->lookup->author((int) $post->getData('author_id')) : null;
    }

    public function authorUrl(Author $author): string
    {
        return $this->url->author($author);
    }

    /**
     * @return array<int, array{title: string, url: string}>
     */
    public function categories(Post $post): array
    {
        return array_map(fn (Category $c) => ['title' => (string) $c->getData('title'), 'url' => $this->url->category($c)],
            $this->lookup->categoriesOf($post->getCategoryIds()));
    }

    /**
     * @return array<int, array{title: string, url: string}>
     */
    public function tags(Post $post): array
    {
        return array_map(fn (Tag $t) => ['title' => (string) $t->getData('title'), 'url' => $this->url->tag($t)],
            $this->lookup->tagsOf($post->getTagIds()));
    }

    /** ISO 8601 with offset (UTC stored). */
    public function isoDate(string $utc): string
    {
        return $utc !== '' ? gmdate('c', (int) strtotime($utc . ' UTC')) : '';
    }

    /** The date in the store's time zone and locale: "Oct 8, 2026". */
    public function date(string $utc): string
    {
        if ($utc === '') {
            return '';
        }

        return $this->timezone->formatDateTime(
            new \DateTime($utc, new \DateTimeZone('UTC')),
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE
        );
    }

    /**
     * Whether the post was changed a day or more after it went out (and after
     * it was created: an imported, back-dated post isn't "updated").
     */
    public function wasUpdated(Post $post): bool
    {
        $since = max(
            (int) strtotime((string) $post->getData('published_at') . ' UTC'),
            (int) strtotime((string) $post->getData('created_at') . ' UTC')
        );
        $updated = (int) strtotime((string) $post->getData('updated_at') . ' UTC');

        return $since && $updated - $since > 86400;
    }

    public function readingTime(Post $post): int
    {
        return max(1, (int) $post->getData('reading_time'));
    }

    /**
     * The featured image as <picture>: 16:9 copies at several widths, WebP
     * first. `$eager` for the image that is the page's largest paint.
     */
    public function picture(string $path, string $alt, string $sizes, bool $eager = false, string $class = ''): string
    {
        $variants = $path !== '' ? $this->imageResizer->getVariants($path, [16, 9], self::CARD_WIDTHS) : null;
        if (!$variants) {
            return '';
        }

        return $this->pictureHtml($variants, $alt, $sizes, $eager, $class);
    }

    /** A square photo (an author's), at 1x and 2x of $size. */
    public function avatar(string $path, string $alt, int $size, string $class = ''): string
    {
        $variants = $path !== '' ? $this->imageResizer->getVariants($path, [1, 1], [$size, $size * 2]) : null;

        return $variants ? $this->pictureHtml($variants, $alt, $size . 'px', false, $class) : '';
    }

    /** The largest featured image copy, for Open Graph and structured data. */
    public function imageUrl(string $path): string
    {
        $variants = $path !== '' ? $this->imageResizer->getVariants($path, [16, 9], [1200]) : null;

        return $variants ? (string) end($variants)['url'] : '';
    }

    /**
     * The post's body: its Zero CMS content for this store view, or the
     * excerpt when it has none yet.
     */
    public function body(Post $post, LayoutInterface $layout): string
    {
        $id = (int) $post->getId();
        if (!isset($this->bodies[$id])) {
            $content = $this->contentRepository->getForStore(Post::CONTENT_TYPE, $id, $this->storeId());
            $this->bodies[$id] = $content !== null && $content['components']
                ? $this->renderer->render($content, $layout)
                : $this->component->paragraphs((string) $post->getData('excerpt'));
        }

        return $this->bodies[$id];
    }

    /**
     * The body's h2 and h3 headings with their ids (Model\Content\Markdown
     * gives article headings ids), for the table of contents.
     *
     * @return array<int, array{level: int, id: string, text: string}>
     */
    public function toc(string $bodyHtml): array
    {
        preg_match_all('#<h([23])\s+id="([^"]+)"[^>]*>(.*?)</h\1>#su', $bodyHtml, $matches, PREG_SET_ORDER);

        return array_map(static fn ($m) => [
            'level' => (int) $m[1],
            'id' => html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5),
            'text' => trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5)),
        ], $matches);
    }

    /**
     * @return Post[]
     */
    public function related(Post $post): array
    {
        return $this->posts->related($post, 3);
    }

    /**
     * The post's products as the theme's product slider (the same cards as
     * category pages), or ''.
     */
    public function productsHtml(Post $post, LayoutInterface $layout): string
    {
        $ids = $post->getProductIds();
        if (!$ids) {
            return '';
        }
        $skus = array_column($this->productResource->getProductsSku($ids), 'sku', 'entity_id');
        $ordered = array_values(array_filter(array_map(static fn (int $id) => $skus[$id] ?? null, $ids)));

        return $ordered
            ? $this->component->productsHtml($layout, ['source' => 'skus', 'category_id' => 0, 'skus' => $ordered], (string) __('Products in this article'), 12)
            : '';
    }

    public function commentsEnabled(Post $post): bool
    {
        return $this->config->commentsEnabled($this->storeId()) && (int) $post->getData('allow_comments') === 1;
    }

    /**
     * Approved comments, oldest first.
     *
     * @return Comment[]
     */
    public function comments(Post $post): array
    {
        return array_values($this->commentCollectionFactory->create()
            ->addFieldToFilter('post_id', (int) $post->getId())
            ->addFieldToFilter('status', Comment::STATUS_APPROVED)
            ->setOrder('created_at', 'ASC')
            ->getItems());
    }

    public function commentAction(): string
    {
        return $this->storeManager->getStore()->getUrl('zero_blog/comment/post');
    }

    /**
     * Share links: plain URLs, nothing loaded from those sites.
     *
     * @return array<string, string> label => URL
     */
    public function shareLinks(Post $post): array
    {
        $url = rawurlencode($this->url->post($post));
        $title = rawurlencode((string) $post->getData('title'));

        return [
            'X' => 'https://x.com/intent/post?url=' . $url . '&text=' . $title,
            'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
            'LinkedIn' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url,
            (string) __('Email') => 'mailto:?subject=' . $title . '&body=' . $url,
        ];
    }

    /**
     * An author's profile links (website, LinkedIn, X) that are set.
     *
     * @return array<string, string>
     */
    public function authorLinks(Author $author): array
    {
        $links = [];
        foreach (['website' => (string) __('Website'), 'linkedin' => 'LinkedIn', 'twitter' => 'X'] as $field => $label) {
            $value = trim((string) $author->getData($field));
            if ($value !== '' && preg_match('#^https?://#i', $value)) {
                $links[$label] = $value;
            }
        }

        return $links;
    }

    public function paragraphs(string $text): string
    {
        return $this->component->paragraphs($text);
    }

    /**
     * @param array<int, array{url: string, webp: string|null, width: int, height: int}> $variants
     */
    private function pictureHtml(array $variants, string $alt, string $sizes, bool $eager, string $class): string
    {
        $srcset = implode(', ', array_map(static fn ($v) => $v['url'] . ' ' . $v['width'] . 'w', $variants));
        $webp = array_filter($variants, static fn ($v) => !empty($v['webp']));
        $largest = end($variants);
        $html = '<picture>';
        if (count($webp) === count($variants)) {
            $html .= sprintf(
                '<source type="image/webp" srcset="%s" sizes="%s">',
                $this->escaper->escapeHtmlAttr(implode(', ', array_map(static fn ($v) => $v['webp'] . ' ' . $v['width'] . 'w', $variants))),
                $this->escaper->escapeHtmlAttr($sizes)
            );
        }
        $html .= sprintf(
            '<img src="%s" srcset="%s" sizes="%s" width="%d" height="%d" alt="%s" class="%s" %s decoding="async">',
            $this->escaper->escapeUrl($largest['url']),
            $this->escaper->escapeHtmlAttr($srcset),
            $this->escaper->escapeHtmlAttr($sizes),
            $largest['width'],
            $largest['height'],
            $this->escaper->escapeHtmlAttr($alt),
            $this->escaper->escapeHtmlAttr($class),
            $eager ? 'fetchpriority="high"' : 'loading="lazy"'
        );

        return $html . '</picture>';
    }

    private function listUrl(int $page): string
    {
        $entity = $this->current->entity();

        return match (true) {
            $entity instanceof Category => $this->url->category($entity, null, $page),
            $entity instanceof Tag => $this->url->tag($entity, null, $page),
            $entity instanceof Author => $this->url->author($entity, null, $page),
            default => $this->url->index(null, $page),
        };
    }

    private function storeId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }
}
