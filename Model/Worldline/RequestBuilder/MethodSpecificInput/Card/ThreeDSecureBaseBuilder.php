<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\MethodSpecificInput\Card;

use Worldline\Connect\Sdk\V1\Domain\ThreeDSecureBase;
use Worldline\Connect\Sdk\V1\Domain\ThreeDSecureBaseFactory;

class ThreeDSecureBaseBuilder
{
    public const AUTHENTICATION_FLOW_BROWSER = 'browser';

    public function __construct(
        private readonly ThreeDSecureBaseFactory $threeDSecureBaseFactory,
    ) {
    }

    public function create(): ThreeDSecureBase
    {
        $threeDSecure = $this->threeDSecureBaseFactory->create();
        $threeDSecure->authenticationFlow = self::AUTHENTICATION_FLOW_BROWSER;
        return $threeDSecure;
    }
}
