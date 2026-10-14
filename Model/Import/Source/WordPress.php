<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Import\Source;

use Magento\Framework\Exception\LocalizedException;
use Zero\Blog\Model\Import\SourceInterface;

/**
 * A WordPress export file (Tools > Export > Posts: WXR XML). Published,
 * scheduled and draft posts with their author, categories, tags, featured
 * image and URL; WordPress's line breaks become paragraphs, shortcodes are
 * dropped (a caption keeps its image and text).
 */
class WordPress implements SourceInterface
{
    private const CONTENT = 'http://purl.org/rss/1.0/modules/content/';
    private const DC = 'http://purl.org/dc/elements/1.1/';

    public function defaultOldPattern(): string
    {
        return '';
    }

    public function read(array $options): array
    {
        $file = (string) ($options['file'] ?? '');
        if ($file === '' || !is_readable($file)) {
            throw new LocalizedException(__('Give the WordPress export file: --file=export.xml'));
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file, \SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if ($xml === false || !isset($xml->channel)) {
            throw new LocalizedException(__('%1 is not a WordPress export (WXR) file.', $file));
        }
        $wpNs = $this->wpNamespace($xml);
        $excerptNs = $wpNs . 'excerpt/';
        $channel = $xml->channel;
        $wpChannel = $channel->children($wpNs);

        $authors = [];
        foreach ($wpChannel->author as $author) {
            $login = (string) $author->author_login;
            $authors[$login] = [
                'name' => (string) $author->author_display_name ?: $login,
                'url_key' => '',
                'bio' => '',
            ];
        }
        $categories = [];
        foreach ($wpChannel->category as $category) {
            $slug = (string) $category->category_nicename;
            $categories[$slug] = [
                'title' => html_entity_decode((string) $category->cat_name, ENT_QUOTES | ENT_HTML5),
                'url_key' => $slug,
                'description' => (string) $category->category_description,
            ];
        }
        $tags = [];
        foreach ($wpChannel->tag as $tag) {
            $slug = (string) $tag->tag_slug;
            $tags[$slug] = ['title' => html_entity_decode((string) $tag->tag_name, ENT_QUOTES | ENT_HTML5), 'url_key' => $slug];
        }

        $attachments = [];
        foreach ($channel->item as $item) {
            $wp = $item->children($wpNs);
            if ((string) $wp->post_type === 'attachment') {
                $attachments[(string) $wp->post_id] = (string) $wp->attachment_url;
            }
        }

        $posts = [];
        foreach ($channel->item as $item) {
            $wp = $item->children($wpNs);
            $status = (string) $wp->status;
            if ((string) $wp->post_type !== 'post' || !in_array($status, ['publish', 'future', 'draft'], true)) {
                continue;
            }
            $meta = [];
            foreach ($wp->postmeta as $postmeta) {
                $meta[(string) $postmeta->meta_key] = (string) $postmeta->meta_value;
            }
            $postCategories = [];
            $postTags = [];
            foreach ($item->category as $term) {
                $nicename = (string) $term['nicename'];
                if ((string) $term['domain'] === 'category' && $nicename !== 'uncategorized') {
                    $postCategories[] = $nicename;
                    $categories[$nicename] ??= ['title' => (string) $term, 'url_key' => $nicename, 'description' => ''];
                } elseif ((string) $term['domain'] === 'post_tag') {
                    $postTags[] = $nicename;
                    $tags[$nicename] ??= ['title' => (string) $term, 'url_key' => $nicename];
                }
            }
            $date = (string) $wp->post_date_gmt;
            if ($date === '' || str_starts_with($date, '0000')) {
                $date = (string) $wp->post_date;
            }
            $link = (string) $item->link;
            $path = (string) parse_url($link, PHP_URL_PATH);
            $posts[] = [
                'title' => html_entity_decode((string) $item->title, ENT_QUOTES | ENT_HTML5),
                'url_key' => urldecode((string) $wp->post_name),
                'html' => $this->html((string) $item->children(self::CONTENT)->encoded),
                'excerpt' => trim(strip_tags((string) $item->children($excerptNs)->encoded)),
                'published_at' => $date,
                'is_active' => in_array($status, ['publish', 'future'], true) ? 1 : 0,
                'author' => (string) $item->children(self::DC)->creator,
                'categories' => $postCategories,
                'tags' => $postTags,
                'stores' => [],
                'image' => $attachments[$meta['_thumbnail_id'] ?? ''] ?? '',
                'image_alt' => '',
                'meta_title' => $meta['_yoast_wpseo_title'] ?? ($meta['rank_math_title'] ?? ''),
                'meta_description' => $meta['_yoast_wpseo_metadesc'] ?? ($meta['rank_math_description'] ?? ''),
                'old_paths' => $path !== '' && $path !== '/' && !str_contains($link, '?p=') ? [$path] : [],
            ];
        }

        return ['authors' => $authors, 'categories' => $categories, 'tags' => $tags, 'posts' => $posts];
    }

    private function wpNamespace(\SimpleXMLElement $xml): string
    {
        foreach ($xml->getDocNamespaces(true) as $uri) {
            if (preg_match('#^http://wordpress\.org/export/\d\.\d/$#', $uri)) {
                return $uri;
            }
        }

        return 'http://wordpress.org/export/1.2/';
    }

    /**
     * Shortcodes out ([caption] keeps its content), and paragraphs where
     * WordPress only has blank lines (it adds <p> when it shows a post).
     */
    public function html(string $content): string
    {
        $content = preg_replace('#\[caption[^\]]*\](.*?)\[/caption\]#s', '<figure>$1</figure>', $content) ?? $content;
        $content = preg_replace('#\[/?[a-z_][\w-]*(?:\s[^\]]*)?\]#i', '', $content) ?? $content;
        $content = preg_replace('#<!--.*?-->#s', '', $content) ?? $content;
        if (!preg_match('#<p[\s>]#i', $content)) {
            $blocks = preg_split('/\n\s*\n/', trim($content)) ?: [];
            $content = implode("\n", array_map(static function (string $block): string {
                $block = trim($block);

                return preg_match('#^<(h[1-6]|ul|ol|blockquote|figure|table|pre|div|img)\b#i', $block)
                    ? $block
                    : '<p>' . nl2br($block, false) . '</p>';
            }, array_filter($blocks, static fn ($b) => trim($b) !== '')));
        }

        return $content;
    }
}
