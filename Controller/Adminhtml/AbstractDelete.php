<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml;

use Magento\Framework\App\Action\HttpPostActionInterface;

abstract class AbstractDelete extends AbstractEntity implements HttpPostActionInterface
{
    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $model = $this->entities->load(static::ENTITY, (int) $this->getRequest()->getParam($this->idParam()));
        if ($model->getId()) {
            try {
                $this->entities->resource(static::ENTITY)->delete($model);
                $this->messageManager->addSuccessMessage(__('The %1 was deleted.', __($this->entityLabel())));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
