<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Adminhtml\Post;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Zero\Blog\Controller\Adminhtml\AbstractSave;
use Zero\Blog\Model\Admin\Entities;
use Zero\Blog\Model\Admin\Image;
use Zero\Blog\Model\Admin\Products;
use Zero\Blog\Model\Admin\Tags;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\Post;
use Zero\Blog\Model\Redirects;
use Zero\Blog\Model\ResourceModel\Post as PostResource;
use Zero\Blog\Model\UrlKey;

/**
 * Saves a post's form. The URL key comes from the title when left empty
 * and must be free in the post's store views; the publish date is typed in
 * the admin's time zone and stored in UTC (empty and published: now). When a
 * published post's URL key changes, its old URL redirects to the new one.
 */
class Save extends AbstractSave
{
    public const ADMIN_RESOURCE = 'Zero_Blog::posts';
    protected const ENTITY = Entities::POST;

    private ?array $previous = null;

    public function __construct(
        Context $context,
        Entities $entities,
        DataPersistorInterface $dataPersistor,
        private readonly UrlKey $urlKey,
        private readonly PostResource $postResource,
        private readonly Image $image,
        private readonly Tags $tags,
        private readonly Products $products,
        private readonly TimezoneInterface $timezone,
        private readonly Redirects $redirects,
        private readonly Config $config
    ) {
        parent::__construct($context, $entities, $dataPersistor);
    }

    /**
     * @param Post $model
     */
    protected function prepare(AbstractModel $model, array $data): void
    {
        $this->previous = $model->getId() && $model->isVisible()
            ? ['url_key' => (string) $model->getData('url_key'), 'stores' => $model->getStoreIds()]
            : null;
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new LocalizedException(__('The post needs a title.'));
        }
        $key = trim(strtolower((string) ($data['url_key'] ?? ''))) ?: $this->urlKey->fromText($title);
        if (!$this->urlKey->isValid($key)) {
            throw new LocalizedException(__('The URL key can have lowercase letters, digits and dashes only.'));
        }
        if (in_array($key, UrlKey::RESERVED, true)) {
            throw new LocalizedException(__('The URL key "%1" is used by the blog itself. Choose another one.', $key));
        }
        $stores = array_map('intval', (array) ($data['store_ids'] ?? [0])) ?: [0];
        if ($this->postResource->urlKeyTakenBy($key, $stores, (int) $model->getId()) !== null) {
            throw new LocalizedException(__('Another post in these store views already uses the URL key "%1".', $key));
        }

        $active = (int) !empty($data['is_active']);
        $publishedAt = trim((string) ($data['published_at'] ?? ''));
        if ($publishedAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{1,2}:\d{2})?$/', $publishedAt)) {
            throw new LocalizedException(__('Type the publish date as YYYY-MM-DD HH:MM.'));
        }
        $publishedAt = $publishedAt !== ''
            ? $this->timezone->convertConfigTimeToUtc($publishedAt)
            : ($active ? gmdate('Y-m-d H:i:s') : null);

        $products = $this->products->idsFor((string) ($data['products'] ?? ''));
        if ($products['unknown']) {
            $this->messageManager->addWarningMessage(__('These SKUs were not found and are left out: %1', implode(', ', $products['unknown'])));
        }

        $model->addData([
            'title' => mb_substr($title, 0, 255),
            'url_key' => $key,
            'excerpt' => $this->text($data, 'excerpt', 1000),
            'image' => $this->image->fromPost($data['image'] ?? null),
            'image_alt' => $this->text($data, 'image_alt', 255),
            'author_id' => (int) ($data['author_id'] ?? 0) ?: null,
            'is_active' => $active,
            'published_at' => $publishedAt,
            'allow_comments' => (int) !empty($data['allow_comments']),
            'meta_title' => $this->text($data, 'meta_title', 255),
            'meta_description' => $this->text($data, 'meta_description', 500),
            'meta_robots' => $this->text($data, 'meta_robots', 32),
            'canonical_url' => $this->text($data, 'canonical_url', 512),
            'store_ids' => $stores,
            'category_ids' => array_map('intval', (array) ($data['category_ids'] ?? [])),
            'tag_ids' => $this->tags->idsFor((string) ($data['tags'] ?? '')),
            'product_ids' => $products['ids'],
        ]);
    }

    /**
     * @param Post $model
     */
    protected function afterSave(AbstractModel $model): void
    {
        if ($this->previous !== null && $this->previous['url_key'] !== $model->getData('url_key')) {
            $route = $this->config->route();
            $this->redirects->add(
                $route . '/' . $this->previous['url_key'],
                $route . '/' . $model->getData('url_key'),
                $model->getStoreIds(),
                (int) $model->getId()
            );
            $this->messageManager->addNoticeMessage(__('The old URL now redirects to the new one.'));
        }
    }
}
