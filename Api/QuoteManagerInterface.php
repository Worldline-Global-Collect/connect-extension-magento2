<?php
declare(strict_types=1);

namespace Worldline\Connect\Api;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;

interface QuoteManagerInterface
{
    /**
     * @param int $cartId
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    public function getQuote(int $cartId): CartInterface;

    /**
     * @param string $cartId
     * @param string $email
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    public function getQuoteForGuest(string $cartId, string $email): CartInterface;

    /**
     * @param string $reservedOrderId
     * @return CartInterface|null
     */
    public function getQuoteByReservedOrderId(string $reservedOrderId): ?CartInterface;

    /**
     * @param string $paymentId
     * @return CartInterface|null
     */
    public function getQuoteByWorldlinePaymentId(string $paymentId): ?CartInterface;

    /**
     * Get all quotes whose contains worldline payment ID and Uncertain worldline status created in the last day
     *
     * @return array
     */
    public function getUncertainWorldlineQuotes(): array;

    /**
     * @return void
     */
    public function clearQuote(): void;

    /**
     * @param CartInterface $quote
     * @param bool $active Default value is true, pass false if you want to deactivate quote
     */
    public function activateQuote(CartInterface $quote, bool $active = true): void;

    /**
     * @param CartInterface $quote
     * @return void
     */
    public function save(CartInterface $quote): void;
}
