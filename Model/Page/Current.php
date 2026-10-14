<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Page;

use Magento\Framework\Model\AbstractModel;

/**
 * What the current blog page shows (set by its controller, read by the view
 * model and the head block): the page type, its post, category, tag or
 * author, and the list page number.
 */
class Current
{
    public const INDEX = 'index';
    public const POST = 'post';
    public const CATEGORY = 'category';
    public const TAG = 'tag';
    public const AUTHOR = 'author';

    private string $type = '';
    private ?AbstractModel $entity = null;
    private int $page = 1;

    public function set(string $type, ?AbstractModel $entity = null, int $page = 1): void
    {
        $this->type = $type;
        $this->entity = $entity;
        $this->page = max(1, $page);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function entity(): ?AbstractModel
    {
        return $this->entity;
    }

    public function page(): int
    {
        return $this->page;
    }
}
