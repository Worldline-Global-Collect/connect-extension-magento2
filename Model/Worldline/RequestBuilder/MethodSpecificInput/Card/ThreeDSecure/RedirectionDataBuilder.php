<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\MethodSpecificInput\Card\ThreeDSecure;

use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Worldline\Connect\Gateway\Command\CreatePaymentRequest\RedirectRequestBuilder;
use Worldline\Connect\Model\Config;
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

    public function create(OrderPayment $payment): RedirectionData
    {
        $redirectionData = $this->redirectionDataFactory->create();
        $redirectionData->variant = $this->getHostedCheckoutVariant($payment);
        $redirectionData->returnUrl = $this->resolveReturnUrl((int) $payment->getOrder()->getStoreId());

        return $redirectionData;
    }

    public function createNew(QuotePayment $payment): RedirectionData
    {
        $redirectionData = $this->redirectionDataFactory->create();
        $redirectionData->variant = $this->getHostedCheckoutVariantNew($payment);
        $redirectionData->returnUrl = $this->resolveReturnUrl((int) $payment->getQuote()->getStoreId());

        return $redirectionData;
    }

    private function resolveReturnUrl(int $storeId): string
    {
        $path = $this->config->getOrderCreationFlow($storeId) === Config::CONFIG_ORDER_CREATION_FLOW_AFTER
            ? RedirectRequestBuilder::INLINE_PAYMENT_RETURN_URL_NEW
            : RedirectRequestBuilder::REDIRECT_PAYMENT_RETURN_URL;

        return $this->urlBuilder->getUrl($path);
    }

    private function getHostedCheckoutVariant(OrderPayment $payment): ?string
    {
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        return $order->getCustomerIsGuest() ?
            $this->config->getHostedCheckoutGuestVariant($storeId) :
            $this->config->getHostedCheckoutVariant($storeId);
    }

    private function getHostedCheckoutVariantNew(QuotePayment $payment): ?string
    {
        $quote = $payment->getQuote();
        $storeId = $quote->getStoreId();

        return $quote->getCustomerIsGuest() ?
            $this->config->getHostedCheckoutGuestVariant($storeId) :
            $this->config->getHostedCheckoutVariant($storeId);
    }
}
