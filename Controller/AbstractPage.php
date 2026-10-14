<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\Page\Current;
use Zero\Blog\Model\Page\Meta;

/**
 * A blog page: what it shows goes to Model\Page\Current, then Model\Page\Meta
 * sets its title, description, canonical URL, robots and breadcrumbs. Blog
 * pages are cacheable (the full page cache and Varnish); saving any blog
 * entity refreshes them by cache tag.
 */
abstract class AbstractPage implements HttpGetActionInterface
{
    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly PageFactory $pageFactory,
        protected readonly ForwardFactory $forwardFactory,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly Config $config,
        protected readonly Current $current,
        protected readonly Meta $meta
    ) {
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if (!$this->config->isEnabled($this->storeId())) {
            return $this->notFound();
        }
        $page = max(1, (int) $this->request->getParam('p', 1));
        $entity = $this->entity();
        if ($entity === false) {
            return $this->notFound();
        }
        $this->current->set($this->type(), $entity, $page);
        if ($page > 1 && $page > $this->meta->lastPage()) {
            return $this->notFound();
        }
        /** @var Page $result */
        $result = $this->pageFactory->create();
        $this->meta->apply($result);

        return $result;
    }

    abstract protected function type(): string;

    /**
     * The page's post, category, tag or author; null for the index; false: not found.
     *
     * @return AbstractModel|null|false
     */
    abstract protected function entity();

    protected function storeId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    protected function notFound()
    {
        return $this->forwardFactory->create()->forward('noroute');
    }
}
