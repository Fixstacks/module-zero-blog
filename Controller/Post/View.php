<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Post;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Blog\Controller\AbstractPage;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\Page\Current;
use Zero\Blog\Model\Page\Meta;
use Zero\Blog\Model\PostFactory;
use Zero\Blog\Model\ResourceModel\Post as Resource;

/**
 * /blog/<post>: a published post of this store view.
 */
class View extends AbstractPage
{
    public function __construct(
        RequestInterface $request,
        PageFactory $pageFactory,
        ForwardFactory $forwardFactory,
        StoreManagerInterface $storeManager,
        Config $config,
        Current $current,
        Meta $meta,
        private readonly PostFactory $factory,
        private readonly Resource $resource
    ) {
        parent::__construct($request, $pageFactory, $forwardFactory, $storeManager, $config, $current, $meta);
    }

    protected function type(): string
    {
        return Current::POST;
    }

    /**
     * @return \Zero\Blog\Model\Post|false
     */
    protected function entity()
    {
        $post = $this->factory->create();
        $this->resource->load($post, (int) $this->request->getParam('id'));

        return $post->getId() && $post->isVisible() && $post->visibleInStore($this->storeId()) ? $post : false;
    }
}
