<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Worldline\Connect\Helper\Format;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer\AccountBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer\AddressBuilder as BillingAddressBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer\CompanyInformationBuilder;
use Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer\DeviceBuilder;
use Worldline\Connect\Sdk\V1\Domain\CompanyInformationFactory;
use Worldline\Connect\Sdk\V1\Domain\ContactDetails;
use Worldline\Connect\Sdk\V1\Domain\ContactDetailsFactory;
use Worldline\Connect\Sdk\V1\Domain\Customer;
use Worldline\Connect\Sdk\V1\Domain\CustomerFactory;
use Worldline\Connect\Sdk\V1\Domain\PersonalInformation;
use Worldline\Connect\Sdk\V1\Domain\PersonalInformationFactory;

use function rand;

/**
 * Class CustomerBuilder
 */
class CustomerBuilder
{
    public const EMAIL_MESSAGE_TYPE = 'html';
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
     * @var BillingAddressBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $billingAddressBuilder;

    /**
     * @var PersonalNameBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $nameBuilder;

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
        BillingAddressBuilder $billingAddressBuilder,
        AccountBuilder $accountBuilder,
        DeviceBuilder $deviceBuilder,
        CompanyInformationBuilder $companyInformationBuilder,
        PersonalNameBuilder $nameBuilder,
        Format $format,
        ResolverInterface $resolver
    ) {
        $this->customerFactory = $customerFactory;
        $this->personalInformationFactory = $personalInformationFactory;
        $this->companyInformationFactory = $companyInformationFactory;
        $this->contactDetailsFactory = $contactDetailsFactory;
        $this->accountBuilder = $accountBuilder;
        $this->deviceBuilder = $deviceBuilder;
        $this->companyInformationBuilder = $companyInformationBuilder;
        $this->nameBuilder = $nameBuilder;
        $this->billingAddressBuilder = $billingAddressBuilder;
        $this->format = $format;
        $this->resolver = $resolver;
    }

    /**
     * @throws LocalizedException
     */
    public function create(Order $order): Customer
    {
        $worldlineCustomer = $this->customerFactory->create();
        $worldlineCustomer->locale = $this->resolver->getLocale();

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
            $worldlineCustomer->personalInformation = $this->getPersonalInformation($billing);
            $worldlineCustomer->contactDetails = $this->getContactDetails($billing);
        }

        $worldlineCustomer->account = $this->accountBuilder->create($order);
        $worldlineCustomer->device = $this->deviceBuilder->create($order);
        $worldlineCustomer->accountType = $this->getAccountType($order);
        $worldlineCustomer->companyInformation = $this->companyInformationBuilder->create($order);
        $worldlineCustomer->billingAddress = $this->billingAddressBuilder->create($order);

        return $worldlineCustomer;
    }

    private function getPersonalInformation(Address $billing): PersonalInformation
    {
        $personalInformation = $this->personalInformationFactory->create();
        $personalInformation->name = $this->nameBuilder->create($billing);

        return $personalInformation;
    }

    private function getContactDetails(Address $billing): ContactDetails
    {
        $contactDetails = $this->contactDetailsFactory->create();
        $contactDetails->emailAddress = $this->format->limit($billing->getEmail(), 70);
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
