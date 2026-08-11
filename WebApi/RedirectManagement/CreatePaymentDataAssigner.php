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

    public function __construct(
        CreateHostedCheckout $createRequest,
        CreatePayment $createPayment,
        TokenManagerInterface $tokenManager
    ) {
        $this->createRequest = $createRequest;
        $this->createPayment = $createPayment;
        $this->tokenManager = $tokenManager;
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

        $action = $flow === 'inline' ? $this->createPayment : $this->createRequest;
        $action->processNew($quote->getPayment(), $requiresApproval);
    }
}
