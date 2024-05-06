<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\MethodSpecificInput\Card;

use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Model\Config\Source\ExemptionRequest;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\Worldline\RequestBuilder\MethodSpecificInput\Card\ThreeDSecure\RedirectionDataBuilder;
use Worldline\Connect\Sdk\V1\Domain\ThreeDSecure;
use Worldline\Connect\Sdk\V1\Domain\ThreeDSecureFactory;

class ThreeDSecureBuilder
{
    public const AUTHENTICATION_FLOW_BROWSER = 'browser';

    public function __construct(
        private readonly ThreeDSecureFactory $threeDSecureFactory,
        private readonly RedirectionDataBuilder $redirectionDataBuilder,
        private readonly ConfigInterface $config,
    ) {
    }

    public function create(Payment $payment): ThreeDSecure
    {
        $threeDSecure = $this->threeDSecureFactory->create();
        $threeDSecure->redirectionData = $this->redirectionDataBuilder->create($payment);
        $threeDSecure->authenticationFlow = self::AUTHENTICATION_FLOW_BROWSER;

        $requestExemptions = $this->config->get3DSRequestExemptions();
        $threeDSecure->exemptionRequest = $requestExemptions;
        $threeDSecure->transactionRiskLevel = $requestExemptions === ExemptionRequest::AUTOMATIC ? '' : null;

        return $threeDSecure;
    }
}
