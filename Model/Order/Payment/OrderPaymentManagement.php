<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Order\Payment;

use DateTime;
use LogicException;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Api\OrderPaymentManagementInterface;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\StatusResponseManagerInterface;
use Worldline\Connect\Sdk\V1\Domain\AbstractPaymentMethodSpecificOutput;
use Worldline\Connect\Sdk\V1\Domain\Payment as WorldlinePayment;

use function ctype_digit;
use function get_object_vars;

class OrderPaymentManagement implements OrderPaymentManagementInterface
{
    public const KEY_PAYMENT_STATUS = 'payment_status';
    public const KEY_PAYMENT_STATUS_CODE_CHANGE_DATE_TIME = 'payment_status_code_change_date_time';
    public const KEY_REFUND_STATUS = 'refund_status';
    public const KEY_REFUND_STATUS_CODE_CHANGE_DATE_TIME = 'refund_status_code_change_date_time';

    /**
     * @var DateTimeFactory
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $dateTimeFactory;

    /**
     * @var StatusResponseManagerInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $statusResponseManager;

    /**
     * OrderPaymentManagement constructor.
     *
     * @param DateTimeFactory $dateTimeFactory
     * @param StatusResponseManagerInterface $statusResponseManager This is only used for legacy orders, where the
     *     Worldline object is saved in the transaction
     */
    public function __construct(DateTimeFactory $dateTimeFactory, StatusResponseManagerInterface $statusResponseManager)
    {
        $this->dateTimeFactory = $dateTimeFactory;
        $this->statusResponseManager = $statusResponseManager;
    }

    /**
     * {@inheritDoc}
     */
    public function getWorldlinePaymentStatus(Payment $payment): string
    {
        // phpcs:ignore PSR12.ControlStructures.ControlStructureSpacing.FirstExpressionLine
        if (($status = $this->getAdditionalInformation($payment, Config::PAYMENT_STATUS_KEY)) ||
            ($status = $this->getLegacyProperty($payment, 'status'))
        ) {
            return $status;
        }

        throw new LogicException('No payment status found on payment object');
    }

    /**
     * {@inheritDoc}
     */
    public function getWorldlineRefundStatus(Payment $payment): string
    {
        // phpcs:ignore PSR12.ControlStructures.ControlStructureSpacing.FirstExpressionLine
        if (($status = $this->getAdditionalInformation($payment, self::KEY_REFUND_STATUS)) ||
            ($status = $this->getLegacyProperty($payment, 'status'))
        ) {
            return $status;
        }

        throw new LogicException('No refund status found on payment object');
    }

    /**
     * {@inheritDoc}
     */
    public function getWorldlinePaymentStatusCodeChangeDate(Payment $payment): DateTime
    {
        // phpcs:ignore PSR12.ControlStructures.ControlStructureSpacing.FirstExpressionLine
        if (($dateTime = $this->getAdditionalInformation($payment, self::KEY_PAYMENT_STATUS_CODE_CHANGE_DATE_TIME)) ||
            ($dateTime = $this->getLegacyProperty($payment, 'statusOutput', 'statusCodeChangeDateTime'))
        ) {
            return $this->dateTimeFactory->create($dateTime);
        }

        throw new LogicException('No payment status code change date/time found on payment object');
    }

    /**
     * {@inheritDoc}
     */
    public function getWorldlineRefundStatusCodeChangeDate(Payment $payment): DateTime
    {
        // phpcs:ignore PSR12.ControlStructures.ControlStructureSpacing.FirstExpressionLine
        if (($dateTime = $this->getAdditionalInformation($payment, self::KEY_REFUND_STATUS_CODE_CHANGE_DATE_TIME)) ||
            ($dateTime = $this->getLegacyProperty($payment, 'statusOutput', 'statusCodeChangeDateTime'))
        ) {
            return $this->dateTimeFactory->create($dateTime);
        }

        throw new LogicException('No refund status code change date/time found on payment object');
    }

    /**
     * {@inheritDoc}
     */
    public function getWorldlinePaymentProductId(Payment $payment): ?int
    {
        $productId = $this->getProductIdFromStoredPayment($payment);
        if ($productId !== null) {
            return $productId;
        }

        /**
         * Fallback only: this key holds what the shopper selected in the Magento checkout, not
         * what Worldline actually processed. The stored response always wins.
         */
        $selected = $this->getAdditionalInformation($payment, Config::PRODUCT_ID_KEY);

        return $selected !== null && $selected !== '' ? (int) $selected : null;
    }

    private function getProductIdFromStoredPayment(Payment $payment): ?int
    {
        $worldlinePaymentId = $this->resolveWorldlinePaymentId($payment);
        if ($worldlinePaymentId === '') {
            return null;
        }

        $status = $this->statusResponseManager->get($payment, $worldlinePaymentId);
        if (!$status instanceof WorldlinePayment || $status->paymentOutput === null) {
            return null;
        }

        /**
         * PaymentOutput carries one populated *PaymentMethodSpecificOutput branch out of nine.
         * They all extend AbstractPaymentMethodSpecificOutput, which is the only class that
         * declares paymentProductId - so walk the branches instead of hard-coding the redirect
         * one, and new products keep working.
         */
        foreach (get_object_vars($status->paymentOutput) as $output) {
            if ($output instanceof AbstractPaymentMethodSpecificOutput && $output->paymentProductId !== null) {
                return $output->paymentProductId;
            }
        }

        return null;
    }

    /**
     * The transaction holding the payment response is keyed by the Worldline payment id.
     * getLastTransId() is not enough: after a first credit memo it points at the refund
     * transaction, whose stored object is a RefundResult.
     *
     * @see \Worldline\Connect\Model\Worldline\Action\CapturePayment::resolveWorldlinePaymentId()
     */
    private function resolveWorldlinePaymentId(Payment $payment): string
    {
        $paymentId = (string) $this->getAdditionalInformation($payment, Config::PAYMENT_ID_KEY);
        if ($paymentId !== '') {
            return $paymentId;
        }

        $lastTransId = (string) $payment->getLastTransId();

        return ctype_digit($lastTransId) ? $lastTransId : '';
    }

    private function getAdditionalInformation(Payment $payment, string $key): ?string
    {
        $additionalInformation = $payment->getAdditionalInformation();
        return $additionalInformation[$key] ?? null;
    }

    private function getLegacyProperty(
        Payment $payment,
        string $key,
        // phpcs:ignore SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing
        ?string $nestedKey = null
    ): ?string {
        if ($status = $this->statusResponseManager->get($payment, $payment->getLastTransId())) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            if (property_exists($status, $key)) {
                if ($nestedKey === null) {
                    return $status->{$key};
                }

                // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
                if (property_exists($status->{$key}, $nestedKey)) {
                    return $status->{$key}->{$nestedKey};
                }
            }
        }
        return null;
    }
}
