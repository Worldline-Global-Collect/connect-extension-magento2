<?php

declare(strict_types=1);

namespace Worldline\Connect\Observer\Adminhtml;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\Config\Source\RefundReason;
use Worldline\Connect\Model\Order\Payment\WeroPaymentDetector;

use function is_array;

/**
 * Copies the "Refund reason" chosen on the admin credit memo form onto the credit memo, so that
 * Gateway\Command\RefundCommand can read it off $payment->getCreditmemo().
 *
 * Bound to adminhtml_sales_order_creditmemo_register_before: the only point that has both the
 * credit memo object and the raw POST, and that runs before the controller triggers the refund.
 *
 * The value is request-scoped on purpose - sales_creditmemo has no column for it, so it is never
 * persisted.
 */
class SetCreditmemoRefundReason implements ObserverInterface
{
    public function __construct(
        private readonly WeroPaymentDetector $weroPaymentDetector
    ) {
    }

    public function execute(Observer $observer)
    {
        $creditMemo = $observer->getData('creditmemo');
        if (!$creditMemo instanceof Creditmemo || !$this->isWeroOrder($creditMemo)) {
            return;
        }

        $input = $observer->getData('input');
        $reason = is_array($input) ? (string) ($input[Config::REFUND_REASON_KEY] ?? '') : '';

        $creditMemo->setData(
            Config::REFUND_REASON_KEY,
            RefundReason::isValid($reason) ? $reason : RefundReason::DEFAULT_REASON
        );
    }

    private function isWeroOrder(Creditmemo $creditMemo): bool
    {
        $payment = $creditMemo->getOrder()->getPayment();

        return $payment instanceof Payment && $this->weroPaymentDetector->isWeroPayment($payment);
    }
}
