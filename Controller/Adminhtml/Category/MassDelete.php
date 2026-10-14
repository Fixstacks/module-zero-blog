<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml\Category;

use Zero\Blog\Controller\Adminhtml\AbstractMassDelete;
use Zero\Blog\Model\Admin\Entities;

class MassDelete extends AbstractMassDelete
{
    public const ADMIN_RESOURCE = 'Zero_Blog::categories';
    protected const ENTITY = Entities::CATEGORY;
}
