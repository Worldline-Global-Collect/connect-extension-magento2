<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\MethodSpecificInput\Card\ThreeDSecure;

use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Gateway\Command\CreatePaymentRequest\RedirectRequestBuilder;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Sdk\V1\Domain\RedirectionData;
use Worldline\Connect\Sdk\V1\Domain\RedirectionDataFactory;

class RedirectionDataBuilder
{
    /**
     * @var RedirectionDataFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $redirectionDataFactory;

    /**
     * @var ConfigInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $config;

    /**
     * @var UrlInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $urlBuilder;

    public function __construct(
        RedirectionDataFactory $redirectionDataFactory,
        ConfigInterface $config,
        UrlInterface $urlBuilder
    ) {
        $this->redirectionDataFactory = $redirectionDataFactory;
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
    }

    public function create(Payment $payment): RedirectionData
    {
        $redirectionData = $this->redirectionDataFactory->create();
        $redirectionData->variant = $this->getHostedCheckoutVariant($payment);
        $redirectionData->returnUrl = $this->urlBuilder->getUrl(RedirectRequestBuilder::REDIRECT_PAYMENT_RETURN_URL);

        return $redirectionData;
    }

    private function getHostedCheckoutVariant(Payment $payment): ?string
    {
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        return $order->getCustomerIsGuest() ?
            $this->config->getHostedCheckoutGuestVariant($storeId) :
            $this->config->getHostedCheckoutVariant($storeId);
    }
}
