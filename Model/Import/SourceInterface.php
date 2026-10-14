<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Import;

use Magento\Framework\Exception\LocalizedException;

/**
 * A blog to import from, read into one plain shape (Model\Import\Importer
 * writes it). Ids are the source's own; dates are UTC "Y-m-d H:i:s".
 *
 *   authors:    [id => [name, url_key, bio]]
 *   categories: [id => [title, url_key, description, meta_title, meta_description]]
 *   tags:       [id => [title, url_key]]
 *   posts:      [[title, url_key, html, excerpt, published_at, is_active,
 *                 author, categories[], tags[], stores[], image, image_alt,
 *                 meta_title, meta_description, old_paths[]]]
 *
 * `image` is a URL to download or a path under pub/media; `old_paths` are
 * the post's addresses on the old blog (redirected to the new one).
 */
interface SourceInterface
{
    /**
     * @param array{file?: string, old_pattern?: string} $options
     * @return array{authors: array, categories: array, tags: array, posts: array}
     * @throws LocalizedException
     */
    public function read(array $options): array;

    /** Old post path, {key} = the post's URL key; '' when the source has real links. */
    public function defaultOldPattern(): string;
}
