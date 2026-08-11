<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Event;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Worldline\Connect\Api\Data\EventInterface;
use Worldline\Connect\Api\EventRepositoryInterface;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\Order\OrderServiceInterface;
use Worldline\Connect\Model\Order\SkipOrderCreationException;
use Worldline\Connect\Model\Quote\QuoteServiceInterface;
use Worldline\Connect\Model\Worldline\Status\Payment\ResolverInterface as PaymentResolverInterface;
use Worldline\Connect\Model\Worldline\Status\Refund\ResolverInterface as RefundResolverInterface;
use Worldline\Connect\Model\Worldline\StatusInterface;
use Worldline\Connect\Sdk\V1\Domain\PaymentResponse;
use Worldline\Connect\Sdk\V1\Domain\WebhooksEvent;
use Worldline\Connect\Sdk\V1\Domain\WebhooksEventFactory;

use function sprintf;
use function str_starts_with;

class Processor
{
    public const MESSAGE_NO_ORDER_FOUND = 'webhook: no order found';
    public const MESSAGE_NO_QUOTE_OR_ORDER_FOUND = 'webhook: no quote or order found';

    private const WEBHOOK_TYPE_PAYMENT_CREATED = 'payment.created';
    private const WEBHOOK_TYPE_PAYMENT_REDIRECTED = 'payment.redirected';

    /**
     * @var WebhooksEventFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $webhookEventFactory;

    /**
     * @var EventRepositoryInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $eventRepository;

    /**
     * @var OrderRepositoryInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $orderRepository;

    /**
     * @var SortOrderBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $sortOrderBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $searchCriteriaBuilder;

    /**
     * @var LoggerInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $logger;

    /**
     * @var OrderServiceInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $orderService;

    /**
     * @var PaymentResolverInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $paymentResolver;

    /**
     * @var RefundResolverInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $refundResolver;

    /**
     * @var QuoteServiceInterface
     */
    private $quoteService;

    /**
     * @var ConfigInterface
     */
    private $config;

    public function __construct(
        WebhooksEventFactory $webhookEventFactory,
        EventRepositoryInterface $eventRepository,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        LoggerInterface $logger,
        OrderServiceInterface $orderService,
        PaymentResolverInterface $paymentResolver,
        RefundResolverInterface $refundResolver,
        QuoteServiceInterface $quoteService,
        ConfigInterface $config
    ) {
        $this->webhookEventFactory = $webhookEventFactory;
        $this->eventRepository = $eventRepository;
        $this->orderRepository = $orderRepository;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
        $this->orderService = $orderService;
        $this->paymentResolver = $paymentResolver;
        $this->refundResolver = $refundResolver;
        $this->quoteService = $quoteService;
        $this->config = $config;
    }

    /**
     * @param int $limit
     * @throws LocalizedException
     */
    public function processBatch($limit = 20)
    {
        $this->processEvents($this->getEvents($limit));
    }

    /**
     * @param int $limit
     * @return array<EventInterface>
     */
    private function getEvents(int $limit): array
    {
        $this->searchCriteriaBuilder->addFilter(EventInterface::STATUS, [
            EventInterface::STATUS_NEW,
        ]);
        $this->searchCriteriaBuilder->setPageSize($limit);
        $this->searchCriteriaBuilder->addSortOrder(
            $this->sortOrderBuilder
                ->setField(EventInterface::CREATED_TIMESTAMP)
                ->setAscendingDirection()
                ->create()
        );

        return $this->eventRepository
            ->getList($this->searchCriteriaBuilder->create())
            ->getItems();
    }

    /**
     * @param array<EventInterface> $events
     * @throws CouldNotSaveException
     */
    private function processEvents(array $events): void
    {
        foreach ($events as $event) {
            try {
                $this->processEvent($event);
                $event->setStatus(EventInterface::STATUS_SUCCESS);
                $this->eventRepository->save($event);
                $this->logger->info('Processed event', [
                    'event_id' => $event->getId(),
                ]);
            } catch (SkipOrderCreationException $exception) {
                $this->logger->info('The event should be ignored', [
                    'event_id' => $event->getId(),
                    'exception' => $exception->getMessage(),
                ]);
                $event->setStatus(EventInterface::STATUS_IGNORED);
                $this->eventRepository->save($event);
            } catch (Throwable $exception) {
                $this->logger->warning('Could not process the event', [
                    'event_id' => $event->getId(),
                    'exception' => $exception->getMessage(),
                ]);
                $event->setStatus(EventInterface::STATUS_FAILED);
                $this->eventRepository->save($event);
            }
        }
    }

    /**
     * @param EventInterface $event
     *
     * @throws LocalizedException
     * @throws SkipOrderCreationException
     */
    private function processEvent(EventInterface $event): void
    {
        /** @var WebhooksEvent $webhookEvent */
        $webhookEvent = $this->webhookEventFactory->create()->fromJson($event->getPayload());
        if ($this->checkEndpointTest($webhookEvent)) {
            return;
        }

        if ($webhookEvent->payment !== null) {
            $order = $this->handlePaymentEvent($webhookEvent);
        } elseif ($webhookEvent->refund !== null) {
            $order = $this->handleRefundEvent($webhookEvent);
        } else {
            throw new RuntimeException(sprintf('Event type %s not supported.', $webhookEvent->type));
        }

        $order->addCommentToStatusHistory($event->getPayload());
        $order->setDataChanges(true);

        $this->orderRepository->save($order);
    }

    private function checkEndpointTest(WebhooksEvent $event): bool
    {
        return str_starts_with((string) $event->id, 'TEST');
    }

    /**
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws SkipOrderCreationException
     */
    private function handlePaymentEvent(WebhooksEvent $webhookEvent): Order
    {
        if ($this->shouldSkipInAfterFlow($webhookEvent)) {
            throw new SkipOrderCreationException(
                __('Event "%1" is ignored in the "order created after redirection" flow.', (string) $webhookEvent->type)
            );
        }

        $payment = $webhookEvent->payment;

        /** @var Order $order */
        $order = $this->getOrCreateOrder($payment);

        try {
            $magentoPayment = $order->getPayment();
            $magentoPayment->setLastTransId($payment->id);

            $this->paymentResolver->resolve($order, $payment);
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (Throwable) { }

        return $order;
    }

    /**
     * @param WebhooksEvent $webhookEvent
     *
     * @return Order
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function handleRefundEvent(WebhooksEvent $webhookEvent): Order
    {
        $refund = $webhookEvent->refund;

        try {
            /** @var Order $order */
            $order = $this->orderService->getByIncrementId($refund->refundOutput->references->merchantReference);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__(self::MESSAGE_NO_ORDER_FOUND));
        }

        try {
            $this->refundResolver->resolve($order, $refund);
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (Throwable) {
        }

        return $order;
    }

    /**
     * In the "order created after redirection" flow the order must not be created on the
     * early, intermediate webhook events (payment.created / payment.redirected); it is
     * created only on a real payment outcome. Such events are skipped (marked IGNORED).
     */
    private function shouldSkipInAfterFlow(WebhooksEvent $webhookEvent): bool
    {
        $type = (string) $webhookEvent->type;

        if (
            $type !== self::WEBHOOK_TYPE_PAYMENT_CREATED
            && $type !== self::WEBHOOK_TYPE_PAYMENT_REDIRECTED
        ) {
            return false;
        }

        $storeId = $this->resolveStoreId($webhookEvent);

        return $this->config->getOrderCreationFlow($storeId) === $this->config->getOrderCreationFlowAfter();
    }

    private function resolveStoreId(WebhooksEvent $webhookEvent): int
    {
        $merchantReference = $webhookEvent->payment->paymentOutput->references->merchantReference ?? null;

        if ($merchantReference !== null) {
            $quote = $this->quoteService->getQuoteByReservedOrderId($merchantReference);
            if ($quote !== null && $quote->getStoreId() !== null) {
                return (int) $quote->getStoreId();
            }
        }

        return 0;
    }

    /**
     * @param PaymentResponse $payment
     *
     * @return OrderInterface
     *
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws SkipOrderCreationException
     */
    private function getOrCreateOrder(PaymentResponse $payment): OrderInterface
    {
        $merchantReference = $payment->paymentOutput->references->merchantReference;

        $quote = $this->quoteService->getQuoteByReservedOrderId($merchantReference);

        if (!$quote) {
            // order creation before redirection flow
            return $this->skipOrderCreationAndReturnOrder($merchantReference);
        }

        try {
            $order = $this->orderService->getByIncrementId($merchantReference);
        } catch (NoSuchEntityException $e) {
            if (in_array($payment->status, StatusInterface::DENIED_STATUSES)) {
                throw new SkipOrderCreationException(__("Order should not be created for quote {$quote->getId()}. The payment was denied."));
            }

            // order creation after redirection flow (and payment status is not denied)
            $order = $this->orderService->createOrderAsync($quote, $payment);
        }

        return $order;
    }

    /**
     * @param string $merchantReference
     *
     * @return OrderInterface
     * @throws LocalizedException
     */
    private function skipOrderCreationAndReturnOrder(string $merchantReference): OrderInterface
    {
        try {
            return $this->orderService->getByIncrementId($merchantReference);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__(self::MESSAGE_NO_QUOTE_OR_ORDER_FOUND));
        }
    }
}
