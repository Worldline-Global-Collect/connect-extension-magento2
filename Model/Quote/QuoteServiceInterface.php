<?php

namespace Worldline\Connect\Model\Quote;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Interface QuoteServiceInterface
 *
 * @package Worldline\Connect\Model\Quote
 */
interface QuoteServiceInterface
{
    /**
     * Retrieve quote by id.
     *
     * @param int $quoteId
     *
     * @return CartInterface|null
     */
    public function getQuoteById(int $quoteId): ?CartInterface;

    /**
     * Retrieve quote by reserved order ID.
     *
     * @param string $reservedOrderId
     *
     * @return CartInterface|null
     */
    public function getQuoteByReservedOrderId(string $reservedOrderId): ?CartInterface;
}
