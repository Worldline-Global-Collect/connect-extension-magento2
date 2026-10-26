<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Abandoned;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Psr\Log\LoggerInterface;
use Throwable;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\Worldline\Action\GetInlinePaymentStatus;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\Status\Payment\ResolverInterface;
use Worldline\Connect\Model\Worldline\StatusInterface;
use Worldline\Connect\Sdk\V1\Domain\Payment;
use Worldline\Connect\Sdk\V1\ResponseException;

use function __;
use function in_array;
use function sprintf;

class AbandonedPaymentService implements AbandonedPaymentServiceInterface
{
    /**
     * The only Worldline statuses in which no authorisation was ever completed, so the payment may
     * be cancelled and the order released. This is the ticket scenario: an abandoned 3DS/ACS
     * challenge leaves the payment in REDIRECTED or PENDING_PAYMENT.
     *
     * Every other status that is neither approved nor denied is deliberately excluded and yields
     * OUTCOME_UNKNOWN instead: AUTHORIZATION_REQUESTED, PENDING_APPROVAL, PENDING_COMPLETION,
     * PENDING_FRAUD_APPROVAL, PENDING_CAPTURE, CAPTURE_IN_PROGRESS, ACCOUNT_VERIFIED and the refund
     * statuses all describe a payment that is live or already authorised, and cancelling one would
     * destroy a payment that actually succeeded. The module says as much itself by listing
     * PENDING_APPROVAL in StatusInterface::SUCCESS_UNCERTAIN_STATUSES.
     */
    private const CANCELLABLE_STATUSES = [
        StatusInterface::CREATED,
        StatusInterface::REDIRECTED,
        StatusInterface::PENDING_PAYMENT,
    ];

    /**
     * Additional information flag that tells CancelPayment::process() that the payment has already
     * been settled at Worldline. Setting it before Order::cancel() keeps Magento's cancellation
     * flow (which runs the void command) from sending a second, pointless cancel request.
     */
    private const CANCELLED_AT_WORLDLINE_KEY = 'CANCELLED_IN_HOSTED_CHECKOUT';

    public function __construct(
        private readonly ClientInterface $client,
        private readonly GetInlinePaymentStatus $inlinePaymentStatus,
        private readonly ResolverInterface $statusResolver,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Order $order): string
    {
        if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
            return self::OUTCOME_UNKNOWN;
        }

        try {
            $payment = $this->fetchWorldlinePayment($order);
            if ($payment === null) {
                // Hosted checkout abandoned before anything was submitted: nothing to cancel.
                return $this->cancelOrder($order, 'Worldline: payment page abandoned, no payment was created.');
            }

            return $this->applyOutcome($order, $payment);
        } catch (Throwable $exception) {
            // The caller retries later, so a transient failure must never cancel an order.
            $this->logger->error(sprintf(
                'Worldline: unable to resolve the abandoned payment of order %s: %s',
                (string) $order->getIncrementId(),
                $exception->getMessage()
            ));

            return self::OUTCOME_UNKNOWN;
        }
    }

    /**
     * Load the Worldline payment of the order, or null when none was ever created.
     *
     * The payment id comes from the order payment's additional information, never from
     * getLastTransId(): for hosted checkout orders that field holds the hostedCheckoutId, which
     * the /payments/{id} resources reject with 404 UNKNOWN_PAYMENT_ID.
     *
     * @param Order $order
     * @return Payment|null
     * @throws LocalizedException
     */
    private function fetchWorldlinePayment(Order $order): ?Payment
    {
        /** @var OrderPayment $orderPayment */
        $orderPayment = $order->getPayment();
        $storeId = (int) $order->getStoreId();

        $paymentId = (string) $orderPayment->getAdditionalInformation(Config::PAYMENT_ID_KEY);
        if ($paymentId !== '') {
            return $this->client->worldlinePayment($paymentId, $storeId);
        }

        $hostedCheckoutId = (string) $orderPayment->getAdditionalInformation(Config::HOSTED_CHECKOUT_ID_KEY);
        if ($hostedCheckoutId === '') {
            throw new LocalizedException(__('Order %1 has no Worldline payment reference.', $order->getIncrementId()));
        }

        return $this->client->getHostedCheckout($hostedCheckoutId, $storeId)->createdPaymentOutput?->payment;
    }

    /**
     * @param Order $order
     * @param Payment $payment
     * @return string
     * @throws LocalizedException
     */
    private function applyOutcome(Order $order, Payment $payment): string
    {
        $outcome = $this->classify($payment->status);

        if ($outcome === self::OUTCOME_ABANDONED) {
            return $this->cancelWorldlinePayment($order, $payment);
        }

        if ($outcome === self::OUTCOME_UNKNOWN) {
            // A live or already authorised payment: leave the order untouched and let it retry.
            return $outcome;
        }

        // Approved and denied are both final: applying the status is all that is needed, the
        // status handler pool cancels the order itself when the payment was denied.
        $this->inlinePaymentStatus->process($order, $payment);
        $this->orderRepository->save($order);

        return $outcome;
    }

    /**
     * @param string|null $status
     * @return string
     */
    private function classify(?string $status): string
    {
        if (in_array($status, StatusInterface::APPROVED_STATUSES, true)) {
            return self::OUTCOME_COMPLETED;
        }

        if (in_array($status, StatusInterface::DENIED_STATUSES, true)) {
            return self::OUTCOME_DENIED;
        }

        if (in_array($status, self::CANCELLABLE_STATUSES, true)) {
            return self::OUTCOME_ABANDONED;
        }

        return self::OUTCOME_UNKNOWN;
    }

    /**
     * Cancel the unauthorised payment at Worldline and let the resulting status cancel the order.
     *
     * @param Order $order
     * @param Payment $payment
     * @return string
     * @throws LocalizedException
     */
    private function cancelWorldlinePayment(Order $order, Payment $payment): string
    {
        try {
            $cancelResponse = $this->client->worldlinePaymentCancel(
                (string) $payment->id,
                (int) $order->getStoreId()
            );
        } catch (ResponseException $exception) {
            return $this->settleUncancellablePayment($order, $exception);
        }

        $cancelledPayment = $cancelResponse->payment;
        if ($cancelledPayment === null) {
            return $this->cancelOrder($order, 'Worldline: payment cancelled after the payment page was abandoned.');
        }

        $this->statusResolver->resolve($order, $cancelledPayment);
        $this->orderRepository->save($order);

        return self::OUTCOME_ABANDONED;
    }

    /**
     * A payment that never reached authorisation cannot be cancelled: Worldline answers
     * 402 UNKNOWN_ORDER_OR_NOT_CANCELLABLE (code 400210), because there is nothing to cancel — an
     * abandoned 3DS challenge simply expires at the acquirer. That is the ticket's own scenario, so
     * it must settle the order rather than be treated as a failure. The status is re-read first, so
     * that a payment which moved on while we were cancelling is applied for real instead.
     *
     * @param Order $order
     * @param ResponseException $exception
     * @return string
     * @throws LocalizedException
     * @throws ResponseException
     */
    private function settleUncancellablePayment(Order $order, ResponseException $exception): string
    {
        $payment = $this->fetchWorldlinePayment($order);
        $outcome = $this->classify($payment?->status);

        if ($outcome === self::OUTCOME_ABANDONED) {
            return $this->cancelOrder(
                $order,
                'Worldline: the payment page was abandoned. The payment never reached authorisation '
                . 'and is therefore not cancellable at Worldline, so the order was cancelled locally.'
            );
        }

        if ($payment !== null && $outcome !== self::OUTCOME_UNKNOWN) {
            $this->inlinePaymentStatus->process($order, $payment);
            $this->orderRepository->save($order);

            return $outcome;
        }

        throw $exception;
    }

    /**
     * Cancel the Magento order without contacting Worldline.
     *
     * @param Order $order
     * @param string $comment
     * @return string
     */
    private function cancelOrder(Order $order, string $comment): string
    {
        /** @var OrderPayment $orderPayment */
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation(self::CANCELLED_AT_WORLDLINE_KEY, true);

        $order->cancel();
        $order->addCommentToStatusHistory($comment);
        $this->orderRepository->save($order);

        return self::OUTCOME_ABANDONED;
    }
}
