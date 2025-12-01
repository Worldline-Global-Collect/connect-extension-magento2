<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Gateway\Command\CreatePaymentRequest;

use Exception;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Worldline\Connect\Gateway\Command\CreatePaymentRequestBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\FraudFieldsBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\MerchantBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\OrderBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\MethodSpecificInput\Card\ThreeDSecureBaseBuilder;
use Worldline\Connect\Model\Worldline\Token\TokenServiceInterface;
use Worldline\Connect\Sdk\V1\Domain\CardPaymentMethodSpecificInputBaseFactory;
use Worldline\Connect\Sdk\V1\Domain\CreateHostedCheckoutRequest;
use Worldline\Connect\Sdk\V1\Domain\CreateHostedCheckoutRequestFactory;
use Worldline\Connect\Sdk\V1\Domain\HostedCheckoutSpecificInput;
use Worldline\Connect\Sdk\V1\Domain\HostedCheckoutSpecificInputFactory;
use Worldline\Connect\Sdk\V1\Domain\PaymentProductFilter;
use Worldline\Connect\Sdk\V1\Domain\PaymentProductFiltersHostedCheckout;

use function array_key_exists;
use function count;
use function is_array;

// phpcs:ignore PSR12.Files.FileHeader.SpacingAfterBlock
// phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse

class CreateHostedCheckoutRequestBuilder implements CreatePaymentRequestBuilder
{
    /**
     * @var CreateHostedCheckoutRequestFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $createHostedCheckoutRequestFactory;

    /**
     * @var OrderBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $orderBuilder;

    /**
     * @var MerchantBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $merchantBuilder;

    /**
     * @var FraudFieldsBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $fraudFieldsBuilder;

    /**
     * @var CardPaymentMethodSpecificInputBaseFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $cardPaymentMethodSpecificInputFactory;

    /**
     * @var ThreeDSecureBaseBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $threeDSecureBuilder;

    /**
     * @var HostedCheckoutSpecificInputFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $hostedCheckoutSpecificInputFactory;

    /**
     * @var ResolverInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $resolver;

    /**
     * @var UrlInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $urlBuilder;

    /**
     * @var TokenServiceInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $tokenService;

    /**
     * @var Json
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $json;

    /**
     * @param CreateHostedCheckoutRequestFactory $createHostedCheckoutRequestFactory
     * @param OrderBuilder $orderBuilder
     * @param MerchantBuilder $merchantBuilder
     * @param FraudFieldsBuilder $fraudFieldsBuilder
     * @param CardPaymentMethodSpecificInputBaseFactory $cardPaymentMethodSpecificInputFactory
     * @param ThreeDSecureBaseBuilder $threeDSecureBuilder
     * @param HostedCheckoutSpecificInputFactory $hostedCheckoutSpecificInputFactory
     * @param ResolverInterface $resolver
     * @param UrlInterface $urlBuilder
     * @param TokenServiceInterface $tokenService
     */
    public function __construct(
        CreateHostedCheckoutRequestFactory $createHostedCheckoutRequestFactory,
        OrderBuilder $orderBuilder,
        MerchantBuilder $merchantBuilder,
        FraudFieldsBuilder $fraudFieldsBuilder,
        CardPaymentMethodSpecificInputBaseFactory $cardPaymentMethodSpecificInputFactory,
        ThreeDSecureBaseBuilder $threeDSecureBuilder,
        HostedCheckoutSpecificInputFactory $hostedCheckoutSpecificInputFactory,
        ResolverInterface $resolver,
        UrlInterface $urlBuilder,
        TokenServiceInterface $tokenService,
        Json $json
    ) {
        $this->createHostedCheckoutRequestFactory = $createHostedCheckoutRequestFactory;
        $this->orderBuilder = $orderBuilder;
        $this->merchantBuilder = $merchantBuilder;
        $this->fraudFieldsBuilder = $fraudFieldsBuilder;
        $this->cardPaymentMethodSpecificInputFactory = $cardPaymentMethodSpecificInputFactory;
        $this->threeDSecureBuilder = $threeDSecureBuilder;
        $this->hostedCheckoutSpecificInputFactory = $hostedCheckoutSpecificInputFactory;
        $this->resolver = $resolver;
        $this->urlBuilder = $urlBuilder;
        $this->tokenService = $tokenService;
        $this->json = $json;
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
    public function build(OrderPayment $payment, bool $requiresApproval): CreateHostedCheckoutRequest
    {
        $order = $payment->getOrder();

        $input = $this->cardPaymentMethodSpecificInputFactory->create();
        $input->threeDSecure = $this->threeDSecureBuilder->create($payment);
        $input->transactionChannel = 'ECOMMERCE';
        $input->requiresApproval = $requiresApproval;
        $input->tokenize = $payment->getAdditionalInformation('tokenize');

        $orderPaymentExtension = $payment->getExtensionAttributes();
        if ($orderPaymentExtension !== null) {
            $paymentToken = $orderPaymentExtension->getVaultPaymentToken();
            if ($paymentToken !== null) {
                $input->token = $paymentToken->getGatewayToken();
            }
        }

        $request = $this->createHostedCheckoutRequestFactory->create();
        $request->order = $this->orderBuilder->create($order);
        $request->merchant = $this->merchantBuilder->create($order);
        $request->fraudFields = $this->fraudFieldsBuilder->create($order);
        $request->hostedCheckoutSpecificInput = $this->buildHostedCheckoutSpecificInput($payment);
        $request->cardPaymentMethodSpecificInput = $input;

        return $request;
    }

    public function buildNew(QuotePayment $payment, bool $requiresApproval): CreateHostedCheckoutRequest
    {
        $quote = $payment->getQuote();

        $input = $this->cardPaymentMethodSpecificInputFactory->create();
        $input->threeDSecure = $this->threeDSecureBuilder->create();
        $input->transactionChannel = 'ECOMMERCE';
        $input->requiresApproval = $requiresApproval;
        $input->tokenize = $payment->getAdditionalInformation('tokenize');

        $orderPaymentExtension = $payment->getExtensionAttributes();
        if ($orderPaymentExtension !== null &&
            method_exists($orderPaymentExtension, 'getVaultPaymentToken') &&
            method_exists($orderPaymentExtension, 'getGatewayToken')) {
            $paymentToken = $orderPaymentExtension->getVaultPaymentToken();
            if ($paymentToken !== null) {
                $input->token = $paymentToken->getGatewayToken();
            }
        }

        $request = $this->createHostedCheckoutRequestFactory->create();
        $request->order = $this->orderBuilder->createNew($quote);
        $request->merchant = $this->merchantBuilder->createNew($quote);
        $request->fraudFields = $this->fraudFieldsBuilder->createNew($quote);
        $request->hostedCheckoutSpecificInput = $this->buildHostedCheckoutSpecificInputNew($payment);
        $request->cardPaymentMethodSpecificInput = $input;

        return $request;
    }

    private function buildHostedCheckoutSpecificInput(OrderPayment $payment): HostedCheckoutSpecificInput
    {
        $specificInput = $this->hostedCheckoutSpecificInputFactory->create();
        $specificInput->locale = $this->resolver->getLocale();
        $specificInput->returnUrl = $this->urlBuilder->getUrl(RedirectRequestBuilder::HOSTED_CHECKOUT_RETURN_URL);
        $specificInput->showResultPage = false;
        $specificInput->tokens = $this->getTokens($payment->getOrder());
        $specificInput->validateShoppingCart = true;
        $specificInput->returnCancelState = true;
        $specificInput->paymentProductFilters = $this->getPaymentProductFilters($payment);

        return $specificInput;
    }

    private function buildHostedCheckoutSpecificInputNew(QuotePayment $payment): HostedCheckoutSpecificInput
    {
        $specificInput = $this->hostedCheckoutSpecificInputFactory->create();
        $specificInput->locale = $this->resolver->getLocale();
        $specificInput->returnUrl = $this->urlBuilder->getUrl(RedirectRequestBuilder::HOSTED_CHECKOUT_RETURN_URL_NEW);
        $specificInput->showResultPage = false;
        $specificInput->tokens = $this->getTokensNew($payment->getQuote());
        $specificInput->validateShoppingCart = true;
        $specificInput->returnCancelState = true;
        $specificInput->paymentProductFilters = $this->getPaymentProductFiltersNew($payment);

        return $specificInput;
    }

    /**
     * @param Order $order
     * @return null|string  String of comma separated token values
     */
    private function getTokens(Order $order)
    {
        if ($order->getCustomerIsGuest() || !$order->getCustomerId()) {
            return null;
        }

        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        $tokens = implode(',', $this->tokenService->find($order->getCustomerId()));

        return $tokens === '' ? null : $tokens;
    }

    /**
     * @param Quote $quote
     * @return null|string  String of comma separated token values
     */
    private function getTokensNew(Quote $quote)
    {
        if ($quote->getCustomerIsGuest() || !$quote->getCustomerId()) {
            return null;
        }

        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        $tokens = implode(',', $this->tokenService->find($quote->getCustomerId()));

        return $tokens === '' ? null : $tokens;
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
    private function getPaymentProductFilters(OrderPayment $payment)
    {
        $paymentProductFilters = new PaymentProductFiltersHostedCheckout();
        $paymentProductFilters->restrictTo = $this->filter(
            $this->getIdentifiers($payment->getMethodInstance()->getConfigData('include_payment_product_groups')),
            $this->getIdentifiers($payment->getMethodInstance()->getConfigData('include_payment_products')),
        );

        $paymentProductFilters->exclude = $this->filter(
            $this->getIdentifiers($payment->getMethodInstance()->getConfigData('exclude_payment_product_groups')),
            $this->getIdentifiers($payment->getMethodInstance()->getConfigData('exclude_payment_products')),
        );

        $productId = $payment->getMethodInstance()->getConfigData('product_id');
        if ($productId) {
            $filter = new PaymentProductFilter();
            $filter->products = [$productId];
            $paymentProductFilters->restrictTo = $filter;
        }

        return $paymentProductFilters;
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
    private function getPaymentProductFiltersNew(QuotePayment $payment)
    {
        $paymentProductFilters = new PaymentProductFiltersHostedCheckout();
        $paymentProductFilters->restrictTo = $this->filter(
            $this->getIdentifiers($payment->getMethodInstance()->getConfigData('include_payment_product_groups')),
            $this->getIdentifiers($payment->getMethodInstance()->getConfigData('include_payment_products')),
        );

        $paymentProductFilters->exclude = $this->filter(
            $this->getIdentifiers($payment->getMethodInstance()->getConfigData('exclude_payment_product_groups')),
            $this->getIdentifiers($payment->getMethodInstance()->getConfigData('exclude_payment_products')),
        );

        $productId = $payment->getMethodInstance()->getConfigData('product_id');
        if ($productId) {
            $filter = new PaymentProductFilter();
            $filter->products = [$productId];
            $paymentProductFilters->restrictTo = $filter;
        }

        return $paymentProductFilters;
    }

    private function filter(array $groups, array $products): ?PaymentProductFilter
    {
        if (count($groups) === 0 && count($products) === 0) {
            return null;
        }

        $filter = new PaymentProductFilter();
        $filter->groups = $groups;
        $filter->products = $products;

        return $filter;
    }

    private function getIdentifiers(?string $config): array
    {
        if ($config === null) {
            return [];
        }

        try {
            $array = $this->json->unserialize($config);
        } catch (Exception) {
            return [];
        }

        if (!is_array($array)) {
            return [];
        }

        $identifiers = [];
        foreach ($array as $identifier) {
            if (!is_array($identifier) || !array_key_exists('id', $identifier)) {
                continue;
            }

            $identifiers[] = $identifier['id'];
        }

        return $identifiers;
    }
}
