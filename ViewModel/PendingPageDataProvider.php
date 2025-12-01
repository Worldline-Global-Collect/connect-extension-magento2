<?php

namespace Worldline\Connect\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

class PendingPageDataProvider implements ArgumentInterface
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var SenderResolverInterface
     */
    private $senderResolver;

    /**
     * @param RequestInterface $request
     * @param SenderResolverInterface $senderResolver
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        RequestInterface $request,
        SenderResolverInterface $senderResolver,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder
    )
    {
        $this->request = $request;
        $this->senderResolver = $senderResolver;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
    }

    public function getNotificationMessage(string $messagePostfix = ''): string
    {
        $message = __('Thank you for your order %1.', $this->getIncrementId());
        $message .= '<br> ';
        $message .= __('Your order is still being processed and you will receive a confirmation e-mail.');
        $message .= '<br> ';

        if (!$messagePostfix) {
            $messagePostfix = __(
                'Please <a href="%1">Contact us</a> in case you don\'t receive the confirmation within %2 minutes.',
                $this->getMailTo(),
                $this->getFallbackTimeout()
            )->render();
        }

        $message .= $messagePostfix;

        return $message;
    }

    public function getIncrementId(): string
    {
        return $this->request->getParam('incrementId', '');
    }

    public function getMailTo(): string
    {
        $storeId = $this->getStoreId();

        // TODO: Add valid email address
        $sender = [
            'email' => 'worldline-connect-magento@support.com',
            'name'  => 'Worldline Connect Support'
        ];
        //$sender = $this->orderSynchronizationConfig->getRefusedPaymentSender($storeId);

        $result = $this->senderResolver->resolve($sender, $storeId);

        return 'mailto:' . $result['email'] . '?subject=' . __('Order %1 - Check the payment', $this->getIncrementId());
    }

    public function getFallbackTimeout(): int
    {
        return 20;
        //return $this->orderSynchronizationConfig->getFallbackTimeout($this->getStoreId());
    }

    public function getMainPageUrl(): string
    {
        return $this->urlBuilder->getUrl();
    }

    private function getStoreId(): int
    {
        return (int)$this->storeManager->getStore()->getId();
    }
}
