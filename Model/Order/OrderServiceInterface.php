<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Order;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Worldline\Connect\Sdk\V1\Domain\Payment;

// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface OrderServiceInterface
{
    /**
     * @param string $incrementId
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    public function getByIncrementId(string $incrementId): OrderInterface;

    /**
     * @param string $hostedCheckoutId
     * @return OrderInterface
     * @throws NoSuchEntityException
     */
    public function getByHostedCheckoutId(string $hostedCheckoutId): OrderInterface;

    /**
     * @param OrderInterface $order
     */
    public function save(OrderInterface $order): void;

    /**
     * Creates new order from quote.
     *
     * @param CartInterface $quote
     * @param int $counter Count order creation attempts
     *
     * @return OrderInterface
     *
     * @throws LocalizedException
     * @throws CouldNotSaveException
     */
    public function createOrder(CartInterface $quote, int $counter = 0): OrderInterface;

    /**
     * Creates new order by quote from asynchronous processes (webhooks or fallback mechanism).
     *
     * @param CartInterface $quote
     * @param Payment $worldlinePayment
     *
     * @return OrderInterface
     *
     * @throws LocalizedException
     * @throws CouldNotSaveException
     * @throws SkipOrderCreationException
     */
    public function createOrderAsync(CartInterface $quote, Payment $worldlinePayment): OrderInterface;

    /**
     * Creates new order from quote and process order status.
     *
     * @param CartInterface $quote
     * @param Payment $worldlinePayment
     * @param bool $async If the parameter is set to true, the function is called from an asynchronous process (webhook or fallback mechanism).
     *
     * @return OrderInterface
     *
     * @throws LocalizedException
     * @throws CouldNotSaveException
     * @throws SkipOrderCreationException
     */
    public function createOrderAndProcessStatus(CartInterface $quote, Payment $worldlinePayment, bool $async = false): OrderInterface;

    /**
     * Retrieves order by quote.
     *
     * @param CartInterface $quote
     *
     * @return OrderInterface|null
     */
    public function getOrderByQuote(CartInterface $quote): ?OrderInterface;

    /**
     * Checks if cancelled order exists for the quote
     *
     * @param CartInterface $quote
     * @return bool
     */
    public function cancelledOrderExists(CartInterface $quote): bool;
}
