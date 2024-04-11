<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\Action;

use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\StatusResponseManager;
use Worldline\Connect\Model\Transaction\TransactionManager;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\Status\Payment\ResolverInterface;
use Worldline\Connect\Sdk\V1\Domain\Payment;

class GetInlinePaymentStatus extends AbstractAction implements ActionInterface
{
    /**
     * @var ResolverInterface
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $statusResolver;

    public function __construct(
        StatusResponseManager $statusResponseManager,
        ClientInterface $worldlineClient,
        TransactionManager $transactionManager,
        ConfigInterface $config,
        ResolverInterface $resolver,
    ) {
        $this->statusResolver = $resolver;
        parent::__construct($statusResponseManager, $worldlineClient, $transactionManager, $config);
    }

    /**
     * @throws LocalizedException
     * @throws InvalidArgumentException
     * @throws NoSuchEntityException
     */
    // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength.FunctionLength, SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint, Generic.Metrics.CyclomaticComplexity.TooHigh
    public function process(Order $order, Payment $payment)
    {
        $this->validateResponse($payment);

        /** @var OrderPayment $orderPayment */
        $orderPayment = $order->getPayment();

        $this->statusResolver->resolve($order, $payment);

        $order->addRelatedObject($orderPayment);

        $orderPayment->setAdditionalInformation(Config::PAYMENT_ID_KEY, $payment->id);
        $orderPayment->setAdditionalInformation(Config::PAYMENT_STATUS_KEY, $payment->status);
        $orderPayment->setAdditionalInformation(Config::PAYMENT_STATUS_CODE_KEY, $payment->statusOutput->statusCode);
    }

    /**
     * @throws LocalizedException
     */
    private function validateResponse(Payment $response)
    {
        if (!$response->paymentOutput) {
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            $msg = __('Your payment was rejected or a technical error occured during processing.');
            // phpcs:ignore SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFallbackGlobalName
            throw new LocalizedException(__($msg));
        }
    }
}
