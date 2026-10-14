<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Zero\Blog\Model\Admin\Entities;

/**
 * Saves a form: the concrete action fills the model from the posted data
 * (prepare()). On an error the form opens again with what was typed.
 * "Save and Continue" stays on the form (back=edit); a post's "Save and
 * Edit Content" goes on to the Zero CMS editor (back=content).
 */
abstract class AbstractSave extends AbstractEntity implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        Entities $entities,
        protected readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context, $entities);
    }

    /**
     * @param array<string, mixed> $data The posted form data
     * @throws LocalizedException
     */
    abstract protected function prepare(AbstractModel $model, array $data): void;

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $data = (array) $this->getRequest()->getPostValue();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }
        $idField = $this->idParam();
        $id = (int) ($data[$idField] ?? 0);
        $model = $this->entities->load(static::ENTITY, $id);
        if ($id && !$model->getId()) {
            $this->messageManager->addErrorMessage(__('This %1 no longer exists.', __($this->entityLabel())));

            return $redirect->setPath('*/*/');
        }
        try {
            $this->prepare($model, $data);
            $this->entities->resource(static::ENTITY)->save($model);
            $this->messageManager->addSuccessMessage(__('The %1 was saved.', __($this->entityLabel())));
            $this->dataPersistor->clear('zero_blog_' . static::ENTITY);
            $this->afterSave($model);

            return match ((string) $this->getRequest()->getParam('back')) {
                'edit' => $redirect->setPath('*/*/edit', [$idField => $model->getId()]),
                'content' => $redirect->setPath('zero_cms/editor/index', ['type' => \Zero\Blog\Model\Post::CONTENT_TYPE, 'id' => $model->getId()]),
                default => $redirect->setPath('*/*/'),
            };
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the %1.', __($this->entityLabel())));
        }
        $this->dataPersistor->set('zero_blog_' . static::ENTITY, $data);

        return $redirect->setPath('*/*/edit', $id ? [$idField => $id] : []);
    }

    protected function afterSave(AbstractModel $model): void
    {
    }

    /**
     * A posted text field, trimmed; null when empty (so nullable columns stay NULL).
     */
    protected function text(array $data, string $key, int $max = 0): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($max > 0) {
            $value = mb_substr($value, 0, $max);
        }

        return $value !== '' ? $value : null;
    }
}
