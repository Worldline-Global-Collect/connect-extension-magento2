<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Action;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Gateway\Command\ApiErrorHandler;
use Worldline\Connect\Gateway\Command\CreatePaymentRequestBuilder;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\StatusResponseManager;
use Worldline\Connect\Model\Transaction\TransactionManager;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\StatusInterface;
use Worldline\Connect\Model\Worldline\Token\TokenService;
use Worldline\Connect\Sdk\V1\DeclinedPaymentException;
use Worldline\Connect\Sdk\V1\ResponseException;

class CreatePayment extends AbstractAction
{
    public function __construct(
        StatusResponseManager $statusResponseManager,
        ClientInterface $worldlineClient,
        TransactionManager $transactionManager,
        ConfigInterface $config,
        private readonly CreatePaymentRequestBuilder $createPaymentRequestBuilder,
        private readonly TokenService $tokenService,
        private readonly MerchantAction $merchantAction,
        private readonly ApiErrorHandler $apiErrorHandler,
    ) {
        parent::__construct(
            $statusResponseManager,
            $worldlineClient,
            $transactionManager,
            $config
        );
    }

    public function process(Payment $payment, bool $requiresApproval): void
    {
        try {
            $request = $this->createPaymentRequestBuilder->build($payment, $requiresApproval);
            $response = $this->worldlineClient->createPayment($request);
            $this->postProcess($payment, $response->payment);

            $this->tokenService->createByOrderAndPayment($payment->getOrder(), $response->payment);
            $this->merchantAction->handle($payment, $response);

            match ($response->payment->status) {
                StatusInterface::CANCELLED => $this->paymentCanceled($payment),
                StatusInterface::PENDING_APPROVAL => $this->paymentPendingApproval($payment),
                StatusInterface::PENDING_FRAUD_APPROVAL => $this->paymentPendingFraudApproval($payment),
                StatusInterface::CAPTURE_REQUESTED => $this->paymentCaptureRequested($payment),
                StatusInterface::REDIRECTED => $this->paymentRedirected(
                    $payment,
                    $response->merchantAction->redirectData->redirectURL
                ),
                default => $this->paymentNoop()
            };
        } catch (DeclinedPaymentException $e) {
            $this->paymentCanceled($payment);
            $this->postProcess($payment, $e->getCreatePaymentResult()->payment);
        } catch (ResponseException $e) {
            $this->apiErrorHandler->handleError($e);
        }
    }

    private function paymentCanceled(Payment $payment): void
    {
        $payment->setData('order_state', Order::STATE_CANCELED);

        $payment->setIsTransactionClosed(true);
        $payment->setIsTransactionPending(true);
    }

    private function paymentRedirected(Payment $payment, string $url): void
    {
        $payment->setData('order_state', Order::STATE_PENDING_PAYMENT);

        $payment->setIsTransactionClosed(false);
        $payment->setIsTransactionPending(true);
        $payment->setAdditionalInformation(Config::REDIRECT_URL_KEY, $url);
    }

    private function paymentPendingApproval(Payment $payment): void
    {
        $payment->setIsTransactionClosed(false);
        $payment->setIsTransactionPending(false);
    }

    private function paymentPendingFraudApproval(Payment $payment): void
    {
        $payment->setIsTransactionClosed(false);
        $payment->setIsTransactionPending(true);
    }

    private function paymentCaptureRequested(Payment $payment): void
    {
        $payment->setIsTransactionClosed(true);
        $payment->setIsTransactionPending(false);
    }

    private function paymentNoop(): void
    {
    }
}
