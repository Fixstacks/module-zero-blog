<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\ResourceModel\Post as PostResource;
use Zero\Blog\Model\Url;

/**
 * The blog's readable URLs (Model\Url) to its actions:
 * zero_blog/{index,post,category,tag,author,rss}/... with the entity id.
 * Unknown keys fall through to the next router (and so to the 404 page).
 */
class Router implements RouterInterface
{
    private const LISTS = [Url::CATEGORY => 'zero_blog_category', Url::TAG => 'zero_blog_tag', Url::AUTHOR => 'zero_blog_author'];

    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly PostResource $postResource
    ) {
    }

    /**
     * @param RequestInterface $request
     * @return ActionInterface|null
     */
    public function match(RequestInterface $request)
    {
        if (!$request instanceof HttpRequest || $request->getModuleName() === 'zero_blog') {
            return null;
        }
        $storeId = (int) $this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)) {
            return null;
        }
        $path = trim((string) $request->getPathInfo(), '/');
        $route = $this->config->route($storeId);
        if ($path === $route) {
            return $this->forward($request, $path, 'index', 'index');
        }
        if (!str_starts_with($path, $route . '/')) {
            return null;
        }
        $parts = explode('/', substr($path, strlen($route) + 1));

        if (count($parts) === 1 && $parts[0] === Url::RSS) {
            return $this->config->rssEnabled($storeId) ? $this->forward($request, $path, 'rss', 'index') : null;
        }
        if (count($parts) === 2 && isset(self::LISTS[$parts[0]])) {
            $id = $this->listId(self::LISTS[$parts[0]], $parts[0] . '_id', rawurldecode($parts[1]));

            return $id !== null ? $this->forward($request, $path, $parts[0], 'view', $id) : null;
        }
        if (count($parts) === 1 && $parts[0] !== '') {
            $id = $this->postResource->findVisibleId(rawurldecode($parts[0]), $storeId, gmdate('Y-m-d H:i:s'));

            return $id !== null ? $this->forward($request, $path, 'post', 'view', $id) : null;
        }

        return null;
    }

    private function listId(string $table, string $idField, string $urlKey): ?int
    {
        $connection = $this->postResource->getConnection();
        $select = $connection->select()
            ->from($this->postResource->getTable($table), [$idField])
            ->where('url_key = ?', $urlKey)
            ->limit(1);
        if ($table !== 'zero_blog_tag') {
            $select->where('is_active = 1');
        }
        $id = $connection->fetchOne($select);

        return $id !== false ? (int) $id : null;
    }

    private function forward(HttpRequest $request, string $path, string $controller, string $action, ?int $id = null): ActionInterface
    {
        $request->setModuleName('zero_blog')->setControllerName($controller)->setActionName($action);
        if ($id !== null) {
            $request->setParam('id', $id);
        }
        $request->setAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, $path);

        return $this->actionFactory->create(Forward::class);
    }
}
