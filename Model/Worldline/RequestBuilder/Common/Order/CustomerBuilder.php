<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Worldline\Connect\Helper\Format;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer\AccountBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer\AddressBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer\CompanyInformationBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer\DeviceBuilder;
use Worldline\Connect\Sdk\V1\Domain\CompanyInformationFactory;
use Worldline\Connect\Sdk\V1\Domain\ContactDetails;
use Worldline\Connect\Sdk\V1\Domain\ContactDetailsFactory;
use Worldline\Connect\Sdk\V1\Domain\Customer;
use Worldline\Connect\Sdk\V1\Domain\CustomerFactory;
use Worldline\Connect\Sdk\V1\Domain\PersonalInformation;
use Worldline\Connect\Sdk\V1\Domain\PersonalInformationFactory;
use Worldline\Connect\Sdk\V1\Domain\PersonalNameFactory;

use function rand;

/**
 * Class CustomerBuilder
 */
class CustomerBuilder
{
    public const EMAIL_MESSAGE_TYPE = 'html';
    public const GENDER_MALE = 0;
    public const GENDER_FEMALE = 1;
    public const ACCOUNT_TYPE_NONE = 'none';
    public const ACCOUNT_TYPE_EXISTING = 'existing';

    /**
     * @var CustomerFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $customerFactory;

    /**
     * @var PersonalInformationFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $personalInformationFactory;

    /**
     * @var CompanyInformationFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $companyInformationFactory;

    /**
     * @var ContactDetailsFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $contactDetailsFactory;

    /**
     * @var PersonalNameFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $personalNameFactory;

    /**
     * @var TimezoneInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $timezone;

    /**
     * @var AccountBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $accountBuilder;

    /**
     * @var DeviceBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $deviceBuilder;

    /**
     * @var CompanyInformationBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $companyInformationBuilder;

    /**
     * @var AddressBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $addressBuilder;

    /**
     * @var Format
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $format;

    /**
     * @var ResolverInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $resolver;

    public function __construct(
        CustomerFactory $customerFactory,
        PersonalInformationFactory $personalInformationFactory,
        CompanyInformationFactory $companyInformationFactory,
        ContactDetailsFactory $contactDetailsFactory,
        PersonalNameFactory $personalNameFactory,
        AddressBuilder $addressBuilder,
        AccountBuilder $accountBuilder,
        DeviceBuilder $deviceBuilder,
        CompanyInformationBuilder $companyInformationBuilder,
        TimezoneInterface $timezone,
        Format $format,
        ResolverInterface $resolver
    ) {
        $this->customerFactory = $customerFactory;
        $this->personalInformationFactory = $personalInformationFactory;
        $this->companyInformationFactory = $companyInformationFactory;
        $this->contactDetailsFactory = $contactDetailsFactory;
        $this->personalNameFactory = $personalNameFactory;
        $this->accountBuilder = $accountBuilder;
        $this->deviceBuilder = $deviceBuilder;
        $this->timezone = $timezone;
        $this->companyInformationBuilder = $companyInformationBuilder;
        $this->addressBuilder = $addressBuilder;
        $this->format = $format;
        $this->resolver = $resolver;
    }

    public function create(Order $order): Customer
    {
        $worldlineCustomer = $this->customerFactory->create();
        $worldlineCustomer->locale = $this->resolver->getLocale();

        $worldlineCustomer->personalInformation = $this->getPersonalInformation($order);
        $worldlineCustomer->merchantCustomerId = $this->format->limit(
            (string) $order->getCustomerId() ?: rand(100000, 999999),
            15
        );

        /** @var Address|null $billing */
        $billing = $order->getBillingAddress();
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
        if ($billing !== null) {
            $companyInformation = $this->companyInformationFactory->create();
            $companyInformation->name = $billing->getCompany();
            $worldlineCustomer->companyInformation = $companyInformation;

            $worldlineCustomer->contactDetails = $this->getContactDetails($order, $billing);
        }

        $worldlineCustomer->account = $this->accountBuilder->create($order);
        $worldlineCustomer->device = $this->deviceBuilder->create($order);
        $worldlineCustomer->accountType = $this->getAccountType($order);
        $worldlineCustomer->companyInformation = $this->companyInformationBuilder->create($order);
        $worldlineCustomer->billingAddress = $this->addressBuilder->create($order);

        return $worldlineCustomer;
    }

    private function getPersonalInformation(Order $order): PersonalInformation
    {
        $personalInformation = $this->personalInformationFactory->create();

        $personalName = $this->personalNameFactory->create();
        $personalName->title = $order->getCustomerPrefix();
        $personalName->firstName = $this->format->limit($order->getCustomerFirstname(), 15);
        $personalName->surnamePrefix = $order->getCustomerMiddlename();
        $personalName->surname = $this->format->limit($order->getCustomerLastname(), 35);

        $personalInformation->name = $personalName;
        $personalInformation->gender = $this->getCustomerGender($order);
        $personalInformation->dateOfBirth = $this->getDateOfBirth($order);

        return $personalInformation;
    }

    private function getCustomerGender(Order $order): string
    {
        return match ($order->getCustomerGender()) {
            self::GENDER_MALE => 'male',
            self::GENDER_FEMALE => 'female',
            default => 'unknown',
        };
    }

    private function getDateOfBirth(Order $order): string
    {
        $dateOfBirth = '';
        if ($order->getCustomerDob()) {
            $doBObject = $this->timezone->date($order->getCustomerDob());
            $dateOfBirth = $doBObject->format('Ymd');
        }

        return $dateOfBirth;
    }

    private function getContactDetails(Order $order, Address $billing): ContactDetails
    {
        $contactDetails = $this->contactDetailsFactory->create();
        $contactDetails->emailAddress = $this->format->limit($order->getCustomerEmail(), 70);
        $contactDetails->emailMessageType = self::EMAIL_MESSAGE_TYPE;
        $contactDetails->phoneNumber = $billing->getTelephone();
        $contactDetails->faxNumber = $billing->getFax();

        return $contactDetails;
    }

    private function getAccountType(Order $order): string
    {
        return $order->getCustomerIsGuest() ? self::ACCOUNT_TYPE_NONE : self::ACCOUNT_TYPE_EXISTING;
    }
}
