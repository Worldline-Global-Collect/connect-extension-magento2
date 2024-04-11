<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Payment\Handler;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Worldline\Connect\Model\Worldline\Status\Payment\HandlerInterface;
use Worldline\Connect\Sdk\V1\Domain\Payment;

/**
 * Class AuthorizationRequested
 *
 * @package Worldline\Connect\Model\Worldline\Status
 */
class AuthorizationRequested extends AbstractHandler implements HandlerInterface
{
    protected const EVENT_STATUS = 'authorization_requested';

    public function resolveStatus(Order $order, Payment $status)
    {
        /** @var OrderPayment $orderPayment */
        $orderPayment = $order->getPayment();
        $orderPayment->registerAuthorizationNotification($order->getBaseGrandTotal());

        $this->dispatchEvent($order, $status);
    }
}
