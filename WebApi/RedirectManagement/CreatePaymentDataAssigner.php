<?php
declare(strict_types=1);

namespace Worldline\Connect\WebApi\RedirectManagement;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\PaymentInterface;
use Worldline\Connect\Api\TokenManagerInterface;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\DataAssigner\DataAssignerInterface;
use Worldline\Connect\Model\Worldline\Action\CreateHostedCheckout;
use Worldline\Connect\Model\Worldline\Action\CreatePayment;
use Worldline\Connect\PaymentMethod\PaymentMethods;

class CreatePaymentDataAssigner implements DataAssignerInterface
{
    /**
     * @var CreateHostedCheckout
     */
    private $createRequest;

    /**
     * @var CreatePayment
     */
    private $createPayment;

    /**
     * @var TokenManagerInterface
     */
    private $tokenManager;

    /**
     * CreatePayment variants keyed by payment product group code.
     *
     * @var array<string, CreatePayment>
     */
    private $createPaymentActions;

    /**
     * @param array<string, CreatePayment> $createPaymentActions
     */
    public function __construct(
        CreateHostedCheckout $createRequest,
        CreatePayment $createPayment,
        TokenManagerInterface $tokenManager,
        array $createPaymentActions = []
    ) {
        $this->createRequest = $createRequest;
        $this->createPayment = $createPayment;
        $this->tokenManager = $tokenManager;
        $this->createPaymentActions = $createPaymentActions;
    }

    /**
     * Assign return and payment id and identify redirect url
     *
     * @param PaymentInterface $payment
     * @param array $additionalInformation
     *
     * @return void
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function assign(
        PaymentInterface $payment,
        array $additionalInformation
    ): void {
        $quote = $payment->getQuote();

        $token = $this->tokenManager->getToken($quote);
        if ($token && $this->tokenManager->isSepaToken($token)) {
            return;
        }
        $requiresApproval = $payment->getMethodInstance()->getConfigData('capture_config') === AbstractMethod::ACTION_AUTHORIZE;

        $flow = $additionalInformation[Config::PAYMENT_FLOW_KEY]
            ?? $quote->getPayment()->getAdditionalInformation(Config::PAYMENT_FLOW_KEY);

        $action = $flow === 'inline' ? $this->resolveCreatePayment($payment) : $this->createRequest;
        $action->processNew($quote->getPayment(), $requiresApproval);
    }

    /**
     * The inline flow must use the CreatePayment variant matching the method's payment product
     * group. Sending a redirect product (e.g. Wero 900) through the card request builder makes
     * Worldline reject the request with 1401 PAYMENT_PRODUCT_ID_INVALID_FOR_PAYMENT_METHOD,
     * because the id ends up in cardPaymentMethodSpecificInput instead of
     * redirectPaymentMethodSpecificInput.
     */
    private function resolveCreatePayment(PaymentInterface $payment): CreatePayment
    {
        $group = PaymentMethods::getPaymentGroup((string) $payment->getMethod());
        if ($group === null) {
            return $this->createPayment;
        }

        return $this->createPaymentActions[$group] ?? $this->createPayment;
    }
}
