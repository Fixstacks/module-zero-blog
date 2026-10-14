<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Row actions in the blog grids: Edit (not for comments, which have no
 * form), Edit Content (posts: the Zero CMS editor), Delete. The entity comes
 * from the column's indexField: post_id is zero_blog/post, and so on.
 */
class Actions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        $idField = (string) $this->getData('config/indexField');
        $entity = substr($idField, 0, -3);
        $route = 'zero_blog/' . $entity;
        if (empty($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            $id = $item[$idField] ?? null;
            if (!$id) {
                continue;
            }
            $actions = [];
            if ($entity !== 'comment') {
                $actions['edit'] = ['href' => $this->urlBuilder->getUrl($route . '/edit', [$idField => $id]), 'label' => __('Edit')];
            }
            if ($entity === 'post') {
                $actions['content'] = [
                    'href' => $this->urlBuilder->getUrl('zero_cms/editor/index', ['type' => 'zero_blog_post', 'id' => $id]),
                    'label' => __('Edit Content'),
                ];
            }
            $actions['delete'] = [
                'href' => $this->urlBuilder->getUrl($route . '/delete', [$idField => $id]),
                'label' => __('Delete'),
                'confirm' => ['title' => __('Delete'), 'message' => __('Delete this for good?')],
                'post' => true,
            ];
            $item[$this->getData('name')] = $actions;
        }
        unset($item);

        return $dataSource;
    }
}
