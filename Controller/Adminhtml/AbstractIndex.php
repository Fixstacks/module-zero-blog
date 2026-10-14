<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * An entity's grid (its UI listing comes from the layout handle).
 */
abstract class AbstractIndex extends AbstractEntity implements HttpGetActionInterface
{
    /**
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu(static::ADMIN_RESOURCE);
        $page->getConfig()->getTitle()->prepend(__('Blog'));
        $page->getConfig()->getTitle()->prepend(__($this->entities->pluralLabel(static::ENTITY)));

        return $page;
    }
}
