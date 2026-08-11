<?php
declare(strict_types=1);

namespace Worldline\Connect\Model\DelayedCapture;

use DateTime;
use DateTimeZone;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction as TransactionResource;
use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory as TransactionCollectionFactory;
use Psr\Log\LoggerInterface;
use Throwable;
use Worldline\Connect\Model\Worldline\Action\CapturePayment;
use Worldline\Connect\PaymentMethod\PaymentMethods;

use function __;

class DelayedCaptureService
{
    private const CAPTURE_REQUEST_SENT_KEY = 'captureRequestSent';
    private const CAPTURED_KEY = 'captured';
    private const RETRIES_KEY = 'retries';
    private const MAX_RETRIES = 6;
    private const MAX_LOOKBACK_DAYS = 30;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly TransactionCollectionFactory $transactionCollectionFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TransactionResource $transactionResource,
        private readonly CapturePayment $capturePayment,
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(): void
    {
        foreach (PaymentMethods::PAYMENT_GROUPS as $groupCode) {
            $captureConfig = $this->scopeConfig->getValue("payment/{$groupCode}/capture_config");
            if ($captureConfig !== MethodInterface::ACTION_AUTHORIZE) {
                continue;
            }

            $days = $this->scopeConfig->getValue("payment/{$groupCode}/auto_capture_delay_days");
            if (empty($days) || $days === 'never') {
                continue;
            }

            $this->processGroup(PaymentMethods::GROUP_METHODS[$groupCode], (int)$days);
        }
    }

    private function processGroup(array $methods, int $days): void
    {
        $now = new DateTime('now', new DateTimeZone('UTC'));

        $cutoffDate = (clone $now)
            ->modify("-{$days} days")
            ->format('Y-m-d H:i:s');

        /**
         * Never look further back than the configured search window (default: 1 month).
         *
         * This prevents scanning the entire order history while still allowing
         * older transactions to be picked up if the cron was delayed or unavailable.
         */
        $lookbackDate = (clone $now)
            ->modify('-' . self::MAX_LOOKBACK_DAYS . ' days')
            ->format('Y-m-d H:i:s');

        $collection = $this->transactionCollectionFactory->create();
        $collection->addFieldToFilter('txn_type', Transaction::TYPE_AUTH);
        $collection->addFieldToFilter('is_closed', 0);
        $collection->addFieldToFilter('created_at', ['lteq' => $cutoffDate]);
        $collection->addFieldToFilter('created_at', ['gteq' => $lookbackDate]);
        $collection->getSelect()->join(
            ['sop' => $collection->getTable('sales_order_payment')],
            'main_table.payment_id = sop.entity_id',
            []
        )->where('sop.method IN (?)', $methods);

        $childrenSubquery = $collection->getConnection()->select()
            ->from($collection->getTable('sales_payment_transaction'), 'parent_id')
            ->where('parent_id IS NOT NULL');

        $collection->getSelect()->where(
            'main_table.parent_id IS NOT NULL OR main_table.transaction_id NOT IN (?)',
            $childrenSubquery
        );

        foreach ($collection as $transaction) {
            $additionalInfo = $transaction->getAdditionalInformation() ?? [];

            if (!empty($additionalInfo[self::CAPTURED_KEY])) {
                continue;
            }

            $retries = (int)($additionalInfo[self::RETRIES_KEY] ?? 0);
            if ($retries >= self::MAX_RETRIES) {
                continue;
            }

            /**
             * Isolate each transaction: a single failure must never abort the whole run and
             * leave later eligible orders uncaptured.
             */
            try {
                $this->attemptCapture($transaction, $retries);
            } catch (Throwable $e) {
                $this->logger->error('Delayed capture: unexpected error while processing transaction', [
                    'txn_id' => $transaction->getTxnId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function attemptCapture(Transaction $transaction, int $retries): void
    {
        $transaction->setAdditionalInformation(self::CAPTURE_REQUEST_SENT_KEY, true);
        $this->transactionResource->save($transaction);

        $order = null;
        try {
            $order = $this->orderRepository->get($transaction->getOrderId());
            $payment = $order->getPayment();
            $payment->setLastTransId($transaction->getTxnId());

            $this->capturePayment->process($payment, $order->getBaseGrandTotal());

            /**
             * Mark the request as sent/captured only AFTER a confirmed successful response, and
             * close the authorization so it is not reprocessed. The previous implementation set
             * this flag BEFORE the call, so a mid-call fatal left it stuck and the transaction
             * was skipped forever.
             */
            $transaction->setAdditionalInformation(self::CAPTURE_REQUEST_SENT_KEY, true);
            $transaction->setAdditionalInformation(self::CAPTURED_KEY, true);
            $transaction->setIsClosed(1);
            $this->transactionResource->save($transaction);
        } catch (Throwable $e) {
            $newRetries = $retries + 1;

            $this->logger->error('Delayed capture failed', [
                'order_id' => $transaction->getOrderId(),
                'txn_id' => $transaction->getTxnId(),
                'retries' => $newRetries,
                'error' => $e->getMessage(),
            ]);

            $transaction->setAdditionalInformation(self::CAPTURE_REQUEST_SENT_KEY, false);
            $transaction->setAdditionalInformation(self::CAPTURED_KEY, false);
            $transaction->setAdditionalInformation(self::RETRIES_KEY, $newRetries);
            $this->transactionResource->save($transaction);

            if ($newRetries >= self::MAX_RETRIES) {
                $this->logger->error(
                    'Delayed capture reached the maximum number of retries; it will stop retrying this transaction',
                    [
                        'order_id' => $transaction->getOrderId(),
                        'txn_id' => $transaction->getTxnId(),
                    ]
                );
            }

            if ($order !== null) {
                $order->addStatusHistoryComment(
                    __('Failed to send capture request to Worldline. Webhook is not sent and the transaction status is not updated.')
                )->setIsCustomerNotified(false);
                $this->orderRepository->save($order);
            }
        }
    }
}
