<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer\Account;

use Magento\Customer\Model\Logger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Worldline\Connect\Sdk\V1\Domain\CustomerAccountAuthentication;
use Worldline\Connect\Sdk\V1\Domain\CustomerAccountAuthenticationFactory;

class AuthenticationBuilder
{
    public const GUEST = 'guest';
    public const MERCHANT_CREDENTIALS = 'merchant-credentials';

    /**
     * @var CustomerAccountAuthenticationFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $authenticationFactory;

    /**
     * @var Logger
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $customerLogger;

    /**
     * @var DateTimeFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $dateTimeFactory;

    public function __construct(
        CustomerAccountAuthenticationFactory $authenticationFactory,
        Logger $customerLogger,
        DateTimeFactory $dateTimeFactory
    ) {
        $this->authenticationFactory = $authenticationFactory;
        $this->customerLogger = $customerLogger;
        $this->dateTimeFactory = $dateTimeFactory;
    }

    public function create(Order $order): CustomerAccountAuthentication
    {
        /** @var CustomerAccountAuthentication $authentication */
        $authentication = $this->authenticationFactory->create();

        $authentication->method = $this->getAuthenticationMethod($order);

        try {
            $authentication->utcTimestamp = $this->getAuthenticationUtcTimestamp($order);
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (LocalizedException $exception) {
            // Do nothing
        }

        return $authentication;
    }

    public function createNew(Quote $quote): CustomerAccountAuthentication
    {
        /** @var CustomerAccountAuthentication $authentication */
        $authentication = $this->authenticationFactory->create();

        $authentication->method = $this->getAuthenticationMethodNew($quote);

        try {
            $authentication->utcTimestamp = $this->getAuthenticationUtcTimestampNew($quote);
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (LocalizedException $exception) {
            // Do nothing
        }

        return $authentication;
    }

    private function getAuthenticationMethod(Order $order): string
    {
        return $order->getCustomerIsGuest() ? self::GUEST : self::MERCHANT_CREDENTIALS;
    }

    private function getAuthenticationMethodNew(Quote $quote): string
    {
        return $quote->getCustomerIsGuest() ? self::GUEST : self::MERCHANT_CREDENTIALS;
    }

    /**
     * @param Order $order
     * @return string
     * @throws LocalizedException
     */
    private function getAuthenticationUtcTimestamp(Order $order): string
    {
        if ($order->getCustomerIsGuest() || !$order->getCustomerId()) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            throw new LocalizedException(__('Cannot get customer last login time'));
        }

        return $this->dateTimeFactory
            ->create($this->customerLogger->get($order->getCustomerId())->getLastLoginAt())
            ->format('YmdHi');
    }

    /**
     * @param Quote $quote
     * @return string
     * @throws LocalizedException
     */
    private function getAuthenticationUtcTimestampNew(Quote $quote): string
    {
        if ($quote->getCustomerIsGuest() || !$quote->getCustomerId()) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            throw new LocalizedException(__('Cannot get customer last login time'));
        }

        return $this->dateTimeFactory
            ->create($this->customerLogger->get($quote->getCustomerId())->getLastLoginAt())
            ->format('YmdHi');
    }
}
