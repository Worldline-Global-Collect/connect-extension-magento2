<?php

declare(strict_types=1);

namespace Worldline\Connect\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Throwable;
use Worldline\Connect\Model\Abandoned\AbandonedPaymentServiceInterface;
use Worldline\Connect\Model\Abandoned\AbandonedPaymentSession;

use function __;
use function sprintf;

/**
 * Events controller_action_predispatch_checkout_cart_index
 * and controller_action_predispatch_checkout_index_index
 *
 * Consumes the marker left by the controller that handed the customer over to the payment page.
 * In the "order creation before redirection" flow a customer who abandons the payment page or the
 * 3DS/ACS challenge never reaches a controller of this module again, so this is the first moment
 * at which the abandoned payment can be resolved and the cart given back.
 *
 * There is deliberately no grace period here: the back button happens within seconds, and any
 * delay would defeat the scenario this exists for. The accepted consequence is that a customer who
 * opens the cart in a second tab while the ACS challenge is still open gets that payment cancelled.
 */
class RecoverAbandonedPayment implements ObserverInterface
{
    public function __construct(
        private readonly AbandonedPaymentSession $abandonedPaymentSession,
        private readonly AbandonedPaymentServiceInterface $abandonedPaymentService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        $orderId = $this->abandonedPaymentSession->getAwaitingOrderId();
        if ($orderId === null) {
            // Hot path: no payment is awaiting a return, so this costs one session read.
            return;
        }

        try {
            $this->recover($orderId);
        } catch (Throwable $exception) {
            // This runs on predispatch of the cart and the checkout page; it may never break them.
            $this->logger->error(sprintf(
                'Worldline: could not recover the abandoned payment of order %d: %s',
                $orderId,
                $exception->getMessage()
            ));
        }
    }

    /**
     * @param int $orderId
     * @return void
     */
    private function recover(int $orderId): void
    {
        try {
            /** @var Order $order */
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException) {
            // The order no longer exists, so the marker can never be resolved.
            $this->abandonedPaymentSession->clear();

            return;
        }

        $outcome = $this->abandonedPaymentService->resolve($order);
        if ($outcome === AbandonedPaymentServiceInterface::OUTCOME_UNKNOWN) {
            // A transient API failure or a payment that is still live: keep the marker so that the
            // next request retries, rather than silently losing the cart.
            return;
        }

        $this->abandonedPaymentSession->clear();

        if ($outcome === AbandonedPaymentServiceInterface::OUTCOME_COMPLETED) {
            // The payment succeeded: the empty cart is correct and the order must not be touched.
            return;
        }

        $this->restoreCart($orderId);
    }

    /**
     * Give the cart back for an order whose payment was denied or abandoned.
     *
     * @param int $orderId
     * @return void
     */
    private function restoreCart(int $orderId): void
    {
        // restoreQuote() works off the session's last real order, which is not necessarily the one
        // the marker names: a marker kept alive by an UNKNOWN outcome can outlive a later, healthy
        // checkout, and restoring then would replace that order's quote and unset its last real
        // order id. The abandoned order has been cancelled either way, which is the part that
        // matters, so a stale cart is the lesser harm.
        if ((int) $this->checkoutSession->getLastRealOrder()->getId() !== $orderId) {
            $this->logger->notice(sprintf(
                'Worldline: order %d was resolved as abandoned, but the checkout session has since '
                . 'moved on to another order; the cart was not restored.',
                $orderId
            ));

            return;
        }

        $this->checkoutSession->restoreQuote();
    }
}
