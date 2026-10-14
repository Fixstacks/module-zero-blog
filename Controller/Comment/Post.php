<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Controller\Comment;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Zero\Blog\Model\Comment\Submission;
use Zero\Core\Model\Captcha\Check as CaptchaCheck;
use Zero\Blog\Model\Config;
use Zero\Blog\Model\PostFactory;
use Zero\Blog\Model\ResourceModel\Post as PostResource;
use Zero\Blog\Model\Url;

/**
 * The comment form: stores the comment for moderation (Model\Comment\Submission)
 * and goes back to the post's comments with a message.
 */
class Post implements HttpPostActionInterface
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly ManagerInterface $messageManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly PostFactory $postFactory,
        private readonly PostResource $postResource,
        private readonly Submission $submission,
        private readonly Url $url,
        private readonly RemoteAddress $remoteAddress,
        private readonly CustomerSession $customerSession,
        private readonly LoggerInterface $logger,
        private readonly CaptchaCheck $captchaCheck
    ) {
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $post = $this->postFactory->create();
        $this->postResource->load($post, (int) $this->request->getPost('post_id'));
        $redirect = $this->redirectFactory->create();
        if (!$post->getId() || !$post->isVisible() || !$post->visibleInStore($storeId)
            || !$this->config->isEnabled($storeId) || !$this->config->commentsEnabled($storeId)
            || !(int) $post->getData('allow_comments')
        ) {
            return $redirect->setUrl($this->storeManager->getStore()->getBaseUrl());
        }
        $redirect->setUrl($this->url->post($post) . '#comments');
        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Your session has expired. Please send the comment again.'));

            return $redirect;
        }
        try {
            $this->captchaCheck->verify($this->request, 'zero_blog_comment');
            $this->submission->submit(
                $post,
                $storeId,
                (array) $this->request->getPostValue(),
                (string) $this->remoteAddress->getRemoteAddress(),
                $this->customerSession->isLoggedIn() ? (int) $this->customerSession->getCustomerId() : null
            );
            $this->messageManager->addSuccessMessage(__('Thank you! Your comment will appear once it is approved.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Zero_Blog: comment not saved', ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('The comment could not be saved. Please try again.'));
        }

        return $redirect;
    }
}
