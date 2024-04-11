<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Refund;

use Magento\Sales\Model\Order;
use Worldline\Connect\Sdk\V1\Domain\RefundResult;

// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface ResolverInterface
{
    public function resolve(Order $order, RefundResult $status);
}
