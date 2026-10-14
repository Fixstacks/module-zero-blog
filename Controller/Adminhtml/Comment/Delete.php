<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml\Comment;

use Zero\Blog\Controller\Adminhtml\AbstractDelete;
use Zero\Blog\Model\Admin\Entities;

class Delete extends AbstractDelete
{
    public const ADMIN_RESOURCE = 'Zero_Blog::comments';
    protected const ENTITY = Entities::COMMENT;
}
