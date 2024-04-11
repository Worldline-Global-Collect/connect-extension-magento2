<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Sales\Model\Order;
use Worldline\Connect\Model\Order\Payment\OrderPaymentManagement;
use Worldline\Connect\Model\StatusResponseManagerInterface;
use Worldline\Connect\Model\Worldline\Status\AbstractResolver;
use Worldline\Connect\Sdk\V1\Domain\Payment;

class Resolver extends AbstractResolver implements ResolverInterface
{
    protected const KEY_STATUS = OrderPaymentManagement::KEY_PAYMENT_STATUS;
    protected const KEY_STATUS_CODE_CHANGE_DATE_TIME = OrderPaymentManagement::KEY_PAYMENT_STATUS_CODE_CHANGE_DATE_TIME;

    /**
     * @var PoolInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $statusHandlerPool;

    public function __construct(
        StatusResponseManagerInterface $statusResponseManager,
        PoolInterface $statusHandlerPool
    ) {
        parent::__construct($statusResponseManager);
        $this->statusHandlerPool = $statusHandlerPool;
    }

    /**
     * @param Order $order
     * @param Payment $payment
     * @throws LocalizedException
     * @throws NotFoundException
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
    public function resolve(Order $order, Payment $payment)
    {
        $statusChanged = false;
        if (!$this->isStatusNewerThanPreviousStatus($order, $payment)) {
            return false;
        }

        /** @var Order\Payment $orderPayment */
        $orderPayment = $order->getPayment();
        $this->preparePayment($orderPayment, $payment);

        // Only run the resolver on an actual status change, otherwise
        // only update the meta-data:
        $additionalInformation = $orderPayment->getAdditionalInformation();
        $currentStatus = $additionalInformation[self::KEY_STATUS] ?? null;
        if ($payment->status !== $currentStatus) {
            $statusHandler = $this->statusHandlerPool->get($payment->status);
            $statusHandler->resolveStatus($order, $payment);

            $statusChanged = true;
        }

        $this->updateStatusCodeChangeDate($order, $payment);
        $this->updateStatus($order, $payment);
        $this->updatePayment($orderPayment, $payment);

        return $statusChanged;
    }
}
