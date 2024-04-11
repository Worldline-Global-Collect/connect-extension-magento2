<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Worldline\Connect\Sdk\V1\Domain\Payment;

/**
 * Interface HandlerInterface
 */
// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface HandlerInterface
{
    /**
     * @throws LocalizedException
     */
    public function resolveStatus(Order $order, Payment $status);
}
