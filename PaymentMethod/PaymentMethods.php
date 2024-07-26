<?php

declare(strict_types=1);

namespace Worldline\Connect\PaymentMethod;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\Resolver;
use Magento\Payment\Api\PaymentMethodListInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Sdk\V1\Domain\PaymentProduct;

use function array_map;
use function in_array;
use function round;
use function str_starts_with;

class PaymentMethods
{
    public const PREFIX = 'worldline_';

    public const CARDS_VAULT = self::PREFIX . 'vault';
    public const AMERICAN_EXPRESS_VAULT = self::PREFIX . 'americanexpress_vault';
    public const DISCOVER_VAULT = self::DISCOVER . '_vault';
    public const CARTE_BANCAIRE_VAULT = self::PREFIX . 'cartebancaire_vault';
    public const MAESTRO_VAULT = self::PREFIX . 'maestro_vault';
    public const MASTERCARD_VAULT = self::PREFIX . 'mastercard_vault';
    public const MASTERCARD_DEBIT_VAULT = self::PREFIX . 'mastercard_debit_vault';
    public const TROY_VAULT = self::PREFIX . 'troy_vault';
    public const TROY_DEBIT_VAULT = self::PREFIX . 'troy_debit_vault';
    public const VISA_VAULT = self::PREFIX . 'visa_vault';
    public const VISA_DEBIT_VAULT = self::PREFIX . 'visa_debit_vault';
    public const VISA_ELECTRON_VAULT = self::PREFIX . 'visa_electron_vault';
    public const CARDS = self::PREFIX . 'cards';
    public const AMERICAN_EXPRESS = self::PREFIX . 'americanexpress';
    public const BC_CARD = self::PREFIX . 'bc_card';
    public const BC_CARD_AUTHENTICATED = self::PREFIX . 'bc_card_authenticated';
    public const CARTE_BANCAIRE = self::PREFIX . 'cartebancaire';
    public const DINERS_CLUB = self::PREFIX . 'dinersclub';
    public const DISCOVER = self::PREFIX . 'discover';
    public const HYUNDAI_CARD = self::PREFIX . 'hyundai_card';
    public const HYUNDAI_CARD_AUTHENTICATED = self::PREFIX . 'hyundai_card_authenticated';
    public const JCB = self::PREFIX . 'jcb';
    public const KB_KOOKMIN_CARD = self::PREFIX . 'kb_kookmin_card';
    public const KB_KOOKMIN_CARD_AUTHENTICATED = self::PREFIX . 'kb_kookmin_card_authenticated';
    public const KEB_HANA_CARD = self::PREFIX . 'keb_hana_card';
    public const KEB_HANA_CARD_AUTHENTICATED = self::PREFIX . 'keb_hana_card_authenticated';
    public const LOTTE_CARD = self::PREFIX . 'lotte_card';
    public const LOTTE_CARD_AUTHENTICATED = self::PREFIX . 'lotte_card_authenticated';
    public const UNIONPAY_INTERNATIONAL_SECUREPAY = self::PREFIX . 'unionpay_international_securepay';
    public const MASTERCARD = self::PREFIX . 'mastercard';
    public const MASTERCARD_DEBIT = self::PREFIX . 'mastercard_debit';
    public const NH_CARD = self::PREFIX . 'nh_card';
    public const NH_CARD_AUTHENTICATED = self::PREFIX . 'nh_card_authenticated';
    public const SAMSUNG_CARD = self::PREFIX . 'samsung_card';
    public const SAMSUNG_CARD_AUTHENTICATED = self::PREFIX . 'samsung_card_authenticated';
    public const SHINHAN_CARD = self::PREFIX . 'shinhan_card';
    public const TROY = self::PREFIX . 'troy';
    public const TROY_DEBIT = self::PREFIX . 'troy_debit';
    public const SHINHAN_CARD_AUTHENTICATED = self::PREFIX . 'shinhan_card_authenticated';
    public const UNIONPAY_EXPRESSPAY = self::PREFIX . 'unionpay_expresspay';
    public const VISA = self::PREFIX . 'visa';
    public const VISA_DEBIT = self::PREFIX . 'visa_debit';
    public const VISA_ELECTRON = self::PREFIX . 'visa_electron';
    public const MAESTRO = self::PREFIX . 'maestro';
    public const LINK_PLUS_PAYMENT_LINK = self::PREFIX . 'link_plus_payment_link';
    public const IDEAL = self::PREFIX . 'ideal';
    public const ACCOUNT_TO_ACCOUNT = self::PREFIX . 'account_to_account';
    public const PAYPAL = self::PREFIX . 'paypal';
    public const PAYSAFECARD = self::PREFIX . 'paysafecard';
    public const SOFORT = self::PREFIX . 'sofort';
    public const TRUSTLY = self::PREFIX . 'trustly';
    public const HOSTED = self::PREFIX . 'hpp';
    public const APPLE_PAY = self::PREFIX . 'apple_pay';
    public const GOOGLE_PAY = self::PREFIX . 'google_pay';

    public function __construct(
        private readonly ClientInterface $client,
        private readonly Resolver $resolver,
        private readonly PaymentMethodListInterface $paymentMethodList,
        private readonly Data $paymentHelper
    ) {
    }

    public function isWorldlinePaymentMethod(string $paymentMethodCode): bool
    {
        return str_starts_with($paymentMethodCode, self::PREFIX);
    }

    /**
     * @return array<MethodInterface>
     * @throws LocalizedException
     */
    public function getPaymentMethods(int $storeId): array
    {
        $allPaymentMethods = $this->paymentMethodList->getList($storeId);

        $methodInstances = [];
        foreach ($allPaymentMethods as $paymentMethod) {
            if (!$this->isWorldlinePaymentMethod($paymentMethod->getCode())) {
                continue;
            }

            $methodInstances[] = $this->paymentHelper->getMethodInstance($paymentMethod->getCode());
        }

        return $methodInstances;
    }

    /**
     * @param array<MethodInterface> $paymentMethods
     */
    public function getPaymentMethodConfigData(array $paymentMethods, string $key): array
    {
        return array_map(
            static fn (MethodInterface $methodInstance) => $methodInstance->getConfigData($key),
            $paymentMethods
        );
    }

    /**
     * @throws LocalizedException
     */
    public function getAvailablePaymentProductIds(Quote $quote): array
    {
        /**
         * Magento overwrites the country that was set on the quote billing address.
         * @see \Magento\Quote\Model\Quote::assignCustomerWithAddressChange
         */
        $countryId = $quote->getBillingAddress()->getOrigData('country_id');
        if ($countryId === null) {
            return [];
        }

        $productIds = $this->getPaymentMethodConfigData(
            $this->getPaymentMethods((int) $quote->getStoreId()),
            'product_id'
        );

        $availableProductIds = [];
        foreach ($this->getAvailablePaymentProducts($quote, $countryId) as $product) {
            if (in_array($product->id, $productIds)) {
                $availableProductIds[] = $product->id;
            }
        }

        return $availableProductIds;
    }

    /**
     * @return array<PaymentProduct>
     */
    private function getAvailablePaymentProducts(Quote $quote, string $countryId): array
    {
        try {
            return $this->client->getAvailablePaymentProducts(
                (int) round($quote->getGrandTotal() * 100),
                $quote->getQuoteCurrencyCode(),
                $countryId,
                $this->resolver->getLocale(),
                $quote->getStoreId()
            )->paymentProducts;
        } catch (Exception) {
            return [];
        }
    }
}
