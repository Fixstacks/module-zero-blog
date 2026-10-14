<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Test\Unit\Model\Import\Source;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Zero\Blog\Model\Import\Source\WordPress;

class WordPressTest extends TestCase
{
    private const WXR = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
    <wp:author><wp:author_login>mara</wp:author_login><wp:author_display_name><![CDATA[Mara Lind]]></wp:author_display_name></wp:author>
    <wp:category><wp:category_nicename>guides</wp:category_nicename><wp:cat_name><![CDATA[Guides &amp; Tips]]></wp:cat_name></wp:category>
    <wp:tag><wp:tag_slug>running</wp:tag_slug><wp:tag_name><![CDATA[Running]]></wp:tag_name></wp:tag>
    <item>
        <title>Jacket</title>
        <wp:post_id>10</wp:post_id>
        <wp:post_type>attachment</wp:post_type>
        <wp:attachment_url>https://old.example/wp-content/uploads/jacket.jpg</wp:attachment_url>
    </item>
    <item>
        <title>How to choose a jacket</title>
        <link>https://old.example/2024/05/how-to-choose-a-jacket/</link>
        <dc:creator><![CDATA[mara]]></dc:creator>
        <content:encoded><![CDATA[First paragraph
still first.

[caption id="x" width="300"]<img src="https://old.example/a.jpg" alt="A"> A caption[/caption]

<!-- wp:paragraph -->Second [gallery ids="1,2"] paragraph.]]></content:encoded>
        <excerpt:encoded><![CDATA[<p>Short version.</p>]]></excerpt:encoded>
        <wp:post_id>11</wp:post_id>
        <wp:post_date_gmt>2024-05-02 08:30:00</wp:post_date_gmt>
        <wp:post_name><![CDATA[how-to-choose-a-jacket]]></wp:post_name>
        <wp:status>publish</wp:status>
        <wp:post_type>post</wp:post_type>
        <category domain="category" nicename="guides"><![CDATA[Guides & Tips]]></category>
        <category domain="post_tag" nicename="running"><![CDATA[Running]]></category>
        <category domain="category" nicename="uncategorized"><![CDATA[Uncategorized]]></category>
        <wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>10</wp:meta_value></wp:postmeta>
        <wp:postmeta><wp:meta_key>_yoast_wpseo_metadesc</wp:meta_key><wp:meta_value>Yoast description</wp:meta_value></wp:postmeta>
    </item>
    <item>
        <title>A page</title>
        <wp:post_type>page</wp:post_type>
        <wp:status>publish</wp:status>
    </item>
    <item>
        <title>Old draft</title>
        <wp:post_type>post</wp:post_type>
        <wp:status>trash</wp:status>
    </item>
</channel>
</rss>
XML;

    public function testReadsPostsWithTheirTermsImageAndOldAddress(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'wxr');
        file_put_contents($file, self::WXR);
        try {
            $data = (new WordPress())->read(['file' => $file]);
        } finally {
            unlink($file);
        }
        $this->assertSame(['mara' => ['name' => 'Mara Lind', 'url_key' => '', 'bio' => '']], $data['authors']);
        $this->assertSame('Guides & Tips', $data['categories']['guides']['title']);
        $this->assertSame('Running', $data['tags']['running']['title']);
        $this->assertCount(1, $data['posts']);
        $post = $data['posts'][0];
        $this->assertSame('how-to-choose-a-jacket', $post['url_key']);
        $this->assertSame('2024-05-02 08:30:00', $post['published_at']);
        $this->assertSame(1, $post['is_active']);
        $this->assertSame('mara', $post['author']);
        $this->assertSame(['guides'], $post['categories']);
        $this->assertSame(['running'], $post['tags']);
        $this->assertSame('https://old.example/wp-content/uploads/jacket.jpg', $post['image']);
        $this->assertSame('Short version.', $post['excerpt']);
        $this->assertSame('Yoast description', $post['meta_description']);
        $this->assertSame(['/2024/05/how-to-choose-a-jacket/'], $post['old_paths']);
        // Paragraphs from blank lines, shortcodes gone, the caption keeps image and text.
        $this->assertStringContainsString("<p>First paragraph<br>\nstill first.</p>", $post['html']);
        $this->assertStringContainsString('<figure><img src="https://old.example/a.jpg" alt="A"> A caption</figure>', $post['html']);
        $this->assertStringContainsString('<p>Second  paragraph.</p>', $post['html']);
        $this->assertStringNotContainsString('wp:paragraph', $post['html']);
    }

    public function testMissingFileIsExplained(): void
    {
        $this->expectException(LocalizedException::class);
        (new WordPress())->read(['file' => '/no/such/file.xml']);
    }
}
