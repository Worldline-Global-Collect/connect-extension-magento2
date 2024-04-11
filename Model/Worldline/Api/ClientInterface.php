<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\Api;

use Magento\Framework\Exception\LocalizedException;
use Worldline\Connect\Sdk\CallContext;
use Worldline\Connect\Sdk\Client;
use Worldline\Connect\Sdk\V1\Domain\ApprovePaymentRequest;
use Worldline\Connect\Sdk\V1\Domain\ApproveRefundRequest;
use Worldline\Connect\Sdk\V1\Domain\CancelApprovalPaymentResponse;
use Worldline\Connect\Sdk\V1\Domain\CancelPaymentResponse;
use Worldline\Connect\Sdk\V1\Domain\CapturePaymentRequest;
use Worldline\Connect\Sdk\V1\Domain\CreateHostedCheckoutRequest;
use Worldline\Connect\Sdk\V1\Domain\CreateHostedCheckoutResponse;
use Worldline\Connect\Sdk\V1\Domain\CreatePaymentRequest;
use Worldline\Connect\Sdk\V1\Domain\CreatePaymentResponse;
use Worldline\Connect\Sdk\V1\Domain\CreateTokenResponse;
use Worldline\Connect\Sdk\V1\Domain\GetHostedCheckoutResponse;
use Worldline\Connect\Sdk\V1\Domain\PaymentProductResponse;
use Worldline\Connect\Sdk\V1\Domain\PaymentProducts;
use Worldline\Connect\Sdk\V1\Domain\PaymentResponse;
use Worldline\Connect\Sdk\V1\Domain\RefundRequest;
use Worldline\Connect\Sdk\V1\Domain\RefundResponse;
use Worldline\Connect\Sdk\V1\Domain\SessionRequest;
use Worldline\Connect\Sdk\V1\Domain\SessionResponse;
use Worldline\Connect\Sdk\V1\Domain\TestConnection;
use Worldline\Connect\Sdk\V1\ResponseException;

// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface ClientInterface
{
    // phpcs:disable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    /**
     * Returns initialized Worldline API client
     *
     * @param null|int $scopeId
     * @return Client
     */
    // phpcs:enable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    public function getWorldlineClient($scopeId = null);

    /**
     * Returns available (configured on Worldline side) payment products for configured merchant.
     * The result set of products depends on the criteria parameters: currency code, country code and locale
     *
     * @link https://developer.globalcollect.com/documentation/api/server/#__merchantId__products_get
     *
     * @param int $amount
     * @param string $currencyCode
     * @param string $countryCode
     * @param string $locale
     * @param int $scopeId
     * @return PaymentProducts
     * @throws ResponseException
     */
    public function getAvailablePaymentProducts($amount, $currencyCode, $countryCode, $locale, $scopeId);

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    public function getPaymentProductGroups($amount, $currencyCode, $countryCode, $locale, $scopeId);

    /**
     * Return selected payment product
     *
     * @param $paymentProductId
     * @param int $amount
     * @param $currencyCode
     * @param $countryCode
     * @param $locale
     * @param $scopeId
     * @return PaymentProductResponse
     * @throws LocalizedException
     * @throws ResponseException
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    public function getWorldlinePaymentProduct(
        $paymentProductId,
        $amount,
        $currencyCode,
        $countryCode,
        $locale,
        $scopeId
    );

    /**
     * Return response of created hosted checkout
     *
     * @param CreateHostedCheckoutRequest $paymentProductRequest
     * @param int|null $scopeId
     * @return CreateHostedCheckoutResponse
     * @throws ResponseException
     */
    public function createHostedCheckout(CreateHostedCheckoutRequest $paymentProductRequest, $scopeId = null);

    /**
     * @param CreatePaymentRequest $request
     * @param int|null $scopeId
     * @return CreatePaymentResponse
     * @throws ResponseException
     */
    public function createPayment(CreatePaymentRequest $request, $scopeId = null);

    /**
     * Return worldline payment refund
     *
     * @param string $refundId
     * @param int $scopeId
     * @return RefundResponse
     * @throws ResponseException
     */
    public function worldlinePaymentRefund($refundId, $scopeId = null);

    /**
     * Return worldline payment cancel
     *
     * @param string $worldlinePaymentId
     * @param int $scopeId
     * @return CancelPaymentResponse
     * @throws ResponseException
     */
    public function worldlinePaymentCancel($worldlinePaymentId, $scopeId = null);

    /**
     * Accept worldline payment
     *
     * @param string $worldlinePaymentId
     * @param int $scopeId
     * @return PaymentResponse
     * @throws ResponseException
     */
    public function worldlinePaymentAccept($worldlinePaymentId, $scopeId = null);

    /**
     * Approve refund
     *
     * @param string $refundId
     * @param ApproveRefundRequest $request
     * @param int $scopeId
     * @return null
     * @throws ResponseException
     */
    public function worldlineRefundAccept($refundId, ApproveRefundRequest $request, $scopeId = null);

    /**
     * Cancel refund
     *
     * @param string $refundId
     * @param int $scopeId
     * @throws ResponseException
     */
    public function worldlineRefundCancel($refundId, $scopeId = null);

    /**
     * Return worldline cancel approval response
     *
     * @param string $worldlinePaymentId
     * @param int $scopeId
     * @return CancelApprovalPaymentResponse
     * @throws ResponseException
     */
    public function worldlineCancelApproval($worldlinePaymentId, $scopeId = null);

    /**
     * Return worldline payment
     *
     * @param string $worldlinePaymentId
     * @param int $scopeId
     * @return PaymentResponse
     * @throws ResponseException
     */
    public function worldlinePayment($worldlinePaymentId, $scopeId = null);

    /**
     * Return worldline payment
     *
     * @param string $worldlinePaymentId
     * @param RefundRequest $request
     * @param CallContext|null $callContext
     * @param int $scopeId
     * @return RefundResponse
     * @throws ResponseException
     */
    public function worldlineRefund($worldlinePaymentId, RefundRequest $request, $callContext = null, $scopeId = null);

    // phpcs:disable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    /**
     * @param string $worldlinePaymentId
     * @param CapturePaymentRequest $request
     * @param int|null $scopeId
     * @return \Worldline\Connect\Sdk\V1\Domain\CaptureResponse
     * @throws ResponseException
     */
    // phpcs:enable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    public function worldlinePaymentCapture($worldlinePaymentId, CapturePaymentRequest $request, $scopeId = null);

    // phpcs:disable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    /**
     * @param string $worldlinePaymentId
     * @param ApprovePaymentRequest $request
     * @param int|null $scopeId
     * @return \Worldline\Connect\Sdk\V1\Domain\PaymentApprovalResponse
     * @throws ResponseException
     */
    // phpcs:enable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    public function worldlinePaymentApprove($worldlinePaymentId, ApprovePaymentRequest $request, $scopeId = null);

    /**
     * @param SessionRequest $request
     * @param int|null $scopeId
     * @return SessionResponse
     * @throws ResponseException
     */
    public function worldlineCreateSession(SessionRequest $request, $scopeId = null);

    // phpcs:disable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName, SlevomatCodingStandard.TypeHints.DisallowArrayTypeHintSyntax.DisallowedArrayTypeHintSyntax
    /**
     * @param int|null $scopeId
     * @param Client|null $client
     * @return TestConnection
     * @throws ResponseException
     */
    // phpcs:enable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName, SlevomatCodingStandard.TypeHints.DisallowArrayTypeHintSyntax.DisallowedArrayTypeHintSyntax
    public function worldlineTestAccount($scopeId, ?Client $client = null);

    /**
     * @param string $worldlinePaymentId
     * @param null $scopeId
     * @param string|null $alias
     * @return CreateTokenResponse
     */
    public function worldlinePaymentTokenize($worldlinePaymentId, $scopeId = null, $alias = null);

    /**
     * @param string $hostedCheckoutId
     * @param $scopeId
     * @return GetHostedCheckoutResponse
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    public function getHostedCheckout(string $hostedCheckoutId, $scopeId = null);

    /**
     * @param mixed $scopeId
     * @param array $data
     * @return mixed
     */
    public function buildFromConfiguration($scopeId, array $data = []);
}
