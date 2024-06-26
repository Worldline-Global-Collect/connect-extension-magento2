<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\RequestBuilder\Common\Order\Customer\Account;

use DateTime;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory as PaymentCollectionFactory;
use Worldline\Connect\Sdk\V1\Domain\CustomerPaymentActivity;
use Worldline\Connect\Sdk\V1\Domain\CustomerPaymentActivityFactory;

class PaymentActivityBuilder
{
    /**
     * @var CustomerPaymentActivityFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $paymentActivityFactory;

    /**
     * @var PaymentCollectionFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $paymentCollectionFactory;

    /**
     * @var DateTimeFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $dateTimeFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $searchCriteriaBuilder;

    /**
     * @var OrderRepositoryInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $orderRepository;

    public function __construct(
        CustomerPaymentActivityFactory $paymentActivityFactory,
        PaymentCollectionFactory $paymentCollectionFactory,
        DateTimeFactory $dateTimeFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->paymentActivityFactory = $paymentActivityFactory;
        $this->paymentCollectionFactory = $paymentCollectionFactory;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
    }

    public function create(OrderInterface $order): CustomerPaymentActivity
    {
        $paymentActivity = $this->paymentActivityFactory->create();

        try {
            $paymentActivity->numberOfPaymentAttemptsLast24Hours = $this->getNumberOfPaymentAttemptsSince(
                $order,
                $this->dateTimeFactory->create('now -1 day')
            );
            $paymentActivity->numberOfPaymentAttemptsLastYear = $this->getNumberOfPaymentAttemptsSince(
                $order,
                $this->dateTimeFactory->create('now -1 year')
            );
            $paymentActivity->numberOfPurchasesLast6Months = $this->getNumberOfPurchasesLastSixMonths($order);
        // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
        } catch (LocalizedException $exception) {
            // Do nothing
        }

        return $paymentActivity;
    }

    /**
     * @param OrderInterface $order
     * @param DateTime $dateTime
     * @return int
     * @throws LocalizedException
     */
    private function getNumberOfPaymentAttemptsSince(OrderInterface $order, DateTime $dateTime): int
    {
        if ($order->getCustomerIsGuest()) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            throw new LocalizedException(__('Cannot get number of payment attempts for a guest order'));
        }
        if (!$order->getCustomerId()) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            throw new LocalizedException(__('No customer ID found'));
        }
        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
        /** @var \Magento\Sales\Model\ResourceModel\Order\Payment\Collection $paymentCollection */
        $paymentCollection = $this->paymentCollectionFactory->create();
        $paymentCollection
            ->join(
                ['o' => 'sales_order'],
                'main_table.parent_id = o.entity_id',
                ['customer_id' => 'o.customer_id']
            )
            ->addFieldToFilter('o.customer_id', (string) $order->getCustomerId())
            ->addFieldToFilter('o.entity_id', ['neq' => $order->getEntityId()])
            ->addFieldToFilter('o.created_at', ['gt' => $dateTime->format('Y-m-d H:i:s')]);
        return $paymentCollection->getSize();
    }

    /**
     * @param OrderInterface $order
     * @return int
     * @throws LocalizedException
     */
    private function getNumberOfPurchasesLastSixMonths(OrderInterface $order): int
    {
        if ($order->getCustomerIsGuest()) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            throw new LocalizedException(__('Cannot get number of purchases during the last six months for a guest'));
        }
        if (!$order->getCustomerId()) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            throw new LocalizedException(__('No customer ID found'));
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderInterface::CUSTOMER_ID, $order->getCustomerId())
            ->addFilter(OrderInterface::TOTAL_DUE, 0.01, 'lt')
            ->addFilter(OrderInterface::ENTITY_ID, $order->getEntityId(), 'neq')
            ->addFilter(
                'created_at',
                $this->dateTimeFactory->create('now -6 months')->format('Y-m-d H:i:s'),
                'gt'
            )
            ->create();

        $searchResults = $this->orderRepository->getList($searchCriteria);
        return $searchResults->getTotalCount();
    }
}
