<?php

declare(strict_types=1);

namespace Worldline\Connect\Controller\HostedCheckoutPage;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\Order\OrderServiceInterface;
use Worldline\Connect\Model\Worldline\Action\GetHostedCheckoutStatus;
use Worldline\Connect\Model\Worldline\Action\GetInlinePaymentStatus;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Sdk\V1\Domain\GetHostedCheckoutResponse;

use function __;

class ProcessReturn extends Action
{
    /**
     * @var Session
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $checkoutSession;

    /**
     * @var GetHostedCheckoutStatus
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $checkoutStatus;

    /**
     * @var LoggerInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $logger;
    private ClientInterface $client;
    private GetInlinePaymentStatus $inlinePaymentStatus;
    private OrderServiceInterface $orderService;

    public function __construct(
        Context $context,
        Session $checkoutSession,
        GetHostedCheckoutStatus $checkoutStatus,
        GetInlinePaymentStatus $inlinePaymentStatus,
        LoggerInterface $logger,
        ClientInterface $client,
        OrderServiceInterface $orderService
    ) {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->checkoutStatus = $checkoutStatus;
        $this->logger = $logger;
        $this->client = $client;
        $this->inlinePaymentStatus = $inlinePaymentStatus;
        $this->orderService = $orderService;
    }

    /**
     * Executes when a customer returns from Hosted Checkout
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();
            $getHostedCheckoutResponse = $this->client->getHostedCheckout($this->retrieveHostedCheckoutId($order));
            $this->checkoutStatus->process($order, $getHostedCheckoutResponse);
            $this->handleInlinePaymentStatus($order, $getHostedCheckoutResponse);
            $this->orderService->save($order);

            // Handle order cancellation:
            if ($order->getState() === Order::STATE_CANCELED) {
                $this->messageManager->addNoticeMessage(
                    __('You cancelled the payment. Please select a different payment option and place your order again')
                        ->render()
                );
                $this->checkoutSession->restoreQuote();
                return $this->redirect('checkout/cart');
            }

            return $this->redirect('checkout/onepage/success');
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->error($e->getMessage());
            $this->checkoutSession->restoreQuote();

            return $this->redirect('checkout/cart');
        }
    }

    /**
     * Return redirect object
     *
     * @param $url
     * @return ResultInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    private function redirect($url)
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath($url);

        return $resultRedirect;
    }

    /**
     * Load hosted checkout id from request param or fall back to session
     *
     * @return string
     * @throws NotFoundException
     */
    private function retrieveHostedCheckoutId(Order $order)
    {
        $hostedCheckoutId = $this->getRequest()->getParam('hostedCheckoutId', false);
        if ($hostedCheckoutId === false && $this->checkoutSession->getLastRealOrder()->getPayment() !== null) {
            /** @var Order\Payment $payment */
            $payment = $order->getPayment();
            $hostedCheckoutId = $payment->getAdditionalInformation(Config::HOSTED_CHECKOUT_ID_KEY);
        }

        // $hostedCheckoutId can be false or null in error case
        if (!$hostedCheckoutId) {
            throw new NotFoundException(__('Could not retrieve payment status.'));
        }

        return $hostedCheckoutId;
    }

    private function handleInlinePaymentStatus(Order $order, GetHostedCheckoutResponse $getHostedCheckoutResponse): void
    {
        $createdPaymentOutput = $getHostedCheckoutResponse->createdPaymentOutput;
        if ($createdPaymentOutput === null) {
            return;
        }

        $payment = $createdPaymentOutput->payment;
        if ($payment === null) {
            return;
        }

        $this->inlinePaymentStatus->process($order, $payment);
    }
}
