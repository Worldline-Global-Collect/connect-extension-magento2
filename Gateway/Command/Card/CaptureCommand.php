<?php

declare(strict_types=1);

namespace Worldline\Connect\Gateway\Command\Card;

use LogicException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\Worldline\Action\CapturePayment;
use Worldline\Connect\Model\Worldline\Action\CreateHostedCheckout;
use Worldline\Connect\Model\Worldline\Action\CreatePayment;

class CaptureCommand implements CommandInterface
{
    public function __construct(
        private readonly CapturePayment $capturePayment,
        private readonly CreatePayment $createPayment,
        private readonly CreateHostedCheckout $createHostedCheckout
    ) {
    }

    public function execute(array $commandSubject): mixed
    {
        /** @var Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        if ($payment->getLastTransId()) {
            $this->capturePayment->process($payment, $commandSubject['amount']);
            return null;
        }

        match ($payment->getMethodInstance()->getConfigData('payment_flow')) {
            Config::CONFIG_WORLDLINE_CHECKOUT_TYPE_OPTIMIZED_FLOW =>
                $this->createPayment->process($payment, false),
            Config::CONFIG_WORLDLINE_CHECKOUT_TYPE_HOSTED_CHECKOUT =>
                $this->createHostedCheckout->process($payment, false),
            default => throw new LogicException('Unknown payment flow'),
        };

        return null;
    }
}
