<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml\Post;

use Zero\Blog\Controller\Adminhtml\AbstractEdit;
use Zero\Blog\Model\Admin\Entities;

class Edit extends AbstractEdit
{
    public const ADMIN_RESOURCE = 'Zero_Blog::posts';
    protected const ENTITY = Entities::POST;
}
