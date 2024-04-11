<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Refund;

use Magento\Sales\Model\Order;
use Worldline\Connect\Sdk\V1\Domain\RefundResult;

/**
 * Interface HandlerInterface
 */
// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface HandlerInterface
{
    public function resolveStatus(Order $order, RefundResult $status);
}
