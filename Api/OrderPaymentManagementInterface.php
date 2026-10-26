<?php

declare(strict_types=1);

namespace Worldline\Connect\Api;

use DateTime;
use LogicException;
use Magento\Sales\Model\Order\Payment;

// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface OrderPaymentManagementInterface
{
    /**
     * Get last known payment status received from the Worldline API.
     * This is the information that is stored in the order payment object
     * and does not do a new API call.
     *
     * @param Payment $payment
     * @return string
     * @throws LogicException
     * @api
     */
    public function getWorldlinePaymentStatus(Payment $payment): string;

    /**
     * Get last known refund status received from the Worldline API.
     * This is the information that is stored in the order payment object
     * and does not do a new API call.
     *
     * @param Payment $payment
     * @return string
     * @throws LogicException
     * @api
     */
    public function getWorldlineRefundStatus(Payment $payment): string;

    /**
     * Get last known payment status code change datetime.
     *
     * @param Payment $payment
     * @return DateTime
     * @throws LogicException
     * @api
     */
    public function getWorldlinePaymentStatusCodeChangeDate(Payment $payment): DateTime;

    /**
     * Get last known refund status code change datetime.
     *
     * @param Payment $payment
     * @return DateTime
     * @throws LogicException
     * @api
     */
    public function getWorldlineRefundStatusCodeChangeDate(Payment $payment): DateTime;

    /**
     * Get the payment product that Worldline actually processed the payment with.
     *
     * Read from the payment response stored on the transaction, so it also covers Hosted
     * Checkout orders where the shopper picked the product on the Worldline page and the
     * Magento method code says nothing about it. Does not do a new API call.
     *
     * @param Payment $payment
     * @return int|null Null when no payment product could be determined.
     * @api
     */
    public function getWorldlinePaymentProductId(Payment $payment): ?int;
}
