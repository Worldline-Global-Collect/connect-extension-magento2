<?php
declare(strict_types=1);

namespace Worldline\Connect\WebApi\Checkout;

use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\CustomerManagement;
use Worldline\Connect\Api\BaseCreatePaymentManagementInterface;
use Worldline\Connect\Api\QuoteManagerInterface;
use Worldline\Connect\Model\DataAssigner\DataAssignerInterface;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Api\QuoteRestorationInterface;
use Worldline\Connect\Model\Order\OrderServiceInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BaseCreatePaymentManagement implements BaseCreatePaymentManagementInterface
{
    private const FAILURE_URL = 'epayments/returns/failure';

    /**
     * @var QuoteManagerInterface
     */
    private $quoteManager;

    /**
     * @var PaymentInformationManagementInterface
     */
    private $paymentInformationManagement;

    /**
     * @var CustomerManagement
     */
    private $customerManagement;

    /**
     * @var DataAssignerInterface[]
     */
    private $dataAssignerPool;

    /**
     * @var QuoteRestorationInterface
     */
    private $quoteRestoration;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var OrderServiceInterface
     */
    private $orderService;

    public function __construct(
        QuoteManagerInterface $quoteManager,
        PaymentInformationManagementInterface $paymentInformationManagement,
        CustomerManagement $customerManagement,
        QuoteRestorationInterface $quoteRestoration,
        UrlInterface $urlBuilder,
        OrderServiceInterface $orderService,
        array $dataAssignerPool = []
    ) {
        $this->quoteManager = $quoteManager;
        $this->paymentInformationManagement = $paymentInformationManagement;
        $this->customerManagement = $customerManagement;
        $this->quoteRestoration = $quoteRestoration;
        $this->dataAssignerPool = $dataAssignerPool;
        $this->urlBuilder = $urlBuilder;
        $this->orderService = $orderService;
    }

    /**
     * Retrieve redirect url
     *
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @throws LocalizedException
     *
     * @return array redirect url
     */
    public function createRequest(
        int $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $quote = $this->quoteManager->getQuote($cartId);

        return $this->process($quote, $paymentMethod, $billingAddress);
    }

    /**
     * Retrieve redirect url for quest user
     *
     * @param string $cartId
     * @param PaymentInterface $paymentMethod
     * @param string $email
     * @param AddressInterface|null $billingAddress
     * @throws LocalizedException
     *
     * @return array array containing redirect url
     */
    public function createGuestRequest(
        string $cartId,
        PaymentInterface $paymentMethod,
        string $email,
        ?AddressInterface $billingAddress = null
    ): array {
        $quote = $this->quoteManager->getQuoteForGuest($cartId, $email);

        return $this->process($quote, $paymentMethod, $billingAddress);
    }

    private function process(
        CartInterface $quote,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {

        if ($this->orderService->cancelledOrderExists($quote)) {
            $payment = $quote->getPayment();
            $payment->setAdditionalInformation(Config::PAYMENT_STATUS_KEY, 'CANCELLED');
            $this->quoteManager->save($quote);

            return [
                'redirectUrl' => $this->urlBuilder->getUrl(self::FAILURE_URL)
            ];
        }

        if (!$quote->isVirtual()) {
            $this->validateAddress($quote->getShippingAddress());
            $this->validateAddress($quote->getBillingAddress());
        }

        $this->paymentInformationManagement->savePaymentInformation($quote->getId(), $paymentMethod, $billingAddress);

        if (!$quote->getCustomerIsGuest()) {
            if ($quote->getCustomerId()) {
                $this->customerManagement->validateAddresses($quote);
            }
        }

        // For PWA additional information is used, for luma - additional_data
        $additionalData = array_merge(
            (array)$paymentMethod->getAdditionalInformation(),
            (array)$paymentMethod->getAdditionalData()
        );

        $quote->reserveOrderId();

        foreach ($this->dataAssignerPool as $dataAssigner) {
            $dataAssigner->assign($quote->getPayment(), $additionalData);
        }

        $this->quoteRestoration->preserveQuoteId((int)$quote->getId());
        $this->quoteManager->activateQuote($quote, false);

        return [
            'redirectUrl' => (string) $quote->getPayment()->getAdditionalInformation(Config::REDIRECT_URL_KEY)
        ];
    }

    /**
     * Magento does not validate addresses in the backend if they are added on the checkout page.
     * The validation happens afterward when Magento creates the order and saves the addresses.
     * It breaks the order creation process.
     * To prevent this we need to validate the addresses before the payment.
     *
     * @param AddressInterface|null $address
     * @return void
     * @throws LocalizedException
     */
    private function validateAddress(?AddressInterface $address = null): void
    {
        if (!$address) {
            return;
        }

        $validationResult = $address->validate();
        if ($validationResult && is_array($validationResult)) {
            throw new LocalizedException(current($validationResult));
        }
    }
}
