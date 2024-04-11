<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\MethodSpecificInput\Card;

use Magento\Sales\Api\Data\OrderInterface;
use Worldline\Connect\Model\Worldline\RequestBuilder\MethodSpecificInput\Card\ThreeDSecure\RedirectionDataBuilder;
use Worldline\Connect\Sdk\V1\Domain\ThreeDSecure;
use Worldline\Connect\Sdk\V1\Domain\ThreeDSecureFactory;

class ThreeDSecureBuilder
{
    public const AUTHENTICATION_FLOW_BROWSER = 'browser';

    /**
     * @var ThreeDSecureFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $threeDSecureFactory;

    /**
     * @var RedirectionDataBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $redirectionDataBuilder;

    public function __construct(
        ThreeDSecureFactory $threeDSecureFactory,
        RedirectionDataBuilder $redirectionDataBuilder
    ) {
        $this->threeDSecureFactory = $threeDSecureFactory;
        $this->redirectionDataBuilder = $redirectionDataBuilder;
    }

    public function create(
        OrderInterface $order
    ): ThreeDSecure {
        $threeDSecure = $this->threeDSecureFactory->create();
        $threeDSecure->redirectionData = $this->redirectionDataBuilder->create($order);

        $threeDSecure->authenticationFlow = self::AUTHENTICATION_FLOW_BROWSER;
        return $threeDSecure;
    }
}
