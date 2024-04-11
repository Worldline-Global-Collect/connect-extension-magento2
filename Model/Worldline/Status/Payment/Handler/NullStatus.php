<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Payment\Handler;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Worldline\Connect\Model\Worldline\Status\Payment\HandlerInterface;
use Worldline\Connect\Sdk\V1\Domain\Payment;

class NullStatus implements HandlerInterface
{
    /**
     * {@inheritDoc}
     */
    public function resolveStatus(Order $order, Payment $payment)
    {
        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        throw new LocalizedException(__('Status is not implemented'));
    }
}
