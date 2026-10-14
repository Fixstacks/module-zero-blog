<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Admin;

use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The admin forms' image fields (a post's featured image, an author's
 * photo): uploaded to media/zero_blog/tmp, moved to media/zero_blog on save.
 * Stored as a media path ("/zero_blog/name.jpg").
 */
class Image
{
    public function __construct(
        private readonly ImageUploader $imageUploader,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * The media path to store from the posted field value: a new upload is
     * moved into place; the current image stays; removed: null.
     *
     * @param mixed $posted
     * @throws LocalizedException
     */
    public function fromPost($posted): ?string
    {
        $file = is_array($posted) ? ($posted[0] ?? null) : null;
        if (!is_array($file) || empty($file['name'])) {
            return null;
        }
        if (!empty($file['tmp_name']) || str_contains((string) ($file['url'] ?? ''), '/' . $this->imageUploader->getBaseTmpPath() . '/')) {
            return '/' . ltrim((string) $this->imageUploader->moveFileFromTmp(basename((string) $file['name']), true), '/');
        }
        $path = (string) ($file['path'] ?? '');
        if ($path === '') {
            $media = $this->mediaUrl();
            $url = (string) ($file['url'] ?? '');
            $path = str_starts_with($url, $media) ? '/' . ltrim(substr($url, strlen($media)), '/') : '';
        }

        return $path !== '' && !str_contains($path, '..') ? $path : null;
    }

    /**
     * The form field's value for a stored media path.
     *
     * @return array<int, array{name: string, url: string, path: string, type: string}>
     */
    public function toForm(?string $path): array
    {
        if ($path === null || $path === '') {
            return [];
        }

        return [[
            'name' => basename($path),
            'url' => $this->mediaUrl() . ltrim($path, '/'),
            'path' => $path,
            'type' => 'image',
        ]];
    }

    private function mediaUrl(): string
    {
        return rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/';
    }
}
