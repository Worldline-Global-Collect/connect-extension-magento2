<?php

declare(strict_types=1);

namespace Worldline\Connect\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\Refund\PaymentNotRefundableException;
use Worldline\Connect\Model\StatusResponseManagerInterface;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\RequestBuilder\Refund\RefundRequestBuilder;
use Worldline\Connect\Sdk\V1\ResponseException;

class RefundCommand implements CommandInterface
{
    public function __construct(
        private readonly RefundRequestBuilder $refundRequestBuilder,
        private readonly ClientInterface $worldlineClient,
        private readonly ApiErrorHandler $apiErrorHandler,
        private readonly StatusResponseManagerInterface $statusResponseManager
    ) {
    }

    public function execute(array $commandSubject): mixed
    {
        /** @var Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        $worldlinePaymentId = $payment->getData('refund_transaction_id');
        $storeId = (int) $payment->getOrder()->getStoreId();

        try {
            $this->assertRefundable($worldlinePaymentId, $storeId);

            $this->refundPayment($worldlinePaymentId, $payment, $storeId);
        } catch (PaymentNotRefundableException $e) {
            $this->apiErrorHandler->handleLocalizedError($e);
        } catch (ResponseException $e) {
            $this->apiErrorHandler->handleError($e);
        }

        return null;
    }

    /**
     * @param string $worldlinePaymentId
     * @param int $scopeId
     *
     * @throws PaymentNotRefundableException
     * @throws ResponseException
     */
    private function assertRefundable(string $worldlinePaymentId, int $scopeId): void
    {
        $response = $this->worldlineClient->worldlinePayment($worldlinePaymentId, $scopeId);

        if (!$response->statusOutput->isRefundable) {
            throw PaymentNotRefundableException::create($worldlinePaymentId);
        }
    }

    /**
     * @param string $worldlinePaymentId
     * @param Payment $payment
     * @param int $scopeId
     *
     * @throws LocalizedException
     */
    private function refundPayment(string $worldlinePaymentId, Payment $payment, int $scopeId): void
    {
        $creditMemo = $payment->getCreditmemo();

        /**
         * Set by Observer\Adminhtml\SetCreditmemoRefundReason from the admin credit memo form,
         * for Wero orders only. Empty for every other payment method, in which case the SDK
         * leaves refundReason out of the request entirely.
         */
        $refundReason = (string) $creditMemo->getData(Config::REFUND_REASON_KEY);

        $response = $this->worldlineClient->worldlineRefund(
            $worldlinePaymentId,
            $this->refundRequestBuilder->build(
                $creditMemo->getOrder(),
                (float) $creditMemo->getGrandTotal(),
                $refundReason
            ),
            null,
            $scopeId
        );

        $this->statusResponseManager->set($payment, $response->id, $response);

        $payment->setLastTransId($response->id);
        $payment->setTransactionId($response->id);
    }
}
