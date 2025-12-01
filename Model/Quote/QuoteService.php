<?php

namespace Worldline\Connect\Model\Quote;

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
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(CartRepositoryInterface $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Retrieve quote by id.
     *
     * @param int $quoteId
     *
     * @return CartInterface|null
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
}
