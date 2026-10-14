<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Test\Unit\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Zero\Blog\Controller\Router;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\ResourceModel\Post as PostResource;

#[AllowMockObjectsWithoutExpectations]
class RouterTest extends TestCase
{
    /** @var array<string, int|false> url_key => id the lookup finds */
    private array $found = [];

    private function router(bool $enabled = true): Router
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('route')->willReturn('blog');
        $config->method('rssEnabled')->willReturn(true);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $select = $this->createStub(Select::class);
        $select->method($this->anything())->willReturnSelf();
        $lastKey = '';
        $select->method('where')->willReturnCallback(function ($cond, $value = null) use ($select, &$lastKey) {
            if ($cond === 'url_key = ?') {
                $lastKey = (string) $value;
            }
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturnCallback(function () use (&$lastKey) {
            return $this->found[$lastKey] ?? false;
        });
        $resource = $this->createStub(PostResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTable')->willReturnArgument(0);
        $resource->method('findVisibleId')->willReturnCallback(fn ($key) => isset($this->found['post:' . $key]) ? (int) $this->found['post:' . $key] : null);

        $actionFactory = $this->createStub(ActionFactory::class);
        $actionFactory->method('create')->willReturn($this->createStub(Forward::class));

        return new Router($actionFactory, $config, $storeManager, $resource);
    }

    private function request(string $path, \stdClass $route): HttpRequest
    {
        $request = $this->getMockBuilder(HttpRequest::class)->disableOriginalConstructor()
            ->onlyMethods(['getPathInfo', 'getModuleName', 'setModuleName', 'setControllerName', 'setActionName', 'setParam', 'setAlias'])
            ->getMock();
        $request->method('getPathInfo')->willReturn($path);
        $request->method('getModuleName')->willReturn(null);
        foreach (['setModuleName' => 'module', 'setControllerName' => 'controller', 'setActionName' => 'action'] as $method => $key) {
            $request->method($method)->willReturnCallback(function ($value) use ($request, $route, $key) {
                $route->values[$key] = $value;
                return $request;
            });
        }
        $request->method('setParam')->willReturnCallback(function ($name, $value) use ($request, $route) {
            $route->values[$name] = $value;
            return $request;
        });

        return $request;
    }

    /**
     * @return array<string, string|int>|null
     */
    private function route(string $path, bool $enabled = true): ?array
    {
        $route = new \stdClass();
        $route->values = [];

        return $this->router($enabled)->match($this->request($path, $route)) ? $route->values : null;
    }

    public function testBlogUrls(): void
    {
        $this->found = ['post:how-to-run' => 7, 'guides' => 3, 'mara' => 2, 'running' => 9];
        $this->assertSame(['module' => 'zero_blog', 'controller' => 'index', 'action' => 'index'], $this->route('/blog'));
        $this->assertSame(['module' => 'zero_blog', 'controller' => 'index', 'action' => 'index'], $this->route('/blog/'));
        $this->assertSame(['module' => 'zero_blog', 'controller' => 'post', 'action' => 'view', 'id' => 7], $this->route('/blog/how-to-run'));
        $this->assertSame(['module' => 'zero_blog', 'controller' => 'category', 'action' => 'view', 'id' => 3], $this->route('/blog/category/guides'));
        $this->assertSame(['module' => 'zero_blog', 'controller' => 'author', 'action' => 'view', 'id' => 2], $this->route('/blog/author/mara'));
        $this->assertSame(['module' => 'zero_blog', 'controller' => 'tag', 'action' => 'view', 'id' => 9], $this->route('/blog/tag/running'));
        $this->assertSame(['module' => 'zero_blog', 'controller' => 'rss', 'action' => 'index'], $this->route('/blog/rss.xml'));
    }

    public function testEverythingElseFallsThrough(): void
    {
        $this->found = ['post:how-to-run' => 7];
        $this->assertNull($this->route('/blog/unknown'));
        $this->assertNull($this->route('/blog/category/unknown'));
        $this->assertNull($this->route('/blog/how-to-run/extra'));
        $this->assertNull($this->route('/blogger'));
        $this->assertNull($this->route('/women.html'));
        $this->assertNull($this->route('/blog', false));
    }
}
