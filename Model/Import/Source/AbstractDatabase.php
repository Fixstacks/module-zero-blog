<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Model\Import\Source;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Blog\Model\Import\SourceInterface;

/**
 * A Magento blog extension's tables in this database (still there after
 * the extension is disabled). Column names differ between versions, so
 * each value is read from the first of its known names that exists.
 */
abstract class AbstractDatabase implements SourceInterface
{
    public function __construct(
        protected readonly ResourceConnection $resource,
        protected readonly StoreManagerInterface $storeManager
    ) {
    }

    protected function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    /**
     * @throws LocalizedException
     */
    protected function requireTable(string $table): string
    {
        $name = $this->resource->getTableName($table);
        if (!$this->connection()->isTableExists($name)) {
            throw new LocalizedException(__('Table %1 was not found: is the blog extension\'s data in this database?', $name));
        }

        return $name;
    }

    protected function hasTable(string $table): bool
    {
        return $this->connection()->isTableExists($this->resource->getTableName($table));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rows(string $table): array
    {
        return $this->hasTable($table)
            ? $this->connection()->fetchAll($this->connection()->select()->from($this->resource->getTableName($table)))
            : [];
    }

    /**
     * post id => [linked ids]
     *
     * @return array<int, int[]>
     */
    protected function links(string $table, string $from, string $to): array
    {
        $links = [];
        foreach ($this->rows($table) as $row) {
            if (isset($row[$from], $row[$to])) {
                $links[(int) $row[$from]][] = (int) $row[$to];
            }
        }

        return $links;
    }

    /**
     * @param array<string, mixed> $row
     * @param string[] $names
     * @return mixed
     */
    protected function pick(array $row, array $names, $default = '')
    {
        foreach ($names as $name) {
            if (array_key_exists($name, $row) && $row[$name] !== null && $row[$name] !== '') {
                return $row[$name];
            }
        }

        return $default;
    }

    /**
     * Magento directives in content: {{media url="x"}} and {{store url="x"}}
     * become real URLs, others are dropped.
     */
    protected function directives(string $html): string
    {
        $store = $this->storeManager->getDefaultStoreView() ?? $this->storeManager->getStore();
        $media = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $base = $store->getBaseUrl();
        $html = preg_replace_callback('#\{\{media\s+url=["\']?([^"\'}\s]+)["\']?\s*\}\}#i', static fn ($m) => $media . ltrim($m[1], '/'), $html) ?? $html;
        $html = preg_replace_callback('#\{\{store\s+(?:direct_)?url=["\']?([^"\'}\s]*)["\']?[^}]*\}\}#i', static fn ($m) => $base . ltrim($m[1], '/'), $html) ?? $html;

        return preg_replace('#\{\{[^}]*\}\}#', '', $html) ?? $html;
    }

    /** A stored date ("Y-m-d H:i:s", Magento stores UTC) or now. */
    protected function date($value): string
    {
        $value = (string) $value;

        return $value !== '' && !str_starts_with($value, '0000') ? $value : gmdate('Y-m-d H:i:s');
    }

    /**
     * @param int[] $stores
     * @return int[]
     */
    protected function stores(array $stores): array
    {
        return $stores ?: [0];
    }

    protected function oldPaths(string $pattern, string $key): array
    {
        return $pattern !== '' && $key !== '' ? [str_replace('{key}', $key, $pattern)] : [];
    }
}
