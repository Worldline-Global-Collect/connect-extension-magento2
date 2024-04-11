<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Payment;

use Magento\Sales\Model\Order;
use Worldline\Connect\Sdk\V1\Domain\Payment;

// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface ResolverInterface
{
    public function resolve(Order $order, Payment $payment);
}
