<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\MethodSpecificInput\Card;

use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Model\Config\Source\ExemptionRequest;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Sdk\V1\Domain\ThreeDSecureBase;
use Worldline\Connect\Sdk\V1\Domain\ThreeDSecureBaseFactory;

class ThreeDSecureBaseBuilder
{
    public const AUTHENTICATION_FLOW_BROWSER = 'browser';

    public function __construct(
        private readonly ThreeDSecureBaseFactory $threeDSecureBaseFactory,
        private readonly ConfigInterface $config,
    ) {
    }

    public function create(Payment $payment): ThreeDSecureBase
    {
        $threeDSecure = $this->threeDSecureBaseFactory->create();
        $threeDSecure->authenticationFlow = self::AUTHENTICATION_FLOW_BROWSER;

        $requestExemptions = $this->config->get3DSRequestExemptions();
        $threeDSecure->exemptionRequest = $requestExemptions;
        $threeDSecure->transactionRiskLevel = $requestExemptions === ExemptionRequest::AUTOMATIC ? '' : null;

        return $threeDSecure;
    }
}
