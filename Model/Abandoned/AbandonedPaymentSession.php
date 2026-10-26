<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Abandoned;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Marks that the customer has been handed over to the payment page (or the 3DS ACS) for an order
 * that already exists in Magento ("order creation before redirection" flow).
 *
 * The customer can abandon that page with the browser back button, in which case no controller of
 * this module is ever called again. The marker survives in the checkout session so that the next
 * request reaching the shop can resolve the outcome of the abandoned payment.
 */
class AbandonedPaymentSession
{
    private CheckoutSession $checkoutSession;

    public function __construct(CheckoutSession $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Remember that the given order is waiting for a payment that is being completed off-site.
     *
     * @param OrderInterface $order
     * @return void
     */
    public function markAwaiting(OrderInterface $order): void
    {
        $this->checkoutSession->setWlAwaitingPaymentOrderId($order->getEntityId());
    }

    /**
     * Id of the order that is waiting for an off-site payment, or null when there is no marker.
     *
     * @return int|null
     */
    public function getAwaitingOrderId(): ?int
    {
        $orderId = $this->checkoutSession->getWlAwaitingPaymentOrderId();

        return $orderId ? (int) $orderId : null;
    }

    /**
     * Drop the marker. Called on every path on which the customer returns through our controllers.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->checkoutSession->setWlAwaitingPaymentOrderId(null);
    }
}
