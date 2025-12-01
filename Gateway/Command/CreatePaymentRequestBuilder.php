<?php

declare(strict_types=1);

namespace Worldline\Connect\Gateway\Command;

use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Quote\Model\Quote\Payment as QuotePayment;

interface CreatePaymentRequestBuilder
{
    public function build(OrderPayment $payment, bool $requiresApproval);

    public function buildNew(QuotePayment $payment, bool $requiresApproval);
}