<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Refund;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Worldline\Connect\Helper\Data as DataHelper;
use Worldline\Connect\Sdk\V1\Domain\AddressPersonal;
use Worldline\Connect\Sdk\V1\Domain\AmountOfMoney;
use Worldline\Connect\Sdk\V1\Domain\ContactDetailsBase;
use Worldline\Connect\Sdk\V1\Domain\PersonalName;
use Worldline\Connect\Sdk\V1\Domain\RefundCustomer;
use Worldline\Connect\Sdk\V1\Domain\RefundReferences;
use Worldline\Connect\Sdk\V1\Domain\RefundRequest;

/**
 * Class RefundRequestBuilder
 *
 * @package Worldline\Connect\Model\Worldline
 */
class RefundRequestBuilder
{
    public const EMAIL_MESSAGE_TYPE = 'html';

    /**
     * @var DateTime
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $dateTime;

    /**
     * @var AmountOfMoney
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $amountOfMoneyObject;

    /**
     * @var RefundReferences
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $refundReferencesObject;

    /**
     * @var PersonalName
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $personalNameObject;

    /**
     * @var ContactDetailsBase
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $contactDetailsBaseObject;

    /**
     * @var AddressPersonal
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $addressPersonalObject;

    /**
     * @var RefundCustomer
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $refundCustomerObject;

    /**
     * @var RefundRequest
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $request;

    /**
     * @var int
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $amount = null;

    /**
     * @var string
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $countryCode = null;

    /**
     * @var string
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $currencyCode = null;

    /**
     * @var string
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $merchantReference = null;

    /**
     * @var string
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $customerLastname = null;

    /**
     * @var string
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $customerEmail = null;

    /**
     * @var string
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $emailMessageType = null;

    public function __construct(
        DateTime $dateTime,
        AmountOfMoney $amountOfMoney,
        RefundReferences $refundReferences,
        PersonalName $personalName,
        ContactDetailsBase $contactDetailsBase,
        AddressPersonal $addressPersonal,
        RefundCustomer $refundCustomer,
        RefundRequest $refundRequest,
    ) {
        $this->dateTime = $dateTime;
        $this->amountOfMoneyObject = $amountOfMoney;
        $this->refundReferencesObject = $refundReferences;
        $this->personalNameObject = $personalName;
        $this->contactDetailsBaseObject = $contactDetailsBase;
        $this->addressPersonalObject = $addressPersonal;
        $this->refundCustomerObject = $refundCustomer;
        $this->request = $refundRequest;
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
    public function build(OrderInterface $order, float $amount)
    {
        if ($billing = $order->getBillingAddress()) {
            $this->setCountryCode($billing->getCountryId());
        }

        $this->setAmount(DataHelper::formatWorldlineAmount($amount));
        $this->setCurrencyCode($order->getOrderCurrencyCode());
        $this->setCustomerEmail($order->getCustomerEmail() ?: '');
        $this->setCustomerLastname($order->getCustomerLastname() ?: '');
        $this->setEmailMessageType(self::EMAIL_MESSAGE_TYPE);
        $this->setMerchantReference($order->getIncrementId());

        return $this->create();
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
    private function create()
    {
        $this->request->refundDate = $this->dateTime->date('Ymd');
        $this->request->refundReferences = $this->buildRefundReferences();
        $this->request->amountOfMoney = $this->buildAmountOfMoney();
        $this->request->customer = $this->buildRefundCustomer();

        return $this->request;
    }

    /**
     * @param int $amount
     */
    private function setAmount(int $amount)
    {
        $this->amount = $amount;
    }

    /**
     * @param string $countryCode
     */
    private function setCountryCode(string $countryCode)
    {
        $this->countryCode = $countryCode;
    }

    /**
     * @param string $currencyCode
     */
    private function setCurrencyCode(string $currencyCode)
    {
        $this->currencyCode = $currencyCode;
    }

    /**
     * @param string $merchantReference
     */
    private function setMerchantReference(string $merchantReference)
    {
        $this->merchantReference = $merchantReference;
    }

    /**
     * @param string $customerLastname
     */
    private function setCustomerLastname(string $customerLastname)
    {
        $this->customerLastname = $customerLastname;
    }

    /**
     * @param string $customerEmail
     */
    private function setCustomerEmail(string $customerEmail)
    {
        $this->customerEmail = $customerEmail;
    }

    /**
     * @param string $emailMessageType
     */
    private function setEmailMessageType(string $emailMessageType)
    {
        $this->emailMessageType = $emailMessageType;
    }

    /**
     * Get money amount
     *
     * @return AmountOfMoney
     */
    private function buildAmountOfMoney()
    {
        $this->amountOfMoneyObject->amount = $this->amount;
        $this->amountOfMoneyObject->currencyCode = $this->currencyCode;

        return $this->amountOfMoneyObject;
    }

    /**
     * @return RefundReferences
     */
    private function buildRefundReferences()
    {
        $this->refundReferencesObject->merchantReference = $this->merchantReference;

        return $this->refundReferencesObject;
    }

    /**
     * @return PersonalName
     */
    private function buildPersonalName()
    {
        $this->personalNameObject->surname = $this->customerLastname;

        return $this->personalNameObject;
    }

    /**
     * @return ContactDetailsBase
     */
    private function buildContactDetailsBase()
    {
        $this->contactDetailsBaseObject->emailAddress = $this->customerEmail;
        $this->contactDetailsBaseObject->emailMessageType = $this->emailMessageType;

        return $this->contactDetailsBaseObject;
    }

    /**
     * @return AddressPersonal
     */
    private function buildAddressPersonal()
    {
        $this->personalNameObject = $this->buildPersonalName();
        $this->addressPersonalObject->name = $this->personalNameObject;
        $this->addressPersonalObject->countryCode = $this->countryCode;

        return $this->addressPersonalObject;
    }

    /**
     * @return RefundCustomer
     */
    private function buildRefundCustomer()
    {
        $this->refundCustomerObject->address = $this->buildAddressPersonal();
        $this->refundCustomerObject->contactDetails = $this->buildContactDetailsBase();

        return $this->refundCustomerObject;
    }
}
