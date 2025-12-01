<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\Data\OrderAddressInterface as OrderAddress;
use Magento\Sales\Model\ResourceModel\Order\Address\Collection;
use Magento\Sales\Model\ResourceModel\Order\Address\CollectionFactory;
use Worldline\Connect\Helper\Format;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Shipping\AddressBuilder as ShippingAddressBuilder;
use Worldline\Connect\Sdk\V1\Domain\Shipping;
use Worldline\Connect\Sdk\V1\Domain\ShippingFactory;

class ShippingBuilder
{
    public const ANOTHER_VERIFIED_ADDRESS_ON_FILE_WITH_MERCHANT = 'another-verified-address-on-file-with-merchant';
    public const DIFFERENT_THAN_BILLING = 'different-than-billing';
    public const DIGITAL_GOODS = 'digital-goods';
    public const SAME_AS_BILLING = 'same-as-billing';

    /**
     * @var ShippingFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $shippingFactory;

    /**
     * @var CollectionFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $addressCollectionFactory;

    /**
     * @var DateTimeFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $dateTimeFactory;

    /**
     * @var ShippingAddressBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $shippingAddressBuilder;

    /**
     * @var Format
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $format;

    public function __construct(
        ShippingFactory $shippingFactory,
        CollectionFactory $addressCollectionFactory,
        DateTimeFactory $dateTimeFactory,
        ShippingAddressBuilder $shippingAddressBuilder,
        Format $format
    ) {
        $this->shippingFactory = $shippingFactory;
        $this->addressCollectionFactory = $addressCollectionFactory;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->shippingAddressBuilder = $shippingAddressBuilder;
        $this->format = $format;
    }

    /**
     * @throws LocalizedException
     */
    public function create(Order $order): Shipping
    {
        $shipping = $this->shippingFactory->create();
        $shipping->address = $this->shippingAddressBuilder->create($order);

        /** @var OrderAddress $shippingAddress */
        $shippingAddress = $order->getShippingAddress();
        $shipping->addressIndicator = $this->getAddressIndicator($order);

        if ($billingAddress = $order->getBillingAddress()) {
            $shipping->emailAddress = $this->format->limit($this->getEmailAddress($billingAddress), 70);
        }

        if ($shippingAddress !== null && !$order->getCustomerIsGuest()) {
            $shipping->firstUsageDate = $this->getFirstUsageDate($shippingAddress);
            $shipping->isFirstUsage = $this->getIsAddressFirstUsage($shippingAddress);
        }

        return $shipping;
    }

    /**
     * @throws LocalizedException
     */
    public function createNew(Quote $quote): Shipping
    {
        $shipping = $this->shippingFactory->create();
        $shipping->address = $this->shippingAddressBuilder->createNew($quote);

        /** @var QuoteAddress $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();
        $shipping->addressIndicator = $this->getAddressIndicatorNew($quote);

        if ($billingAddress = $quote->getBillingAddress()) {
            $shipping->emailAddress = $this->format->limit($this->getEmailAddressNew($billingAddress), 70);
        }

        if ($shippingAddress !== null && !$quote->getCustomerIsGuest()) {
            $shipping->firstUsageDate = $this->getFirstUsageDateNew($shippingAddress);
            $shipping->isFirstUsage = $this->getIsAddressFirstUsageNew($shippingAddress);
        }

        return $shipping;
    }

    private function getFirstUsageDate(OrderAddress $shippingAddress): string
    {
        $addressCollection = $this->getShippingAddressLastUsagesByOrder($shippingAddress);
        $oldestUsage = $addressCollection->getFirstItem();
        $oldestUsageDate = $oldestUsage['created_at'];
        return $this->dateTimeFactory->create($oldestUsageDate !== null ? $oldestUsageDate : 'now')->format('Ymd');
    }

    private function getFirstUsageDateNew(QuoteAddress $shippingAddress): string
    {
        $addressCollection = $this->getShippingAddressLastUsagesByOrderNew($shippingAddress);
        $oldestUsage = $addressCollection->getFirstItem();
        $oldestUsageDate = $oldestUsage['created_at'];
        return $this->dateTimeFactory->create($oldestUsageDate !== null ? $oldestUsageDate : 'now')->format('Ymd');
    }

    private function getIsAddressFirstUsage(OrderAddress $shippingAddress): bool
    {
        $addressCollection = $this->getShippingAddressLastUsagesByOrder($shippingAddress);
        return !($addressCollection->getSize() > 1);
    }

    private function getIsAddressFirstUsageNew(QuoteAddress $shippingAddress): bool
    {
        $addressCollection = $this->getShippingAddressLastUsagesByOrderNew($shippingAddress);
        return !($addressCollection->getSize() > 1);
    }

    private function getAddressIndicator(Order $order): string
    {
        if ($order->getIsVirtual()) {
            return self::DIGITAL_GOODS;
        }

        if ($this->isShippingAddressEqualToBillingAddress($order->getBillingAddress(), $order->getShippingAddress())) {
            return self::SAME_AS_BILLING;
        }

        // phpcs:ignore PSR12.ControlStructures.ControlStructureSpacing.FirstExpressionLine
        if (!$order->getCustomerIsGuest() &&
            $this->isShippingAddressOnFileWithTheRegisteredCustomer($order->getShippingAddress())
        ) {
            return self::ANOTHER_VERIFIED_ADDRESS_ON_FILE_WITH_MERCHANT;
        }

        return self::DIFFERENT_THAN_BILLING;
    }

    private function getAddressIndicatorNew(Quote $quote): string
    {
        if ($quote->getIsVirtual()) {
            return self::DIGITAL_GOODS;
        }

        if ($this->isShippingAddressEqualToBillingAddressNew($quote->getBillingAddress(), $quote->getShippingAddress())) {
            return self::SAME_AS_BILLING;
        }

        // phpcs:ignore PSR12.ControlStructures.ControlStructureSpacing.FirstExpressionLine
        if (!$quote->getCustomerIsGuest() &&
            $this->isShippingAddressOnFileWithTheRegisteredCustomerNew($quote->getShippingAddress())
        ) {
            return self::ANOTHER_VERIFIED_ADDRESS_ON_FILE_WITH_MERCHANT;
        }

        return self::DIFFERENT_THAN_BILLING;
    }

    private function getEmailAddress(OrderAddress $address): string
    {
        return $address->getEmail();
    }

    private function getEmailAddressNew(QuoteAddress $address): string
    {
        return $address->getEmail();
    }

    /**
     * @param Order $order
     * @return string
     * @throws LocalizedException
     */
    private function getShipmentTrackingNumber(Order $order): string
    {
        $trackingNumbers = $order->getTrackingNumbers();

        if ($trackingNumbers === []) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            throw new LocalizedException(__('No tracking numbers set for this Order'));
        }

        return $trackingNumbers[0];
    }

    /**
     * @param Quote $quote
     * @return string
     * @throws LocalizedException
     */
    private function getShipmentTrackingNumberNew(Quote $quote): string
    {
        $trackingNumbers = $quote->getTrackingNumbers();

        if ($trackingNumbers === []) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            throw new LocalizedException(__('No tracking numbers set for this Order'));
        }

        return $trackingNumbers[0];
    }

    private function isShippingAddressOnFileWithTheRegisteredCustomer(OrderAddress $shippingAddress): bool
    {
        return $shippingAddress->getCustomerAddressId() !== null;
    }

    private function isShippingAddressOnFileWithTheRegisteredCustomerNew(QuoteAddress $shippingAddress): bool
    {
        return $shippingAddress->getCustomerAddressId() !== null;
    }

    private function getShippingAddressLastUsagesByOrder(OrderAddress $shippingAddress): Collection
    {
        $addressCollection = $this->addressCollectionFactory->create();
        $addressCollection
            ->join(
                ['a' => 'customer_address_entity'],
                'main_table.customer_address_id = a.entity_id',
                ['customer_address_entity_id' => 'a.entity_id']
            )
            ->join(
                ['o' => 'sales_order'],
                'main_table.parent_id = o.entity_id',
                ['created_at' => 'o.created_at']
            )
            ->addFieldToFilter('a.entity_id', (string) $shippingAddress->getCustomerAddressId())
            ->addFieldToFilter('main_table.address_type', QuoteAddress::ADDRESS_TYPE_SHIPPING)
            ->addOrder('o.created_at', AbstractDb::SORT_ORDER_ASC);

        return $addressCollection;
    }

    private function getShippingAddressLastUsagesByOrderNew(QuoteAddress $shippingAddress): Collection
    {
        $addressCollection = $this->addressCollectionFactory->create();
        $addressCollection
            ->join(
                ['a' => 'customer_address_entity'],
                'main_table.customer_address_id = a.entity_id',
                ['customer_address_entity_id' => 'a.entity_id']
            )
            ->join(
                ['o' => 'sales_order'],
                'main_table.parent_id = o.entity_id',
                ['created_at' => 'o.created_at']
            )
            ->addFieldToFilter('a.entity_id', (string) $shippingAddress->getCustomerAddressId())
            ->addFieldToFilter('main_table.address_type', QuoteAddress::ADDRESS_TYPE_SHIPPING)
            ->addOrder('o.created_at', AbstractDb::SORT_ORDER_ASC);

        return $addressCollection;
    }

    private function isShippingAddressEqualToBillingAddress(
        OrderAddress $shippingAddress,
        OrderAddress $billingAddress
    ): bool {
        $shippingAddress = [
            'firstName' => $shippingAddress->getFirstname(),
            'lastName' => $shippingAddress->getLastname(),
            'company' => $shippingAddress->getCompany(),
            'streetAddress' => $shippingAddress->getStreet(),
            'city' => $shippingAddress->getCity(),
            'region' => $shippingAddress->getRegion(),
            'postalCode' => $shippingAddress->getPostcode(),
            'country' => $shippingAddress->getCountryId(),
            'phoneNumber' => $shippingAddress->getTelephone(),
        ];

        $billingAddress = [
            'firstName' => $billingAddress->getFirstname(),
            'lastName' => $billingAddress->getLastname(),
            'company' => $billingAddress->getCompany(),
            'streetAddress' => $billingAddress->getStreet(),
            'city' => $billingAddress->getCity(),
            'region' => $billingAddress->getRegion(),
            'postalCode' => $billingAddress->getPostcode(),
            'country' => $billingAddress->getCountryId(),
            'phoneNumber' => $billingAddress->getTelephone(),
        ];

        return $shippingAddress === $billingAddress;
    }

    private function isShippingAddressEqualToBillingAddressNew(
        QuoteAddress $shippingAddress,
        QuoteAddress $billingAddress
    ): bool {
        $shippingAddress = [
            'firstName' => $shippingAddress->getFirstname(),
            'lastName' => $shippingAddress->getLastname(),
            'company' => $shippingAddress->getCompany(),
            'streetAddress' => $shippingAddress->getStreet(),
            'city' => $shippingAddress->getCity(),
            'region' => $shippingAddress->getRegion(),
            'postalCode' => $shippingAddress->getPostcode(),
            'country' => $shippingAddress->getCountryId(),
            'phoneNumber' => $shippingAddress->getTelephone(),
        ];

        $billingAddress = [
            'firstName' => $billingAddress->getFirstname(),
            'lastName' => $billingAddress->getLastname(),
            'company' => $billingAddress->getCompany(),
            'streetAddress' => $billingAddress->getStreet(),
            'city' => $billingAddress->getCity(),
            'region' => $billingAddress->getRegion(),
            'postalCode' => $billingAddress->getPostcode(),
            'country' => $billingAddress->getCountryId(),
            'phoneNumber' => $billingAddress->getTelephone(),
        ];

        return $shippingAddress === $billingAddress;
    }
}
