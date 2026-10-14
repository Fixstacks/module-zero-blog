<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Category;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Blog\Controller\AbstractPage;
use Zero\Blog\Model\CategoryFactory;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\Page\Current;
use Zero\Blog\Model\Page\Meta;
use Zero\Blog\Model\ResourceModel\Category as Resource;

/**
 * /blog/category/<key>: the category's posts (Controller\Router resolved the id).
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
        private readonly CategoryFactory $factory,
        private readonly Resource $resource
    ) {
        parent::__construct($request, $pageFactory, $forwardFactory, $storeManager, $config, $current, $meta);
    }

    protected function type(): string
    {
        return Current::CATEGORY;
    }

    /**
     * @return \Zero\Blog\Model\Category|false
     */
    protected function entity()
    {
        $entity = $this->factory->create();
        $this->resource->load($entity, (int) $this->request->getParam('id'));

        return $entity->getId() && ($entity->getData('is_active') ?? 1) ? $entity : false;
    }
}
