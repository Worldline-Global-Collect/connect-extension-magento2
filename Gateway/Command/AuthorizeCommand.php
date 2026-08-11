<?php

declare(strict_types=1);

namespace Worldline\Connect\Gateway\Command;

use LogicException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\Worldline\Action\CreateHostedCheckout;
use Worldline\Connect\Model\Worldline\Action\CreatePayment;

class AuthorizeCommand implements CommandInterface
{
    public function __construct(
        private readonly CreatePayment $createPayment,
        private readonly CreateHostedCheckout $createHostedCheckout,
        private readonly ConfigInterface $config
    ) {
    }

    public function execute(array $commandSubject): mixed
    {
        /** @var Payment $payment */
        $payment = $commandSubject['payment']->getPayment();

        if ($this->reusesExistingPayment($payment)) {
            return null;
        }

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

    /**
     * In the "after redirection" flow the Worldline payment / hosted checkout is already created
     * (by CreatePayment::processNew) before the order is placed, so this command must not create a
     * second one. In the "before" flow it must always create a fresh payment — otherwise a restored
     * cart carrying stale worldline ids from a previous (declined) attempt would skip the HPP/3DS
     * redirect and silently place a Processing order without a real transaction.
     */
    private function reusesExistingPayment(Payment $payment): bool
    {
        $storeId = (int) $payment->getOrder()->getStoreId();
        if ($this->config->getOrderCreationFlow($storeId) !== $this->config->getOrderCreationFlowAfter()) {
            return false;
        }

        $existingPaymentId = $payment->getAdditionalInformation(Config::PAYMENT_ID_KEY);
        if ($existingPaymentId !== null && $existingPaymentId !== '') {
            $payment->setTransactionId((string) $existingPaymentId);
            // Keep the authorization transaction OPEN. Without this the row
            // takes the sales_payment_transaction.is_closed DB default (1), so the delayed
            // capture cron (which filters is_closed = 0) never sees it.
            $payment->setIsTransactionClosed(false);

            return true;
        }

        $existingHostedCheckoutId = $payment->getAdditionalInformation(Config::HOSTED_CHECKOUT_ID_KEY);
        if ($existingHostedCheckoutId !== null && $existingHostedCheckoutId !== '') {
            $payment->setTransactionId((string) $existingHostedCheckoutId);
            $payment->setIsTransactionClosed(false);

            return true;
        }

        return false;
    }
}
