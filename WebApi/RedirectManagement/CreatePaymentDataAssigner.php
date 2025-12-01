<?php
declare(strict_types=1);

namespace Worldline\Connect\WebApi\RedirectManagement;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\PaymentInterface;
use Worldline\Connect\Api\TokenManagerInterface;
use Worldline\Connect\Model\DataAssigner\DataAssignerInterface;
use Worldline\Connect\Model\Worldline\Action\CreateHostedCheckout;

class CreatePaymentDataAssigner implements DataAssignerInterface
{
    /**
     * @var CreateHostedCheckout
     */
    private $createRequest;

    /**
     * @var TokenManagerInterface
     */
    private $tokenManager;

    public function __construct(
        CreateHostedCheckout $createRequest,
        TokenManagerInterface $tokenManager
    ) {
        $this->createRequest = $createRequest;
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

        $this->createRequest->processNew($quote->getPayment(), $requiresApproval);
    }
}
