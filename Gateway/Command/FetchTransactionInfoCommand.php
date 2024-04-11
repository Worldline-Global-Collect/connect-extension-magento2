<?php

declare(strict_types=1);

namespace Worldline\Connect\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\StatusInterface;

use function in_array;

class FetchTransactionInfoCommand implements CommandInterface
{
    public function __construct(
        private readonly ClientInterface $worldlineClient
    ) {
    }

    public function execute(array $commandSubject): mixed
    {
        /** @var Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        $worldlinePayment = $this->worldlineClient->worldlinePayment($commandSubject['transactionId']);
        if (in_array($worldlinePayment->status, StatusInterface::APPROVED_STATUSES, true)) {
            $payment->registerCaptureNotification($payment->getOrder()->getBaseGrandTotal());
            $payment->setData('is_transaction_approved', true);
        }

        if (in_array($worldlinePayment->status, StatusInterface::DENIED_STATUSES, true)) {
            $payment->setData('is_transaction_denied', true);
        }

        return null;
    }
}
