<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;
use Worldline\Connect\Sdk\V1\Domain\AbstractOrderStatus;
use Worldline\Connect\Sdk\V1\Domain\APIError;
use Worldline\Connect\Sdk\V1\Domain\Capture;
use Worldline\Connect\Sdk\V1\Domain\Payment as WorldlinePayment;
use Worldline\Connect\Sdk\V1\Domain\RefundResult;

use function array_key_exists;
use function is_array;

/**
 * Class StatusResponseManager
 *
 * @package Worldline\Connect\Model
 * @deprecated
 */
class StatusResponseManager implements StatusResponseManagerInterface
{
    /**
     * @var TransactionRepository
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $transactionRepository;

    /**
     * StatusResponseManager constructor.
     *
     * @param TransactionRepository $transactionRepository
     */
    public function __construct(
        TransactionRepository $transactionRepository
    ) {
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Retrieve last PaymentResponse object stored in transaction additionalInformation. It contains canonical
     * information about a payment, such as isCancellable or isRefundable and isAuthorized values.
     *
     * @param string $transactionId
     * @return WorldlinePayment|false
     * @throws InputException
     * @deprecated This kind of information needs to be stored on the transaction, not on the payment object. Use
     *     \Worldline\Connect\Model\Transaction\TransactionManager::getResponseDataFromTransaction() instead
     */
    public function get(Payment $payment, $transactionId)
    {
        $orderStatus = false;
        /** @var Transaction $transaction */
        $transaction = $this->transactionRepository->getByTransactionId(
            $transactionId, // @phpstan-ignore-line
            $payment->getId(),
            $payment->getOrder()->getId()
        );

        if ($transaction && $classPath = $transaction->getAdditionalInformation(self::TRANSACTION_CLASS_KEY)) {
            /** @var WorldlinePayment $orderStatus */
            $orderStatus = new $classPath();
            $orderStatus = $orderStatus->fromJson(
                $transaction->getAdditionalInformation(self::TRANSACTION_INFO_KEY)
            );
        } elseif ($additionalInfo = $payment->getTransactionAdditionalInfo()) {
            // If transaction does not yet exist
            $classPath = $additionalInfo[self::TRANSACTION_CLASS_KEY];
            /** @var WorldlinePayment $orderStatus */
            $orderStatus = new $classPath();
            $orderStatus = $orderStatus->fromJson(
                $additionalInfo[self::TRANSACTION_INFO_KEY]
            );
        }

        return $orderStatus;
    }

    /**
     * Update the PaymentResponse object stored in a transaction.
     *
     * @param Payment $payment
     * @param $transactionId
     * @param AbstractOrderStatus $orderStatus
     * @throws InputException
     * @throws LocalizedException
     * @deprecated This kind of information needs to be stored on the transaction, not on the payment object. Use
     *     \Worldline\Connect\Model\Transaction\TransactionManager::setResponseDataOnTransaction() instead.
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    public function set(
        Payment $payment,
        $transactionId,
        AbstractOrderStatus $orderStatus
    ) {
        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        if (!property_exists($orderStatus, 'status') || !property_exists($orderStatus, 'statusOutput')) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            throw new LocalizedException(__('Unknown payment status.'));
        }

        /** @var Transaction $transaction */
        $transaction = $this->transactionRepository
            ->getByTransactionId($transactionId, $payment->getId(), $payment->getOrder()->getId());

        if ($transaction && $transaction->getId()) {
            $this->setResponseDataOnTransaction($orderStatus, $transaction);
            $payment->getOrder()->addRelatedObject($transaction);
        } else {
            // phpcs:ignore SlevomatCodingStandard.Classes.ModernClassNameReference.ClassNameReferencedViaFunctionCall, SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            $objectClassName = get_class($orderStatus);
            $objectJson = $orderStatus->toJson();
            // If transaction does not (yet) exist
            $payment->setTransactionAdditionalInfo(self::TRANSACTION_CLASS_KEY, $objectClassName);
            $payment->setTransactionAdditionalInfo(self::TRANSACTION_INFO_KEY, $objectJson);
            // setTransactionAdditionalInfo's doc block type hints are broken, but passing (string, array) works.
            $payment->setTransactionAdditionalInfo(
                Transaction::RAW_DETAILS,
                $this->getVisibleInfo($orderStatus) // @phpstan-ignore-line
            );
        }

        $payment->setAdditionalInformation(Config::PAYMENT_STATUS_KEY, $orderStatus->status);
        $payment->setAdditionalInformation(Config::PAYMENT_STATUS_CODE_KEY, $orderStatus->statusOutput->statusCode);
    }

    /**
     * @param AbstractOrderStatus $responseData
     * @param Transaction $transaction
     * @throws LocalizedException
     * @deprecated Use the one on to the TransactionManager instead
     */
    public function setResponseDataOnTransaction(AbstractOrderStatus $responseData, Transaction $transaction)
    {
        // phpcs:ignore SlevomatCodingStandard.Classes.ModernClassNameReference.ClassNameReferencedViaFunctionCall, SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        $objectClassName = get_class($responseData);
        $objectJson = $responseData->toJson();
        $transaction->setAdditionalInformation(self::TRANSACTION_CLASS_KEY, $objectClassName);
        $transaction->setAdditionalInformation(self::TRANSACTION_INFO_KEY, $objectJson);
        if (
            $responseData instanceof RefundResult ||
            $responseData instanceof Capture ||
            $responseData instanceof WorldlinePayment
        ) {
            $transaction->setAdditionalInformation(
                Transaction::RAW_DETAILS,
                $this->getVisibleInfo($responseData)
            );
        }
    }

    // phpcs:disable SlevomatCodingStandard.TypeHints.DisallowArrayTypeHintSyntax.DisallowedArrayTypeHintSyntax
    /**
     * @return mixed[]
     */
    // phpcs:enable SlevomatCodingStandard.TypeHints.DisallowArrayTypeHintSyntax.DisallowedArrayTypeHintSyntax
    private function getVisibleInfo(
        RefundResult|Capture|WorldlinePayment $orderStatus
    ) {
        $visibleInfo = [];
        $visibleInfo['status'] = $orderStatus->status;

        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        $visibleInfo = array_merge(
            $visibleInfo,
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            get_object_vars($orderStatus->statusOutput)
        );

        $errors = $orderStatus->statusOutput->errors;
        if (is_array($errors) && array_key_exists(0, $errors)) {
            $error = $errors[0];
            $visibleInfo['error'] = [$error->code, $error->message];
        }

        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        $visibleInfo = array_map(
            [$this, 'formatInfo'],
            $visibleInfo
        );

        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        $visibleInfo = array_filter($visibleInfo);

        return $visibleInfo;
    }

    // phpcs:disable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    /**
     * If the transaction is not found, this will return an empty transaction object or null.
     *
     * @param string $transactionId
     * @return \Magento\Sales\Api\Data\TransactionInterface|null
     * @deprecated This kind of information needs to be stored on the transaction, not on the payment object
     */
    // phpcs:enable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    public function getTransactionBy($transactionId, InfoInterface $payment)
    {
        /** @var Payment $payment */
        /** @var Transaction $transaction */
        $transaction = $this->transactionRepository->getByTransactionId(
            $transactionId, // @phpstan-ignore-line
            $payment->getId(),
            $payment->getOrder()->getId()
        );
        return $transaction;
    }

    /**
     * Normalize values to be displayed in transaction info tab
     *
     * @param mixed $info
     * @return mixed
     */
    public function formatInfo($info)
    {
        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        if (is_bool($info)) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            $info = $info ? __('Yes') : __('No');
        // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
        } elseif (is_array($info)) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            $info = implode(', ', array_map([$this, __FUNCTION__], $info));
        } elseif ($info instanceof APIError) {
            $info = $info->id;
        }
        return $info;
    }

    // phpcs:disable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    /**
     * Persists the transaction
     *
     * @param \Magento\Sales\Api\Data\TransactionInterface $transaction
     * @return void
     * @deprecated Use the save()-method of the TransactionManager instead
     */
    // phpcs:enable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
    public function save(\Magento\Sales\Api\Data\TransactionInterface $transaction)
    {
        $this->transactionRepository->save($transaction);
    }
}
