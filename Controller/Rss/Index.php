<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Rss;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\Page\Posts;
use Zero\Blog\Model\Post;
use Zero\Blog\Model\Url;
use Zero\Blog\ViewModel\Blog;

/**
 * /blog/rss.xml: the 20 newest posts as RSS 2.0, cacheable for an hour.
 */
class Index implements HttpGetActionInterface
{
    private const LIMIT = 20;

    public function __construct(
        private readonly RawFactory $rawFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly Config $config,
        private readonly Posts $posts,
        private readonly Url $url,
        private readonly Blog $blog,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId) || !$this->config->rssEnabled($storeId)) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        $posts = $this->posts->latest(self::LIMIT);
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $xml->startElement('channel');
        $site = (string) ($this->scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE)
            ?: $this->storeManager->getStore()->getFrontendName());
        $xml->writeElement('title', $site . ' - ' . $this->config->title($storeId));
        $xml->writeElement('link', $this->url->index());
        $xml->writeElement('description', $this->config->metaDescription($storeId) ?: $this->config->title($storeId));
        $xml->writeElement('language', strtolower(str_replace('_', '-', (string) $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE))));
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', $this->url->rss());
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();
        if ($posts) {
            $xml->writeElement('lastBuildDate', $this->rfc822((string) $posts[0]->getData('published_at')));
        }
        foreach ($posts as $post) {
            $this->item($xml, $post);
        }
        $xml->endElement();
        $xml->endElement();

        /** @var Raw $result */
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'application/rss+xml; charset=UTF-8', true);
        $result->setHeader('Cache-Control', 'public, max-age=3600', true);
        $result->setContents($xml->outputMemory());

        return $result;
    }

    private function item(\XMLWriter $xml, Post $post): void
    {
        $link = $this->url->post($post);
        $xml->startElement('item');
        $xml->writeElement('title', (string) $post->getData('title'));
        $xml->writeElement('link', $link);
        $xml->startElement('guid');
        $xml->writeAttribute('isPermaLink', 'true');
        $xml->text($link);
        $xml->endElement();
        $xml->writeElement('pubDate', $this->rfc822((string) $post->getData('published_at')));
        $author = $this->blog->author($post);
        if ($author) {
            $xml->writeElement('dc:creator', (string) $author->getData('name'));
        }
        foreach ($this->blog->categories($post) as $category) {
            $xml->writeElement('category', $category['title']);
        }
        $xml->writeElement('description', (string) $post->getData('excerpt'));
        $xml->endElement();
    }

    private function rfc822(string $utc): string
    {
        return gmdate(DATE_RSS, (int) strtotime($utc . ' UTC'));
    }
}
