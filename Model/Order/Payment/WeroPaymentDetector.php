<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Order\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Api\OrderPaymentManagementInterface;
use Worldline\Connect\PaymentMethod\PaymentMethods;

/**
 * Single source of truth for "was this order paid with Wero?".
 *
 * Both the credit memo block and the credit memo observer ask this service, so the render
 * condition and the server-side guard can never drift apart.
 *
 * Two orders qualify:
 *  - the Magento method code is worldline_wero (Wero picked in the Magento checkout);
 *  - the payment product Worldline actually processed is the Wero product, which is how a
 *    Hosted Checkout order looks when the shopper picks Wero on the Worldline page - there the
 *    method code is worldline_hpp and says nothing about the product.
 */
class WeroPaymentDetector
{
    public function __construct(
        private readonly OrderPaymentManagementInterface $orderPaymentManagement,
        private readonly PaymentHelper $paymentHelper
    ) {
    }

    public function isWeroPayment(Payment $payment): bool
    {
        if ($payment->getMethod() === PaymentMethods::WERO) {
            return true;
        }

        $weroProductId = $this->getWeroProductId();
        if ($weroProductId === null) {
            return false;
        }

        return $this->orderPaymentManagement->getWorldlinePaymentProductId($payment) === $weroProductId;
    }

    /**
     * Read from configuration rather than hard-coding 900, so a merchant override is honoured.
     *
     * @see \Worldline\Connect\PaymentMethod\PaymentMethods::getAvailablePaymentProductIds()
     */
    private function getWeroProductId(): ?int
    {
        try {
            $configured = $this->paymentHelper->getMethodInstance(PaymentMethods::WERO)->getConfigData('product_id');
        } catch (LocalizedException) {
            return null;
        }

        return $configured !== null && $configured !== '' && $configured !== false ? (int) $configured : null;
    }
}
