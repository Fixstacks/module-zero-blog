<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Zero\Blog\Model\Admin\Entities;
use Zero\Blog\Model\Admin\Image;
use Zero\Blog\Model\UrlKey;

/**
 * Saves a category, tag or author: its text fields (FIELDS => max length),
 * a URL key from the name when empty (unique), an enabled switch and an
 * image where the entity has them.
 */
abstract class AbstractSimpleSave extends AbstractSave
{
    /** @var array<string, int> */
    protected const FIELDS = [];
    protected const NAME_FIELD = 'title';

    public function __construct(
        Context $context,
        Entities $entities,
        DataPersistorInterface $dataPersistor,
        protected readonly UrlKey $urlKey,
        protected readonly Image $image
    ) {
        parent::__construct($context, $entities, $dataPersistor);
    }

    protected function prepare(AbstractModel $model, array $data): void
    {
        $name = trim((string) ($data[static::NAME_FIELD] ?? ''));
        if ($name === '') {
            throw new LocalizedException(__('Please fill in the name.'));
        }
        $key = trim(strtolower((string) ($data['url_key'] ?? ''))) ?: $this->urlKey->fromText($name);
        if (!$this->urlKey->isValid($key)) {
            throw new LocalizedException(__('The URL key can have lowercase letters, digits and dashes only.'));
        }
        $resource = $this->entities->resource(static::ENTITY);
        $taken = $resource->getConnection()->fetchOne(
            $resource->getConnection()->select()->from($resource->getMainTable(), [$this->idParam()])
                ->where('url_key = ?', $key)->where($this->idParam() . ' != ?', (int) $model->getId())->limit(1)
        );
        if ($taken !== false) {
            throw new LocalizedException(__('The URL key "%1" is already used. Choose another one.', $key));
        }
        $values = [static::NAME_FIELD => mb_substr($name, 0, 255), 'url_key' => $key];
        foreach (static::FIELDS as $field => $max) {
            $values[$field] = $this->text($data, $field, $max);
        }
        if (array_key_exists('is_active', $data)) {
            $values['is_active'] = (int) !empty($data['is_active']);
        }
        if (array_key_exists('position', $data)) {
            $values['position'] = (int) $data['position'];
        }
        if (array_key_exists('image', $data) || $model->hasData('image')) {
            $values['image'] = $this->image->fromPost($data['image'] ?? null);
        }
        $model->addData($values);
    }
}
