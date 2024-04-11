<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace Worldline\Connect\Model\Worldline\Action;

use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Sdk\V1\Domain\GetHostedCheckoutResponse;

use function __;

class GetHostedCheckoutStatus implements ActionInterface
{
    public const PAYMENT_OUTPUT_SHOW_INSTRUCTIONS = 'SHOW_INSTRUCTIONS';
    public const CANCELLED_BY_CONSUMER = 'CANCELLED_BY_CONSUMER';

    /**
     * Load HostedCheckout instance from API and apply it to corresponding order
     *
     * @param Order $order
     * @param GetHostedCheckoutResponse $getHostedCheckoutResponse
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws InvalidArgumentException
     */
    public function process(Order $order, GetHostedCheckoutResponse $getHostedCheckoutResponse)
    {
        /** @var Order\Payment $payment */
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('CANCELLED_IN_HOSTED_CHECKOUT', true);
        if ($getHostedCheckoutResponse->status === self::CANCELLED_BY_CONSUMER) {
            $order->cancel();
            return;
        }

        if (!$getHostedCheckoutResponse->createdPaymentOutput) {
            $msg = __('Your payment was rejected or a technical error occured during processing.');
            throw new LocalizedException(__($msg));
        }

        $payment->setLastTransId($getHostedCheckoutResponse->createdPaymentOutput->payment->id);

        // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found, PSR12.ControlStructures.ControlStructureSpacing.FirstExpressionLine
        if (isset($getHostedCheckoutResponse->createdPaymentOutput->displayedData) // @phpstan-ignore-line
            && $getHostedCheckoutResponse->createdPaymentOutput->displayedData->displayedDataType
            // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedEqualOperator
            == self::PAYMENT_OUTPUT_SHOW_INSTRUCTIONS
        ) {
            $payment->setAdditionalInformation(
                Config::PAYMENT_SHOW_DATA_KEY,
                $getHostedCheckoutResponse->createdPaymentOutput->displayedData->toJson()
            );
        }

        $payment->setAdditionalInformation(
            Config::PRODUCT_TOKENIZE_KEY,
            $getHostedCheckoutResponse->createdPaymentOutput->tokens !== null ? '1' : '0'
        );
    }
}
