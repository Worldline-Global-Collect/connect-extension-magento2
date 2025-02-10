<?php

declare(strict_types=1);

namespace Worldline\Connect\Gateway\Command;

use LogicException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\Worldline\Action\CreateHostedCheckout;
use Worldline\Connect\Model\Worldline\Action\CreatePayment;

class AuthorizeCommand implements CommandInterface
{
    public function __construct(
        private readonly CreatePayment $createPayment,
        private readonly CreateHostedCheckout $createHostedCheckout
    ) {
    }

    public function execute(array $commandSubject): mixed
    {
        /** @var Payment $payment */
        $payment = $commandSubject['payment']->getPayment();

        match ($payment->getMethodInstance()->getConfigData('payment_flow')) {
            Config::CONFIG_WORLDLINE_CHECKOUT_TYPE_OPTIMIZED_FLOW =>
            $this->createPayment->process(
                $payment,
                $payment->getMethodInstance()->getConfigData('capture_config') === AbstractMethod::ACTION_AUTHORIZE
            ),
            Config::CONFIG_WORLDLINE_CHECKOUT_TYPE_HOSTED_CHECKOUT =>
            $this->createHostedCheckout->process(
                $payment,
                $payment->getMethodInstance()->getConfigData('capture_config') === AbstractMethod::ACTION_AUTHORIZE
            ),
            default => throw new LogicException('Unknown payment flow'),
        };

        return null;
    }
}
