<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Index;

use Zero\Blog\Controller\AbstractPage;
use Zero\Blog\Model\Page\Current;

/**
 * /blog: the newest posts.
 */
class Index extends AbstractPage
{
    protected function type(): string
    {
        return Current::INDEX;
    }

    /**
     * @return null
     */
    protected function entity()
    {
        return null;
    }
}
