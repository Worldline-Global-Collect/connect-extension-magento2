<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Action;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Helper\Data;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\StatusResponseManager;
use Worldline\Connect\Model\Transaction\TransactionManager;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\StatusInterface;
use Worldline\Connect\Sdk\V1\Domain\ApprovePaymentRequestFactory;
use Worldline\Connect\Sdk\V1\Domain\CapturePaymentRequestFactory;
use Worldline\Connect\Sdk\V1\Domain\OrderApprovePaymentFactory;
use Worldline\Connect\Sdk\V1\Domain\OrderReferencesApprovePaymentFactory;

use function ctype_digit;
use function __;

class CapturePayment extends AbstractAction implements ActionInterface
{
    public function __construct(
        StatusResponseManager $statusResponseManager,
        ClientInterface $worldlineClient,
        TransactionManager $transactionManager,
        ConfigInterface $config,
        private readonly ApprovePaymentRequestFactory $approvePaymentRequestFactory,
        private readonly OrderApprovePaymentFactory $orderApprovePaymentFactory,
        private readonly OrderReferencesApprovePaymentFactory $orderReferencesApprovePaymentFactory,
        private readonly CapturePaymentRequestFactory $capturePaymentRequestFactory,
    ) {
        parent::__construct(
            $statusResponseManager,
            $worldlineClient,
            $transactionManager,
            $config
        );
    }

    public function process(Payment $payment, mixed $amount): void
    {
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        /**
         * Always operate on the real Worldline payment id, never a
         * hosted-checkout id / Magento txn_id (which can be a UUID and yields 404
         * UNKNOWN_PAYMENT_ID).
         */
        $worldlinePaymentId = $this->resolveWorldlinePaymentId($payment);

        /**
         * The correct Worldline operation depends on the payment's current
         * status. Calling approve on a payment that is not PENDING_APPROVAL returns
         * 402 UNKNOWN_ORDER_OR_NOT_PENDING.
         */
        $currentPayment = $this->worldlineClient->worldlinePayment($worldlinePaymentId, $storeId);

        switch ((string) $currentPayment->status) {
            case StatusInterface::PENDING_APPROVAL:
                $this->approve($order, $worldlinePaymentId, $amount, $storeId);
                break;

            case StatusInterface::PENDING_CAPTURE:
            case StatusInterface::CAPTURE_IN_PROGRESS:
                $this->capture($worldlinePaymentId, $amount, $storeId);
                break;

            case StatusInterface::CAPTURE_REQUESTED:
            case StatusInterface::CAPTURED:
            case StatusInterface::PAID:
                // Already captured/requested — nothing to send; just sync local state.
                $this->postProcess($payment, $currentPayment);
                return;

            default:
                throw new LocalizedException(
                    __(
                        'Worldline payment %1 is not in a capturable state (status: %2).',
                        $worldlinePaymentId,
                        (string) $currentPayment->status
                    )
                );
        }

        // Re-read the payment so local state is synced with the real payment id + latest status.
        $updatedPayment = $this->worldlineClient->worldlinePayment($worldlinePaymentId, $storeId);
        $this->postProcess($payment, $updatedPayment);
    }

    private function approve(Order $order, string $worldlinePaymentId, mixed $amount, mixed $storeId): void
    {
        $orderReferencesApprovePayment = $this->orderReferencesApprovePaymentFactory->create();
        $orderReferencesApprovePayment->merchantReference = $order->getIncrementId();

        $orderApprovePayment = $this->orderApprovePaymentFactory->create();
        $orderApprovePayment->references = $orderReferencesApprovePayment;

        $approvePaymentRequest = $this->approvePaymentRequestFactory->create();
        $approvePaymentRequest->order = $orderApprovePayment;
        $approvePaymentRequest->amount = Data::formatWorldlineAmount($amount);

        $this->worldlineClient->worldlinePaymentApprove($worldlinePaymentId, $approvePaymentRequest, $storeId);
    }

    private function capture(string $worldlinePaymentId, mixed $amount, mixed $storeId): void
    {
        $capturePaymentRequest = $this->capturePaymentRequestFactory->create();
        $capturePaymentRequest->amount = Data::formatWorldlineAmount($amount);
        $capturePaymentRequest->isFinal = true;

        $this->worldlineClient->worldlinePaymentCapture($worldlinePaymentId, $capturePaymentRequest, $storeId);
    }

    /**
     * Prefer the stored Worldline payment id; fall back to the last transaction id only when it
     * is a numeric Connect payment id (never a hosted-checkout UUID).
     */
    private function resolveWorldlinePaymentId(Payment $payment): string
    {
        $paymentId = (string) $payment->getAdditionalInformation(Config::PAYMENT_ID_KEY);
        if ($paymentId !== '') {
            return $paymentId;
        }

        $lastTransId = (string) $payment->getLastTransId();
        if ($lastTransId !== '' && ctype_digit($lastTransId)) {
            return $lastTransId;
        }

        throw new LocalizedException(
            __('No Worldline payment id available to capture order %1.', $payment->getOrder()->getIncrementId())
        );
    }
}
