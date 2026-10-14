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
 * New or edit form (the UI form comes from the layout handle and loads its
 * data through Ui\DataProvider\Form).
 */
abstract class AbstractEdit extends AbstractEntity implements HttpGetActionInterface
{
    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $id = (int) $this->getRequest()->getParam($this->idParam());
        $model = $this->entities->load(static::ENTITY, $id);
        if ($id && !$model->getId()) {
            $this->messageManager->addErrorMessage(__('This %1 no longer exists.', __($this->entityLabel())));

            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu(static::ADMIN_RESOURCE);
        $title = (string) ($model->getData('title') ?: $model->getData('name'));
        $page->getConfig()->getTitle()->prepend($id ? $title : __('New %1', __($this->entityLabel())));

        return $page;
    }
}
