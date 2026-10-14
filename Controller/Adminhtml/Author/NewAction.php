<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml\Author;

use Zero\Blog\Controller\Adminhtml\AbstractNew;
use Zero\Blog\Model\Admin\Entities;

class NewAction extends AbstractNew
{
    public const ADMIN_RESOURCE = 'Zero_Blog::authors';
    protected const ENTITY = Entities::AUTHOR;
}
