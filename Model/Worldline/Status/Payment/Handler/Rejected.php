<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Payment\Handler;

use Magento\Sales\Model\Order;
use Worldline\Connect\Model\Worldline\Status\Payment\HandlerInterface;
use Worldline\Connect\Sdk\V1\Domain\Payment;

class Rejected extends Cancelled implements HandlerInterface
{
    protected const EVENT_STATUS = 'rejected';

    /**
     * {@inheritDoc}
     *
     * REJECTED is functionally a cancellation from Magento's perspective — the order must end
     * in STATE_CANCELED. We cannot delegate to Payment::update() because Magento Core's
     * registerCancellation() refuses to cancel an order whose items are still pending invoice
     * (Order::canCancel() returns false), which is exactly the state of a PENDING_PAYMENT
     * order after a failed 3DS challenge. We therefore set the denied flags for audit and
     * reuse the manual cancellation flow from the Cancelled handler.
     */
    public function resolveStatus(Order $order, Payment $status)
    {
        $orderPayment = $order->getPayment();
        $orderPayment->setIsTransactionClosed(true);
        $orderPayment->setData('is_transaction_denied', true);

        parent::resolveStatus($order, $status);
    }
}
