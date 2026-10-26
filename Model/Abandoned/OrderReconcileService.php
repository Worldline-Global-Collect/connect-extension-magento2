<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Abandoned;

use DateTime;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Throwable;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\PaymentMethod\PaymentMethods;

use function sprintf;
use function str_starts_with;

class OrderReconcileService implements OrderReconcileServiceInterface
{
    /**
     * Only orders created inside this window are reconciled.
     *
     * The age limit deliberately bounds the retry loop. An order whose outcome can never be
     * determined (a Worldline response without paymentOutput makes GetInlinePaymentStatus::process()
     * throw, so the payment service returns OUTCOME_UNKNOWN on every run) drops out of the batch
     * after a day instead of being re-queried by every cron run forever.
     */
    private const MAX_ORDER_AGE = '-24 hours';

    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly AbandonedPaymentServiceInterface $abandonedPaymentService,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(): void
    {
        foreach ($this->getStuckOrders() as $order) {
            try {
                if (!$this->isWorldlineOrder($order) || !$this->hasGracePeriodExpired($order)) {
                    continue;
                }

                $outcome = $this->abandonedPaymentService->resolve($order);
                if ($outcome !== AbandonedPaymentServiceInterface::OUTCOME_UNKNOWN) {
                    $this->logger->info(sprintf(
                        'Worldline: abandoned payment of order %s resolved as %s.',
                        (string) $order->getIncrementId(),
                        $outcome
                    ));
                }
            } catch (Throwable $exception) {
                // One unresolvable order must never abort the rest of the batch.
                $this->logger->error(sprintf(
                    'Worldline: unable to reconcile abandoned order %s: %s',
                    (string) $order->getIncrementId(),
                    $exception->getMessage()
                ));
            }
        }
    }

    /**
     * Orders that are still awaiting a payment, recent enough to be worth resolving and older than
     * the shortest grace period any store can be configured with.
     *
     * The exact, store-scoped grace period is applied afterwards in hasGracePeriodExpired(): the
     * setting is per store while this query spans all of them, so the coarse bound here uses the
     * hard minimum, which no store can undercut.
     *
     * @return list<Order>
     */
    private function getStuckOrders(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderInterface::STATE, Order::STATE_PENDING_PAYMENT)
            ->addFilter(OrderInterface::CREATED_AT, $this->pointInTime(self::MAX_ORDER_AGE), 'gteq')
            ->addFilter(OrderInterface::UPDATED_AT, $this->minimumGracePeriodCutOff(), 'lteq')
            ->create();

        /** @var list<Order> $orders */
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        return $orders;
    }

    /**
     * The order payment is loaded per order rather than joined into the query above: the payment
     * method lives in sales_order_payment, and the state plus 24 hour bounds already keep the
     * result set to the handful of orders that are actually awaiting a payment right now.
     *
     * @param Order $order
     * @return bool
     */
    private function isWorldlineOrder(Order $order): bool
    {
        $payment = $order->getPayment();

        return $payment !== null && str_starts_with((string) $payment->getMethod(), PaymentMethods::PREFIX);
    }

    /**
     * @param Order $order
     * @return bool
     */
    private function hasGracePeriodExpired(Order $order): bool
    {
        $gracePeriod = $this->config->getAbandonedPaymentGracePeriod((int) $order->getStoreId());

        return (string) $order->getUpdatedAt() <= $this->pointInTime(sprintf('-%d minutes', $gracePeriod));
    }

    /**
     * @return string
     */
    private function minimumGracePeriodCutOff(): string
    {
        return $this->pointInTime(
            sprintf('-%d minutes', Config::ABANDONED_PAYMENT_GRACE_PERIOD_MINIMUM)
        );
    }

    /**
     * @param string $modifier
     * @return string
     */
    private function pointInTime(string $modifier): string
    {
        return (new DateTime($modifier))->format(self::DATE_FORMAT);
    }
}
