<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model;

use Magento\Framework\Filter\TranslitUrl;

/**
 * URL keys: lowercase latin letters, digits and dashes, from any title
 * (transliterated the way product URL keys are).
 */
class UrlKey
{
    /** Paths the router uses itself; a post can't take them. */
    public const RESERVED = [Url::CATEGORY, Url::TAG, Url::AUTHOR, Url::RSS, 'comment', 'index'];

    public function __construct(private readonly TranslitUrl $translit)
    {
    }

    public function fromText(string $text): string
    {
        $key = trim((string) $this->translit->filter($text), '-');
        $key = preg_replace('/-{2,}/', '-', $key) ?? $key;

        return substr($key, 0, 200) ?: '';
    }

    public function isValid(string $key): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) && strlen($key) <= 255;
    }
}
