<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Ui\DataProvider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Zero\Blog\Model\Admin\Entities;
use Zero\Blog\Model\Admin\Image;
use Zero\Blog\Model\Admin\Products;
use Zero\Blog\Model\Admin\Tags;
use Zero\Blog\Model\Post;

/**
 * Data of a blog admin form (`entity` in the form's XML: post, category,
 * tag, author). A post's links come as form values: tags and products as
 * text, the publish date in the admin's time zone. After a failed save the
 * form shows what was typed.
 */
class Form extends AbstractDataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly Entities $entities,
        private readonly RequestInterface $request,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly Image $image,
        private readonly Tags $tags,
        private readonly Products $products,
        private readonly TimezoneInterface $timezone,
        private readonly string $entity = Entities::POST,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $this->entities->collection($this->entity);
    }

    /**
     * @return array
     */
    public function getData()
    {
        $id = (int) $this->request->getParam($this->requestFieldName);
        $persisted = $this->dataPersistor->get('zero_blog_' . $this->entity);
        if (is_array($persisted)) {
            $this->dataPersistor->clear('zero_blog_' . $this->entity);
            unset($persisted['image']);

            return [$id ?: '' => $persisted];
        }
        if (!$id) {
            return [];
        }
        $model = $this->entities->load($this->entity, $id);
        if (!$model->getId()) {
            return [];
        }
        $values = $model->getData();
        if (array_key_exists('image', $values)) {
            $values['image'] = $this->image->toForm($model->getData('image'));
        }
        if ($model instanceof Post) {
            $values['store_ids'] = array_map('strval', $model->getStoreIds());
            $values['category_ids'] = array_map('strval', $model->getCategoryIds());
            $values['tags'] = $this->tags->titlesOf($model->getTagIds());
            $values['products'] = $this->products->skusOf($model->getProductIds());
            $values['published_at'] = $model->getData('published_at')
                ? $this->timezone->date(new \DateTime((string) $model->getData('published_at'), new \DateTimeZone('UTC')))->format('Y-m-d H:i')
                : '';
            unset($values['store_ids_orig']);
        }

        return [$id => $values];
    }
}
