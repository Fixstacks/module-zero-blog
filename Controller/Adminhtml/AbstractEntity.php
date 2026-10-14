<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Zero\Blog\Model\Admin\Entities;

/**
 * Base of the blog's admin actions: each concrete controller names its
 * entity (ENTITY, one of Model\Admin\Entities) and ACL resource.
 */
abstract class AbstractEntity extends Action
{
    protected const ENTITY = '';

    public function __construct(Context $context, protected readonly Entities $entities)
    {
        parent::__construct($context);
    }

    protected function entityLabel(): string
    {
        return $this->entities->label(static::ENTITY);
    }

    protected function idParam(): string
    {
        return $this->entities->idField(static::ENTITY);
    }
}
