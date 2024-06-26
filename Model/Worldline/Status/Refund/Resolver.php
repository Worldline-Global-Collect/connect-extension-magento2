<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Refund;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Model\Order\Payment\OrderPaymentManagement;
use Worldline\Connect\Model\StatusResponseManagerInterface;
use Worldline\Connect\Model\Worldline\Status\AbstractResolver;
use Worldline\Connect\Sdk\V1\Domain\RefundResult;

class Resolver extends AbstractResolver implements ResolverInterface
{
    protected const KEY_STATUS = OrderPaymentManagement::KEY_REFUND_STATUS;
    protected const KEY_STATUS_CODE_CHANGE_DATE_TIME = OrderPaymentManagement::KEY_REFUND_STATUS_CODE_CHANGE_DATE_TIME;

    /**
     * @var PoolInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $statusHandlerPool;

    public function __construct(
        StatusResponseManagerInterface $statusResponseManager,
        PoolInterface $statusHandlerPool,
    ) {
        parent::__construct($statusResponseManager);
        $this->statusHandlerPool = $statusHandlerPool;
    }

    public function resolve(Order $order, RefundResult $status)
    {
        if (!$this->isStatusNewerThanPreviousStatus($order, $status)) {
            return;
        }

        /** @var Payment $payment */
        $payment = $order->getPayment();
        $this->preparePayment($payment, $status);

        $statusHandler = $this->statusHandlerPool->get($status->status);
        $statusHandler->resolveStatus($order, $status);

        $this->updateStatusCodeChangeDate($order, $status);
        $this->updateStatus($order, $status);
        $this->updatePayment($payment, $status);
    }
}
