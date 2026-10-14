<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml\Author;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Zero\Blog\Controller\Adminhtml\AbstractSimpleSave;
use Zero\Blog\Model\Admin\Entities;

class Save extends AbstractSimpleSave
{
    public const ADMIN_RESOURCE = 'Zero_Blog::authors';
    protected const ENTITY = Entities::AUTHOR;
    protected const NAME_FIELD = 'name';
    protected const FIELDS = [
        'job_title' => 255, 'bio' => 5000, 'website' => 255, 'linkedin' => 255, 'twitter' => 255,
        'meta_title' => 255, 'meta_description' => 500,
    ];

    protected function prepare(AbstractModel $model, array $data): void
    {
        foreach (['website', 'linkedin', 'twitter'] as $field) {
            $url = trim((string) ($data[$field] ?? ''));
            if ($url !== '' && !preg_match('#^https://[^\s<>"]+$#i', $url)) {
                throw new LocalizedException(__('Profile links must be full https:// addresses.'));
            }
        }
        $data['image'] ??= [];
        parent::prepare($model, $data);
    }
}
