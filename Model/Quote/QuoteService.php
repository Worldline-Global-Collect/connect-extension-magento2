<?php

namespace Worldline\Connect\Model\Quote;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Class QuoteService
 *
 * @package Worldline\Connect\Model\Quote
 */
class QuoteService implements QuoteServiceInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $searchCriteriaBuilder;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(CartRepositoryInterface $quoteRepository, SearchCriteriaBuilder $searchCriteriaBuilder)
    {
        $this->quoteRepository = $quoteRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @inheritDoc
     *
     * @throws NoSuchEntityException
     */
    public function getQuoteById(int $quoteId): ?CartInterface
    {
        $quote = $this->quoteRepository->get($quoteId);

        if (!$quote->getId()) {
            return null;
        }

        return $quote;
    }

    /**
     * @inheritDoc
     */
    public function getQuoteByReservedOrderId(string $reservedOrderId): ?CartInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('reserved_order_id', $reservedOrderId)
            ->setPageSize(1)
            ->create();

        $quoteList = $this->quoteRepository->getList($searchCriteria)->getItems();

        if (count($quoteList) > 0) {
            return reset($quoteList);
        }

        return null;
    }
}
