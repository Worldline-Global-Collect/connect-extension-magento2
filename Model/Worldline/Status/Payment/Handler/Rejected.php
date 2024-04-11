<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Payment\Handler;

use Magento\Sales\Model\Order;
use Worldline\Connect\Model\Worldline\Status\Payment\HandlerInterface;
use Worldline\Connect\Sdk\V1\Domain\Payment;

class Rejected extends AbstractHandler implements HandlerInterface
{
    protected const EVENT_STATUS = 'rejected';

    /**
     * {@inheritDoc}
     */
    public function resolveStatus(Order $order, Payment $status)
    {
        /** @var Order\Payment $orderPayment */
        $orderPayment = $order->getPayment();
        $orderPayment->setIsTransactionClosed(true);
        $orderPayment->setData('is_transaction_denied', true);
        $orderPayment->update();

        $this->dispatchEvent($order, $status);
    }
}
