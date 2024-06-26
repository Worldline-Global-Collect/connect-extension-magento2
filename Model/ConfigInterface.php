<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model;

// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface ConfigInterface
{
    public function getValue(string $field, mixed $storeId = null): mixed;

    public function get3DSRequestExemptions(mixed $storeId = null): string;

    /**
     * Returns Api Key
     *
     * @param int|null $storeId
     * @param string|null $environment
     * @return string
     */
    public function getApiKey($storeId = null, $environment = null);

    /**
     * Returns Api Secret
     *
     * @param int|null $storeId
     * @param string|null $environment
     * @return string
     */
    public function getApiSecret($storeId = null, $environment = null);

    /**
     * Returns Merchant Id
     *
     * @param int|null $storeId
     * @param string|null $environment
     * @return string
     */
    public function getMerchantId($storeId = null, $environment = null);

    /**
     * Returns Api Endpoint
     *
     * @param int|null $storeId
     * @param string|null $environment
     * @return string
     */
    public function getApiEndpoint($storeId = null, $environment = null);

    /**
     * Returns Api Environment
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiEnvironment($storeId = null);

    /**
     * Returns WebHooks Key Id
     *
     * @param int|null $storeId
     * @return string
     */
    public function getWebHooksKeyId($storeId = null);

    /**
     * Returns WebHooks Secret Key
     *
     * @param int|null $storeId
     * @return string
     */
    public function getWebHooksSecretKey($storeId = null);

    /**
     * Returns Fraud Manager Email
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getFraudManagerEmail($storeId = null);

    /**
     * Returns Fraud Email Sender
     *
     * @param int|null $storeId
     * @return string
     */
    public function getFraudEmailSender($storeId = null);

    /**
     * Returns Fraud Email Template
     *
     * @param int|null $storeId
     * @return string
     */
    public function getFraudEmailTemplate($storeId = null);

    /**
     * Returns Soft Descriptor
     *
     * @param int|null $storeId
     * @return string
     */
    public function getDescriptor($storeId = null);

    /**
     * Returns Hosted Checkout SubDomain
     *
     * @param int|null $storeId
     * @return string
     */
    public function getHostedCheckoutSubDomain($storeId = null);

    /**
     * Return Hosted Checkout Variant
     *
     * @param null|int $storeId
     * @return string|null
     */
    public function getHostedCheckoutVariant($storeId = null);

    /**
     * Return Hosted Checkout Guest Variant
     *
     * @param null|int $storeId
     * @return string|null
     */
    public function getHostedCheckoutGuestVariant($storeId = null);

    /**
     * @param null|int $storeId
     * @return string
     */
    public function getHostedCheckoutTitle($storeId = null);

    /**
     * Returns flag (enable/disable) to log all requests
     *
     * @param int|null $storeId
     * @return bool
     */
    public function getLogAllRequests($storeId = null);

    /**
     * Returns flag (enable/disable) to log all frontend requests
     *
     * @param int|null $storeId
     * @return bool
     */
    public function getLogFrontendRequests($storeId = null);

    /**
     * Returns file name of log file
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLogAllRequestsFile($storeId = null);

    /**
     * Returns payment status info
     *
     * @param int|null $storeId
     * @param $status
     * @return string
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    public function getPaymentStatusInfo($status, $storeId = null);

    /**
     * Returns refund status info
     *
     * @param $status
     * @param null $storeId
     * @return mixed
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    public function getRefundStatusInfo($status, $storeId = null);

    public function getLimitAPIFieldLength(): bool;

    public function getSaveForLaterVisible(int $storeId): bool;

    public function getRedirectText(int $storeId): string;
}
