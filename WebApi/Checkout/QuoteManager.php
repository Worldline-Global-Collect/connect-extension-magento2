<?php
declare(strict_types=1);

namespace Worldline\Connect\WebApi\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Psr\Log\LoggerInterface;
use Worldline\Connect\Api\QuoteManagerInterface;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Quote\Model\ResourceModel\Quote\Payment\CollectionFactory as QuotePaymentCollectionFactory;
use Worldline\Connect\Model\Worldline\StatusInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Worldline\Connect\Model\Config;

class QuoteManager implements QuoteManagerInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var QuoteCollectionFactory
     */
    private $quoteCollectionFactory;

    /**
     * @var QuotePaymentCollectionFactory
     */
    private $quotePaymentCollectionFactory;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $quotes = [];

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param QuotePaymentCollectionFactory $quotePaymentCollectionFactory
     * @param Session $checkoutSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteCollectionFactory $quoteCollectionFactory,
        QuotePaymentCollectionFactory $quotePaymentCollectionFactory,
        Session $checkoutSession,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        LoggerInterface $logger,
    ) {
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->quotePaymentCollectionFactory = $quotePaymentCollectionFactory;
        $this->checkoutSession = $checkoutSession;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->logger = $logger;
    }

    /**
     * @param int $cartId
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    public function getQuote(int $cartId): CartInterface
    {
        return $this->cartRepository->get($cartId);
    }

    /**
     * @param string $cartId
     * @param string $email
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    public function getQuoteForGuest(string $cartId, string $email): CartInterface
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quote = $this->cartRepository->get($quoteIdMask->getQuoteId());
        $quote->setCustomerEmail($email);

        // compatibility with magento 2.3.7
        $quote->setCustomerIsGuest(true);

        return $quote;
    }

    /**
     * @param string $reservedOrderId
     *
     * @return CartInterface|null
     * @throws NoSuchEntityException
     */
    public function getQuoteByReservedOrderId(string $reservedOrderId): ?CartInterface
    {
        if (empty($this->quotes[$reservedOrderId])) {
            // collection because the refund doesn't work in multi-shop context
            $collection = $this->quoteCollectionFactory->create();
            $collection->addFieldToFilter('reserved_order_id', ['eq' => $reservedOrderId]);
            $collection->getSelect()->limit(1);
            $quote = $collection->getFirstItem();
            if ($quote->isEmpty()) {
                return null;
            }
            // need for load additional attributes
            $loadedQuote = $this->cartRepository->get($quote->getId());
            $this->quotes[$reservedOrderId] = $loadedQuote;
        }

        return $this->quotes[$reservedOrderId];
    }

    /**
     * @param string $paymentId
     *
     * @return CartInterface|null
     * @throws NoSuchEntityException
     */
    public function getQuoteByWorldlinePaymentId(string $paymentId): ?CartInterface
    {
        $collection = $this->quotePaymentCollectionFactory->create();
        $collection->addFieldToFilter('additional_information', ['like' => '%' . $paymentId . '%']);
        $collection->setOrder('payment_id');
        $collection->getSelect()->limit(1);
        $quotePayment = $collection->getFirstItem();
        if ($quotePayment->isEmpty()) {
            $this->logger->warning('No quote_payment entity with payment_id: ' . $paymentId);
            return null;
        }

        $collection = $this->quoteCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['eq' => $quotePayment->getQuoteId()]);
        $collection->getSelect()->limit(1);
        $quote = $collection->getFirstItem();

        return $this->cartRepository->get($quote->getId());
    }

    /**
     * @inheritDoc
     */
    public function getUncertainWorldlineQuotes(): array
    {
        $paymentCollection = $this->quotePaymentCollectionFactory->create();

        $statusFilters = [];
        foreach (StatusInterface::UNCERTAIN_STATUSES as $status) {
            $statusFilters[] = ['like' => '%"' . Config::PAYMENT_STATUS_KEY . '":"' . $status . '"%'];
        }
        $paymentCollection->addFieldToFilter('additional_information', $statusFilters);

        $paymentCollection->addFieldToFilter(
            'additional_information',
            ['like' => '%"' . Config::HOSTED_CHECKOUT_ID_KEY . '"%']
        );

        $quoteIds = $paymentCollection->getColumnValues('quote_id');
        $quoteIds = array_unique($quoteIds);

        if (empty($quoteIds)) {
            return [];
        }

        $quoteIdFilter = $this->filterBuilder
            ->setField('entity_id')
            ->setValue($quoteIds)
            ->setConditionType('in')
            ->create();

        $oneDayAgo = (new \DateTime('-1 day'))->format('Y-m-d H:i:s');
        $dateMinFilter = $this->filterBuilder
            ->setField('created_at')
            ->setValue($oneDayAgo)
            ->setConditionType('gteq')
            ->create();

        $tenMinutesAgo = (new \DateTime('-10 minutes'))->format('Y-m-d H:i:s');
        $dateMaxFilter = $this->filterBuilder
            ->setField('created_at')
            ->setValue($tenMinutesAgo)
            ->setConditionType('lteq')
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilters([$quoteIdFilter])
            ->addFilters([$dateMinFilter])
            ->addFilters([$dateMaxFilter])
            ->create();

        $results = $this->cartRepository->getList($searchCriteria);

        return $results->getItems();
    }

    /**
     * @inheritDoc
     */
    public function clearQuote(): void
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            if ($quote->getId()) {
                $quote->setIsActive(false);
                $this->cartRepository->save($quote);
            }

            $this->checkoutSession->clearQuote();
            $this->checkoutSession->clearStorage();
        } catch (LocalizedException $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function activateQuote(CartInterface $quote, bool $active = true): void
    {
        if ($quote->getIsActive() == $active) {
            return;
        }

        $quote->setIsActive($active);

        $this->save($quote);
    }

    public function save(CartInterface $quote): void
    {
        $this->cartRepository->save($quote);
    }
}
