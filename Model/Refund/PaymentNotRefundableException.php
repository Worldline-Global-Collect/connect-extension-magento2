<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Refund;

use Magento\Framework\Exception\LocalizedException;

use function __;

/**
 * Thrown when a credit memo is created for a Worldline payment that the platform does not
 * (yet) accept a refund for - typically because the capture has been requested but not
 * settled yet. Refunding only becomes possible once the payment reports isRefundable.
 */
class PaymentNotRefundableException extends LocalizedException
{
    public static function create(string $worldlinePaymentId): self
    {
        return new self(__(
            'The Worldline payment %1 is not refundable yet. '
            . 'Please try again later.',
            $worldlinePaymentId
        ));
    }
}
