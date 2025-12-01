<?php

namespace Worldline\Connect\Api;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Worldline\Connect\Model\Order\SkipOrderCreationException;

interface WorldlineStatusCheckerInterface
{
    /**
     * Fetches and checks Worldline Status, and creates Magento order
     *
     * @param CartInterface $quote
     * @param string $hostedCheckoutId
     * @param bool $createPending Create Magento order in the 'Pending' status for uncertain transactions
     *
     * @return string
     */
    public function processQuote(CartInterface $quote, string $hostedCheckoutId, bool $createPending = false): string;

    /**
     * Process uncertain quote
     *
     * @param CartInterface $quote
     *
     * @return OrderInterface
     *
     * @throws SkipOrderCreationException
     * @throws LocalizedException
     * @throws CouldNotSaveException
     */
    public function processUncertainQuote(CartInterface $quote): OrderInterface;

    /**
     * Checks if transaction is cancelled and update quote
     *
     * @param CartInterface $quote
     * @param string $hostedCheckoutId
     *
     * @return string Reserved order ID from the quote
     */
    public function isCancelled(CartInterface $quote, string $hostedCheckoutId): string;
}
