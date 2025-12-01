<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Order;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Quote\Api\CartManagementInterface;
use \Magento\Quote\Model\CartLockedException;
use Psr\Log\LoggerInterface;
use Worldline\Connect\Api\QuoteManagerInterface;
use Worldline\Connect\Model\Worldline\EmailSender;
use Worldline\Connect\Model\Worldline\Status\Payment\ResolverInterface as PaymentResolverInterface;
use Worldline\Connect\Model\Worldline\StatusInterface;
use Worldline\Connect\Sdk\V1\Domain\Payment;
use Worldline\Connect\Model\Config;

class OrderService implements OrderServiceInterface
{
    /** @var int */
    private const MAX_RETRIES = 3;

    /**
     * @var OrderRepositoryInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $searchCriteriaBuilder;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $orderPaymentRepository;

    /**
     * @var CartManagementInterface
     */
    private $quoteManagement;

    /**
     * @var PaymentResolverInterface
     */
    private $paymentResolver;

    /**
     * @var QuoteManagerInterface
     */
    private $quoteManager;

    /**
     * @var EmailSender
     */
    private $emailSender;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CartManagementInterface $quoteManagement,
        PaymentResolverInterface $paymentResolver,
        QuoteManagerInterface $quoteManager,
        EmailSender $emailSender,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->quoteManagement = $quoteManagement;
        $this->paymentResolver = $paymentResolver;
        $this->quoteManager = $quoteManager;
        $this->emailSender = $emailSender;
        $this->logger = $logger;
    }

    /**
     * @param string $incrementId
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    public function getByIncrementId(string $incrementId): OrderInterface
    {
        return $this->getOrder(
            $this->searchCriteriaBuilder
                ->addFilter(OrderInterface::INCREMENT_ID, $incrementId)
                ->create()
        );
    }

    /**
     * @param string $hostedCheckoutId
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    public function getByHostedCheckoutId(string $hostedCheckoutId): OrderInterface
    {
        $payment = $this->getOrderPayment(
            $this->searchCriteriaBuilder
                ->addFilter(
                    OrderPaymentInterface::ADDITIONAL_INFORMATION,
                    // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
                    sprintf('%%"worldline_hosted_checkout_id":"%1$s"%%', $hostedCheckoutId),
                    'like'
                )
                ->create()
        );

        return $this->orderRepository->get($payment->getParentId());
    }

    /**
     * @inheritDoc
     */
    public function save(OrderInterface $order): void
    {
        $this->orderRepository->save($order);
    }

    /**
     * @inheritDoc
     */
    public function createOrder(CartInterface $quote, int $counter = 0): OrderInterface
    {
        try {
            $order = $this->getOrderByQuote($quote);
            if ($order) {
                return $order;
            }

            $this->quoteManager->activateQuote($quote);

            $orderId = $this->quoteManagement->placeOrder($quote->getId());

            $order = $this->orderRepository->get($orderId);
            $order->setState(Order::STATE_PENDING_PAYMENT);
            $order->setStatus(Order::STATE_PENDING_PAYMENT);

            $this->orderRepository->save($order);

            $this->emailSender->sendEmails($order, $quote);

            return $order;
        } catch (CartLockedException $e) {
            $this->logger->info("Unable to create order because quote {$quote->getId()} is locked. Some other process created the order.");
            if (++$counter < self::MAX_RETRIES) {
                sleep(1);
                return $this->createOrder($quote, $counter);
            }

            throw $e;
        } catch (NoSuchEntityException $e) {
            $this->logger->info("Unable to create order because quote {$quote->getId()} is no longer active. Some other process created the order.");
            if (++$counter < self::MAX_RETRIES) {
                sleep(1);
                return $this->createOrder($quote, $counter);
            }

            throw $e;
        } finally {
            $this->quoteManager->activateQuote($quote, false);
        }
    }

    /**
     * @inheritDoc
     */
    public function createOrderAsync(CartInterface $quote, Payment $worldlinePayment): OrderInterface
    {
        $this->checkAsyncOrderCreation($quote, $worldlinePayment);

        return $this->createOrder($quote);
    }

    /**
     * @inheritDoc
     */
    public function createOrderAndProcessStatus(CartInterface $quote, Payment $worldlinePayment, bool $async = false): OrderInterface
    {
        $payment = $quote->getPayment();
        $payment->setAdditionalInformation(Config::PAYMENT_ID_KEY, $worldlinePayment->id);
        $payment->setAdditionalInformation(Config::PAYMENT_STATUS_KEY, $worldlinePayment->status);
        $this->quoteManager->save($quote);

        if ($async) {
            /** @var Order $order */
            $order = $this->createOrderAsync($quote, $worldlinePayment);
        } else {
            /** @var Order $order */
            $order = $this->createOrder($quote);
        }

        $this->paymentResolver->resolve($order, $worldlinePayment);

        $order->addCommentToStatusHistory('Order created and status updated through redirection from Worldline to the Magento.');
        $order->setDataChanges(true);
        $this->orderRepository->save($order);

        return $order;
    }

    /**
     * @inheritDoc
     */
    public function getOrderByQuote(CartInterface $quote): ?OrderInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('quote_id', $quote->getId())
            ->setPageSize(1)
            ->create();

        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        if (empty($orders)) {
            return null;
        }

        return reset($orders);
    }

    /**
     * @inheritDoc
     */
    public function cancelledOrderExists(CartInterface $quote): bool
    {
        $order = $this->getOrderByQuote($quote);

        return $order && $order->getStatus() === Order::STATE_CANCELED;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    private function getOrder(SearchCriteriaInterface $searchCriteria): OrderInterface
    {
        $orderList = $this->orderRepository->getList($searchCriteria);
        if ($orderList->getTotalCount() === 0) {
            $this->throwException($searchCriteria);
        }

        $orders = $orderList->getItems();
        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        return $orders[key($orders)];
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return OrderPaymentInterface
     * @throws NoSuchEntityException
     */
    private function getOrderPayment(SearchCriteriaInterface $searchCriteria): OrderPaymentInterface
    {
        $orderPaymentList = $this->orderPaymentRepository->getList($searchCriteria);

        if ($orderPaymentList->getTotalCount() === 0) {
            $this->throwException($searchCriteria);
        }

        $orderPayments = $orderPaymentList->getItems();
        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        return $orderPayments[key($orderPayments)];
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @throws NoSuchEntityException
     */
    private function throwException(SearchCriteriaInterface $searchCriteria)
    {
        $filterGroups = $searchCriteria->getFilterGroups();
        $searchName = $filterGroups[0]->getFilters()[0]->getField();
        $searchValue = $filterGroups[0]->getFilters()[0]->getValue();
        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        throw new NoSuchEntityException(__('No order found with %1: %2', $searchName, $searchValue));
    }

    /**
     * @param CartInterface $quote
     * @param Payment $worldlinePayment
     *
     * @throws SkipOrderCreationException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function checkAsyncOrderCreation(CartInterface $quote, Payment $worldlinePayment): void
    {
        $status = $this->getPaymentStatus($quote, $worldlinePayment);

        $payment = $quote->getPayment();
        $timestamp = (int) $payment->getAdditionalInformation(
            Config::HOSTED_CHECKOUT_ID_TIMESTAMP_KEY
        );

        $order = $this->getOrderByQuote($quote);
        $pendingStatus = in_array($status, StatusInterface::UNCERTAIN_STATUSES, true)
            && !in_array($status, StatusInterface::SUCCESS_UNCERTAIN_STATUSES, true);
        $shouldSkip = empty($status) || ($pendingStatus && (time() - $timestamp < 3600));

        if (!$order && $shouldSkip) {
            throw new SkipOrderCreationException(__("Order should not be created for quote {$quote->getId()}. Hosted checkout session is still active."));
        }
    }

    /**
     * Fetches and updates worldline payment status on the quote
     *
     * @param CartInterface $quote
     * @param Payment $worldlinePayment
     *
     * @return string
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getPaymentStatus(CartInterface $quote, Payment $worldlinePayment): string
    {
        $payment = $quote->getPayment();
        $currentStatus = $payment->getAdditionalInformation(Config::PAYMENT_STATUS_KEY);

        $worldlineStatus = $worldlinePayment->status;
        if ($currentStatus !== $worldlineStatus) {
            $payment->setAdditionalInformation(Config::PAYMENT_STATUS_KEY, $worldlineStatus);
            $this->quoteManager->save($quote);
        }

        return $worldlineStatus;
    }
}
