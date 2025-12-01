<?php

namespace Worldline\Connect\WebApi\Checkout;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Worldline\Connect\Api\QuoteManagerInterface;
use Worldline\Connect\Api\SessionManagerInterface;
use Worldline\Connect\Api\WorldlineStatusCheckerInterface;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\Order\OrderServiceInterface;
use Worldline\Connect\Model\Order\RejectOrderException;
use Worldline\Connect\Model\Worldline\Action\GetHostedCheckoutStatus;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\StatusInterface;
use Worldline\Connect\Sdk\V1\Domain\GetHostedCheckoutResponse;
use Worldline\Connect\Sdk\V1\ResponseException;

class WorldlineStatusChecker implements WorldlineStatusCheckerInterface
{
    public const SUCCESS_URL = 'checkout/onepage/success';
    public const REJECT_URL = 'epayments/returns/reject';
    public const WAITING_URL = 'epayments/returns/waiting';

    /**
     * @var QuoteManagerInterface
     */
    private $quoteManager;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var OrderServiceInterface
     */
    private $orderService;

    /**
     * @var SessionManagerInterface
     */
    private $sessionDataManager;

    /**
     * @param ClientInterface $client
     * @param QuoteManagerInterface $quoteManager
     * @param OrderServiceInterface $orderService
     * @param SessionManagerInterface $sessionDataManager
     */
    public function __construct(
        ClientInterface $client,
        QuoteManagerInterface $quoteManager,
        OrderServiceInterface $orderService,
        SessionManagerInterface $sessionDataManager
    )
    {
        $this->client = $client;
        $this->quoteManager = $quoteManager;
        $this->orderService = $orderService;
        $this->sessionDataManager = $sessionDataManager;
    }

    /**
     * Fetches and checks Worldline Status, and creates Magento order
     *
     * @param CartInterface $quote
     * @param string $hostedCheckoutId
     * @param bool $createPending Create Magento order in the 'Pending' status for uncertain transactions
     *
     * @return string
     *
     * @throws LocalizedException
     */
    public function processQuote(CartInterface $quote, string $hostedCheckoutId, bool $createPending = false): string
    {
        /** @var GetHostedCheckoutResponse $getHostedCheckoutResponse */
        $getHostedCheckoutResponse = $this->client->getHostedCheckout($hostedCheckoutId);

        if (!$getHostedCheckoutResponse || !$getHostedCheckoutResponse->status) {
            throw new LocalizedException(__('The payment has failed. Please, try again'));
        }

        try {
            $paymentId = $this->getWorldlinePaymentId($quote, $getHostedCheckoutResponse);

            $worldlinePayment = $this->client->worldlinePayment($paymentId);
            $status = $worldlinePayment->status;

            if (in_array($status, StatusInterface::APPROVED_STATUSES, true)) {
                $order = $this->orderService->createOrderAndProcessStatus($quote, $worldlinePayment);
                $this->sessionDataManager->setOrderData($order);

                return self::SUCCESS_URL;
            }

            if (in_array($status, StatusInterface::DENIED_STATUSES, true)) {
                return self::REJECT_URL;
            }

            if ($createPending && in_array($status, StatusInterface::UNCERTAIN_STATUSES, true)) {
                $order = $this->orderService->createOrderAndProcessStatus($quote, $worldlinePayment);

                if (in_array($status, StatusInterface::SUCCESS_UNCERTAIN_STATUSES, true)) {
                    $this->sessionDataManager->setOrderData($order);

                    return self::SUCCESS_URL;
                }
            }

            return self::WAITING_URL;
        } catch (ResponseException $exception) {
            throw new LocalizedException(__('The payment has failed. Please, try again'));
        }
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     * @throws CouldNotSaveException
     */
    public function processUncertainQuote(CartInterface $quote): OrderInterface
    {
        $paymentId = $this->getWorldlinePaymentId($quote);

        if (empty($paymentId)) {
            throw new LocalizedException(__('The Worldline payment does not exist.'));
        }

        $worldlinePayment = $this->client->worldlinePayment($paymentId);

        return $this->orderService->createOrderAndProcessStatus($quote, $worldlinePayment, true);
    }

    /**
     * @param CartInterface $quote
     * @param string $hostedCheckoutId
     *
     * @return string
     *
     * @throws LocalizedException
     * @throws RejectOrderException
     */
    public function isCancelled(CartInterface $quote, string $hostedCheckoutId): string
    {
        /** @var GetHostedCheckoutResponse $getHostedCheckoutResponse */
        $getHostedCheckoutResponse = $this->client->getHostedCheckout($hostedCheckoutId);

        if (!$getHostedCheckoutResponse || !$getHostedCheckoutResponse->status) {
            throw new LocalizedException(__('The payment has failed. Please, try again'));
        }

        if (GetHostedCheckoutStatus::CANCELLED_BY_CONSUMER === $getHostedCheckoutResponse->status) {
            throw new RejectOrderException(__('The payment has rejected, please, try again'));
        }

        $payment = $quote->getPayment();
        if ($getHostedCheckoutResponse->createdPaymentOutput) {
            $payment->setAdditionalInformation(Config::PAYMENT_ID_KEY, $getHostedCheckoutResponse->createdPaymentOutput->payment->id);
            $payment->setAdditionalInformation(Config::PAYMENT_STATUS_KEY, $getHostedCheckoutResponse->createdPaymentOutput->payment->status);
        }

        $this->quoteManager->save($quote);

        return (string)$quote->getReservedOrderId();
    }

    /**
     * @param CartInterface $quote
     * @param GetHostedCheckoutResponse|null $hostedCheckoutResponse
     *
     * @return string
     *
     * @throws LocalizedException
     */
    private function getWorldlinePaymentId(CartInterface $quote, ?GetHostedCheckoutResponse $hostedCheckoutResponse = null): string
    {
        $paymentId = $quote->getPayment()->getAdditionalInformation(Config::PAYMENT_ID_KEY);

        if ($paymentId) {
            return $paymentId;
        }

        if (!$hostedCheckoutResponse) {
            $hostedCheckoutId = $quote->getPayment()->getAdditionalInformation(Config::HOSTED_CHECKOUT_ID_KEY);
            $hostedCheckoutResponse = $this->client->getHostedCheckout($hostedCheckoutId);
        }

        $paymentId = $hostedCheckoutResponse->createdPaymentOutput
            ? $hostedCheckoutResponse->createdPaymentOutput->payment->id : '';

        $payment = $quote->getPayment();
        $payment->setAdditionalInformation(Config::PAYMENT_ID_KEY, $paymentId);
        $this->quoteManager->save($quote);

        return $paymentId;
    }
}
