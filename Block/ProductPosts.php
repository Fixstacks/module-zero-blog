<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Block;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\Page\Posts;
use Zero\Blog\Model\Post;

/**
 * "From the blog" on a product page. Tagged zero_blog_product_<id>, which a
 * post carries for each product it links to (or linked to), so linking or
 * unlinking a post refreshes just those product pages.
 */
class ProductPosts extends Template implements IdentityInterface
{
    public function __construct(
        Context $context,
        private readonly CatalogHelper $catalogHelper,
        private readonly Posts $posts,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return Post[]
     */
    public function posts(): array
    {
        $productId = $this->productId();
        if (!$productId || !$this->config->isEnabled() || !$this->config->showOnProductPage()) {
            return [];
        }

        return $this->posts->forProduct($productId, 3);
    }

    /**
     * @return string[]
     */
    public function getIdentities()
    {
        return $this->productId() ? [Post::PRODUCT_TAG . '_' . $this->productId()] : [];
    }

    private function productId(): int
    {
        return (int) $this->catalogHelper->getProduct()?->getId();
    }
}
