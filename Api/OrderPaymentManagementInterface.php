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
}
