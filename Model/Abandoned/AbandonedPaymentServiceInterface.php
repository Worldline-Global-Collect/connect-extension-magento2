<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Abandoned;

use Magento\Sales\Model\Order;

/**
 * Determines what really happened to the Worldline payment of an order that is stuck in
 * "pending payment" because the customer abandoned the payment page or the 3DS/ACS challenge,
 * and drives the order to a consistent final state.
 *
 * The implementation must be usable from a frontend observer as well as from cron, so it never
 * depends on the checkout session or on any other session state.
 */
// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface AbandonedPaymentServiceInterface
{
    /** The payment succeeded at Worldline; the order was updated and must never be cancelled. */
    public const OUTCOME_COMPLETED = 'completed';

    /** The payment finally failed or was cancelled at Worldline; the order was updated. */
    public const OUTCOME_DENIED = 'denied';

    /** No authorisation was ever completed; the payment was cancelled and the order with it. */
    public const OUTCOME_ABANDONED = 'abandoned';

    /** The outcome could not be determined; the order is untouched and the caller must not act. */
    public const OUTCOME_UNKNOWN = 'unknown';

    /**
     * Resolve the outcome of a possibly abandoned payment and apply it to the order.
     *
     * @param Order $order
     * @return string One of the OUTCOME_* constants
     */
    public function resolve(Order $order): string;
}
