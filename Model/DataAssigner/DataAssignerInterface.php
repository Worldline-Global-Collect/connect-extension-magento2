<?php
declare(strict_types=1);

namespace Worldline\Connect\Model\DataAssigner;

use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Assigner interface for quote payment data
 */
interface DataAssignerInterface
{
    public function assign(
        PaymentInterface $payment,
        array $additionalInformation
    ): void;
}
