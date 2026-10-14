<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;
use Zero\Blog\Model\Admin\Entities;

/**
 * Deletes the rows selected in the grid.
 */
abstract class AbstractMassDelete extends AbstractEntity implements HttpPostActionInterface
{
    public function __construct(Context $context, Entities $entities, protected readonly Filter $filter)
    {
        parent::__construct($context, $entities);
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->entities->collection(static::ENTITY));
        $resource = $this->entities->resource(static::ENTITY);
        $count = 0;
        foreach ($collection as $model) {
            $resource->delete($model);
            $count++;
        }
        $this->messageManager->addSuccessMessage(__('%1 record(s) were deleted.', $count));

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
