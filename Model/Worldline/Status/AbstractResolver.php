<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Model\StatusResponseManagerInterface;
use Worldline\Connect\Sdk\V1\Domain\AbstractOrderStatus;

abstract class AbstractResolver
{
    protected const KEY_STATUS = 'status';
    protected const KEY_STATUS_CODE_CHANGE_DATE_TIME = 'status_code_change_date_time';

    /**
     * @var StatusResponseManagerInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $statusResponseManager;

    public function __construct(StatusResponseManagerInterface $statusResponseManager)
    {
        $this->statusResponseManager = $statusResponseManager;
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
    protected function isStatusNewerThanPreviousStatus(OrderInterface $order, AbstractOrderStatus $status)
    {
        /** @var Payment $payment */
        $payment = $order->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();

        $newStatusChangeDateTime = $status->statusOutput->statusCodeChangeDateTime; // @phpstan-ignore-line

        // phpcs:ignore PSR12.ControlStructures.ControlStructureSpacing.FirstExpressionLine, SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        if (array_key_exists(static::KEY_STATUS_CODE_CHANGE_DATE_TIME, $additionalInformation) &&
            $additionalInformation[static::KEY_STATUS_CODE_CHANGE_DATE_TIME] >= $newStatusChangeDateTime
        ) {
            return false;
        }

        // This is for legacy orders, can be removed when the Worldline object is no longer stored in the payment:
        $existingStatus = $this->statusResponseManager->get($payment, $status->id);

        // phpcs:ignore PSR12.ControlStructures.ControlStructureSpacing.FirstExpressionLine
        if ($existingStatus
            // phpcs:ignore PSR12.ControlStructures.ControlStructureSpacing.CloseParenthesisLine
            && $existingStatus->statusOutput->statusCodeChangeDateTime >= $newStatusChangeDateTime) {
            return false;
        }
        // --- end

        return true;
    }

    protected function updateStatusCodeChangeDate(OrderInterface $order, AbstractOrderStatus $status)
    {
        $this->updateAdditionalInformation(
            $order,
            static::KEY_STATUS_CODE_CHANGE_DATE_TIME,
            $status->statusOutput->statusCodeChangeDateTime // @phpstan-ignore-line
        );
    }

    protected function updateStatus(OrderInterface $order, AbstractOrderStatus $status)
    {
        $this->updateAdditionalInformation($order, static::KEY_STATUS, $status->status); // @phpstan-ignore-line
    }

    private function updateAdditionalInformation(OrderInterface $order, string $key, string $value)
    {
        /** @var Payment $orderPayment */
        $orderPayment = $order->getPayment();
        $orderPayment->setAdditionalInformation($key, $value);
    }

    protected function preparePayment(Payment $payment, AbstractOrderStatus $worldlineStatus)
    {
        $payment->setTransactionId($worldlineStatus->id);

        if (!$this->statusResponseManager->get($payment, $worldlineStatus->id)) {
            $this->updatePayment($payment, $worldlineStatus);
        }
    }

    /**
     * @throws LocalizedException
     */
    protected function updatePayment(Payment $payment, AbstractOrderStatus $worldlineStatus)
    {
        $this->statusResponseManager->set($payment, $worldlineStatus->id, $worldlineStatus);
    }
}
