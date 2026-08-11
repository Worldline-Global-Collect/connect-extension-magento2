<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Action;

use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Gateway\Command\ApiErrorHandler;
use Worldline\Connect\Gateway\Command\CreatePaymentRequestBuilder;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\Order\OrderServiceInterface;
use Worldline\Connect\Model\StatusResponseManager;
use Worldline\Connect\Model\Transaction\TransactionManager;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\StatusInterface;
use Worldline\Connect\Model\Worldline\Token\TokenService;
use Worldline\Connect\Sdk\V1\DeclinedPaymentException;
use Worldline\Connect\Sdk\V1\Domain\CreatePaymentResponse;
use Worldline\Connect\Sdk\V1\Domain\CreatePaymentResult;
use Worldline\Connect\Sdk\V1\ResponseException;

use function in_array;

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
        private readonly OrderServiceInterface $orderService,
        private readonly UrlInterface $urlBuilder,
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

            if (in_array($response->payment->status, StatusInterface::APPROVED_STATUSES, true)) {
                if ($payment->getOrder()->getEmailSent()) {
                    $payment->getOrder()->setCanSendNewEmailFlag(false);
                }

                $payment->registerCaptureNotification($payment->getOrder()->getBaseGrandTotal());
                $payment->setData('is_transaction_approved', true);
            }

            if ($response->payment->status === StatusInterface::REDIRECTED) {
                $payment->getOrder()->setCanSendNewEmailFlag(false);
                $payment->setIsTransactionPending(true);
                $payment->registerAuthorizationNotification($payment->getOrder()->getBaseGrandTotal());
            }

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

    public function processNew(QuotePayment $payment, bool $requiresApproval): void
    {
        try {
            $request = $this->createPaymentRequestBuilder->buildNew($payment, $requiresApproval);
            $response = $this->worldlineClient->createPayment(
                $request,
                $payment->getQuote()->getStoreId()
            );

            $this->persistResponseDataNew($payment, $response);

            if ($this->shouldRedirect($response)) {
                $this->persistRedirectDataNew($payment, $response);
                return;
            }

            $this->orderService->createOrderAndProcessStatus(
                $payment->getQuote(),
                $response->payment
            );

            $this->persistSyncRedirectUrl($payment, $response);
        } catch (DeclinedPaymentException $e) {
            $this->persistResponseDataNew($payment, $e->getCreatePaymentResult());
            $payment->setAdditionalInformation(
                Config::REDIRECT_URL_KEY,
                $this->urlBuilder->getUrl('epayments/returns/reject')
            );
        } catch (ResponseException $e) {
            $this->apiErrorHandler->handleError($e);
        }
    }

    /**
     * For the synchronous-outcome branch (frictionless 3DS or non-3DS), the JS expects a URL
     * to navigate to after the REST call. Set a Magento success or reject URL depending on
     * whether the synchronously-received Worldline status is final-denied or accepted.
     */
    private function persistSyncRedirectUrl(QuotePayment $payment, CreatePaymentResponse $response): void
    {
        $isDenied = in_array(
            $response->payment->status,
            StatusInterface::DENIED_STATUSES,
            true
        );

        $payment->setAdditionalInformation(
            Config::REDIRECT_URL_KEY,
            $this->urlBuilder->getUrl(
                $isDenied ? 'epayments/returns/reject' : 'checkout/onepage/success'
            )
        );
    }

    private function persistResponseDataNew(QuotePayment $payment, CreatePaymentResult $response): void
    {
        $payment->setAdditionalInformation(Config::PAYMENT_ID_KEY, $response->payment->id);
        $payment->setAdditionalInformation(Config::PAYMENT_STATUS_KEY, $response->payment->status);
        $payment->setAdditionalInformation(
            Config::PAYMENT_STATUS_CODE_KEY,
            $response->payment->statusOutput?->statusCode
        );
    }

    private function shouldRedirect(CreatePaymentResponse $response): bool
    {
        if ($response->payment->status === StatusInterface::REDIRECTED) {
            return true;
        }

        $redirectUrl = $response->merchantAction?->redirectData?->redirectURL ?? '';

        return $redirectUrl !== '';
    }

    private function persistRedirectDataNew(QuotePayment $payment, CreatePaymentResponse $response): void
    {
        $redirectData = $response->merchantAction?->redirectData;
        if ($redirectData === null) {
            return;
        }

        $payment->setAdditionalInformation(Config::REDIRECT_URL_KEY, $redirectData->redirectURL);

        if ($redirectData->RETURNMAC !== null) {
            $payment->setAdditionalInformation(Config::RETURNMAC_KEY, $redirectData->RETURNMAC);
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
