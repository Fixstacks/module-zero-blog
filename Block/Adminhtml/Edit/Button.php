<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Block\Adminhtml\Edit;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * The blog forms' buttons, one class configured per kind in di.xml
 * (`kind`: back, delete, save, continue, content; `entity`: post, category,
 * tag, author). Delete only shows for a saved entity. Saving goes through
 * the UI form (buttonAdapter), with `back` telling Save where to go next.
 */
class Button implements ButtonProviderInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $url,
        private readonly AuthorizationInterface $authorization,
        private readonly string $kind = 'save',
        private readonly string $entity = 'post'
    ) {
    }

    /**
     * A save button's data: the form's save action. With 'on_click' => '' (see
     * getButtonData()): without it Magento adds `location.href` to the module's
     * index page, which runs before the save and can take its "saved" message.
     */
    private function save(array $params): array
    {
        $form = 'zero_blog_' . $this->entity . '_form';

        return ['mage-init' => ['buttonAdapter' => ['actions' => [[
            'targetName' => $form . '.' . $form,
            'actionName' => 'save',
            'params' => $params,
        ]]]]];
    }

    /**
     * @return array
     */
    public function getButtonData()
    {
        $idField = $this->entity . '_id';
        $id = (int) $this->request->getParam($idField);
        $route = 'zero_blog/' . $this->entity;

        return match ($this->kind) {
            'back' => [
                'label' => __('Back'),
                'on_click' => sprintf("location.href = '%s';", $this->url->getUrl($route . '/index')),
                'class' => 'back',
                'sort_order' => 10,
            ],
            'delete' => $id ? [
                'label' => __('Delete'),
                'class' => 'delete',
                'on_click' => sprintf(
                    "deleteConfirm('%s', '%s', {data: {}})",
                    __('Delete this for good?'),
                    $this->url->getUrl($route . '/delete', [$idField => $id])
                ),
                'sort_order' => 20,
            ] : [],
            'continue' => [
                'label' => __('Save and Continue Edit'),
                'on_click' => '',
                'class' => 'save',
                'data_attribute' => $this->save([true, ['back' => 'edit']]),
                'sort_order' => 80,
            ],
            'content' => $this->authorization->isAllowed('Zero_Cms::editor') ? [
                'label' => __('Save and Edit Content'),
                'on_click' => '',
                'class' => 'save zero-cms-edit',
                'data_attribute' => $this->save([true, ['back' => 'content']]),
                'sort_order' => 85,
            ] : [],
            default => [
                'label' => __('Save'),
                'on_click' => '',
                'class' => 'save primary',
                'data_attribute' => $this->save([true]),
                'sort_order' => 90,
            ],
        };
    }
}
