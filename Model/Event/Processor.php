<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Event;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Worldline\Connect\Api\Data\EventInterface;
use Worldline\Connect\Api\EventRepositoryInterface;
use Worldline\Connect\Model\Order\OrderServiceInterface;
use Worldline\Connect\Model\Order\SkipOrderCreationException;
use Worldline\Connect\Model\Quote\QuoteServiceInterface;
use Worldline\Connect\Model\Worldline\Status\Payment\ResolverInterface as PaymentResolverInterface;
use Worldline\Connect\Model\Worldline\Status\Refund\ResolverInterface as RefundResolverInterface;
use Worldline\Connect\Sdk\V1\Domain\PaymentResponse;
use Worldline\Connect\Sdk\V1\Domain\WebhooksEvent;
use Worldline\Connect\Sdk\V1\Domain\WebhooksEventFactory;

use function sprintf;
use function str_starts_with;

class Processor
{
    public const MESSAGE_NO_ORDER_FOUND = 'webhook: no order found';
    public const MESSAGE_NO_QUOTE_FOUND = 'webhook: no quote found';

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
        QuoteServiceInterface $quoteService
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
                $this->logger->info('Could not process event', [
                    'event_id' => $event->getId(),
                    'exception' => $exception->getMessage(),
                ]);
                $event->setStatus(EventInterface::STATUS_IGNORED);
                $this->eventRepository->save($event);
            } catch (Throwable $exception) {
                $this->logger->warning('Could not process event', [
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
        $payment = $webhookEvent->payment;
        $merchantReference = $payment->paymentOutput->references->merchantReference;

        if (str_contains($merchantReference, 'Quote-')) {
            $merchantReference = $this->handleNewFlow($payment);
        }

        /** @var Order $order */
        $order = $this->orderService->getByIncrementId($merchantReference);

        if (!$order) {
            throw new LocalizedException(__(self::MESSAGE_NO_ORDER_FOUND));
        }

        try {
            $this->paymentResolver->resolve($order, $payment);
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (Throwable) {
        }

        return $order;
    }

    /**
     * @param PaymentResponse $payment
     *
     * @return string|null
     *
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws SkipOrderCreationException
     */
    private function handleNewFlow(PaymentResponse $payment): ?string
    {
        $merchantReference = $payment->paymentOutput->references->merchantReference;
        $quoteId = str_replace('Quote-', '', $merchantReference);

        $quote = $this->quoteService->getQuoteById((int)$quoteId);

        if (!$quote) {
            throw new LocalizedException(__(self::MESSAGE_NO_QUOTE_FOUND));
        }

        $order = $this->orderService->createOrderAsync($quote);

        return $order->getIncrementId();
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
        $merchantReference = $refund->refundOutput->references->merchantReference;

        if (str_contains($merchantReference, 'Quote-')) {
            $quoteId = str_replace('Quote-', '', $merchantReference);
            $quote = $this->quoteService->getQuoteById((int)$quoteId);

            /** @var Order $order */
            $order = $this->orderService->getOrderByQuote($quote);
        } else {
            /** @var Order $order */
            $order = $this->orderService->getByIncrementId($refund->refundOutput->references->merchantReference);
        }

        if (!$order) {
            throw new LocalizedException(__(self::MESSAGE_NO_ORDER_FOUND));
        }

        try {
            $this->refundResolver->resolve($order, $refund);
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (Throwable) {
        }

        return $order;
    }
}
