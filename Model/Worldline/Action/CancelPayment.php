<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\Action;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\StatusResponseManager;
use Worldline\Connect\Model\Transaction\TransactionManager;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\Status\Payment\ResolverInterface;
use Worldline\Connect\Sdk\V1\ResponseException;

/**
 * @link https://developer.globalcollect.com/documentation/api/server/#__merchantId__payments__paymentId__cancel_post
 */
class CancelPayment extends AbstractAction implements ActionInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $orderRepository;

    /**
     * @var ResolverInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $statusResolver;

    /**
     * CancelPayment constructor.
     *
     * @param StatusResponseManager $statusResponseManager
     * @param ClientInterface $worldlineClient
     * @param TransactionManager $transactionManager
     * @param ConfigInterface $config
     * @param OrderRepositoryInterface $orderRepository
     * @param ResolverInterface $statusResolver
     */
    public function __construct(
        StatusResponseManager $statusResponseManager,
        ClientInterface $worldlineClient,
        TransactionManager $transactionManager,
        ConfigInterface $config,
        OrderRepositoryInterface $orderRepository,
        ResolverInterface $statusResolver
    ) {
        $this->orderRepository = $orderRepository;

        parent::__construct(
            $statusResponseManager,
            $worldlineClient,
            $transactionManager,
            $config
        );
        $this->statusResolver = $statusResolver;
    }

    /**
     * Cancel payment
     *
     * @param Order $order
     * @throws LocalizedException
     * @throws ResponseException
     */
    public function process(Order $order)
    {
        /** @var Order\Payment $payment */
        $payment = $order->getPayment();

        $transactionId = $payment->getLastTransId();
        /** @var Transaction $transaction */
        $transaction = $this->transactionManager->retrieveTransaction($transactionId);
        if ($transaction !== null) {
            $transaction->setIsClosed(1);
            $order->addRelatedObject($transaction);
        }

        if ($payment->getAdditionalInformation('CANCELLED_IN_HOSTED_CHECKOUT')) {
            return;
        }

        $response = $this->worldlineClient->worldlinePaymentCancel($transactionId);
        $this->statusResolver->resolve($order, $response->payment);

        $this->orderRepository->save($order);

        $this->postProcess($payment, $response->payment);
    }
}
