<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml\Comment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;
use Zero\Blog\Controller\Adminhtml\AbstractEntity;
use Zero\Blog\Model\Admin\Entities;
use Zero\Blog\Model\Comment;

/**
 * Approves or rejects the selected comments (?status=1 or 2). Saving one
 * refreshes its post's page, where approved comments show.
 */
class MassStatus extends AbstractEntity implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Zero_Blog::comments';
    protected const ENTITY = Entities::COMMENT;

    public function __construct(Context $context, Entities $entities, private readonly Filter $filter)
    {
        parent::__construct($context, $entities);
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $status = (int) $this->getRequest()->getParam('status');
        if (!in_array($status, [Comment::STATUS_APPROVED, Comment::STATUS_REJECTED, Comment::STATUS_PENDING], true)) {
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $resource = $this->entities->resource(static::ENTITY);
        $count = 0;
        foreach ($this->filter->getCollection($this->entities->collection(static::ENTITY)) as $comment) {
            if ((int) $comment->getData('status') !== $status) {
                $comment->setData('status', $status);
                $resource->save($comment);
                $count++;
            }
        }
        $this->messageManager->addSuccessMessage(match ($status) {
            Comment::STATUS_APPROVED => __('%1 comment(s) approved.', $count),
            Comment::STATUS_REJECTED => __('%1 comment(s) rejected.', $count),
            default => __('%1 comment(s) marked as pending.', $count),
        });

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
