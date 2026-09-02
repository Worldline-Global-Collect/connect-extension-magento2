<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Action;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Worldline\Connect\Gateway\Command\CreatePaymentRequest\CreateHostedCheckoutRequestBuilder;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\StatusResponseManager;
use Worldline\Connect\Model\Transaction\TransactionManager;
use Worldline\Connect\Model\Worldline\Api\ApiErrorClassifier;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\StatusInterface;
use Worldline\Connect\Sdk\V1\ResponseException;

use function __;

class CreateHostedCheckout extends AbstractAction
{
    private const NO_PAYMENT_METHODS_URL = 'epayments/returns/nopaymentmethods';

    public function __construct(
        StatusResponseManager $statusResponseManager,
        ClientInterface $worldlineClient,
        TransactionManager $transactionManager,
        ConfigInterface $config,
        private readonly ClientInterface $client,
        private readonly CreateHostedCheckoutRequestBuilder $createHostedCheckoutRequestBuilder,
        private readonly ApiErrorClassifier $apiErrorClassifier,
        private readonly UrlInterface $urlBuilder,
    ) {
        parent::__construct($statusResponseManager, $worldlineClient, $transactionManager, $config);
    }

    public function process(OrderPayment $payment, bool $requiresApproval): void
    {
        $payment->getOrder()->setCanSendNewEmailFlag(false);
        $storeId = $payment->getOrder()->getStoreId();

        try {
            $response = $this->client->createHostedCheckout(
                $this->createHostedCheckoutRequestBuilder->build($payment, $requiresApproval),
                $storeId
            );
        } catch (ResponseException $e) {
            if (!$this->apiErrorClassifier->isNoPaymentProductsAvailable($e)) {
                throw $e;
            }

            // Thrown as a LocalizedException on purpose: that is the only exception type Magento's
            // place-order error handling forwards to the shopper. Any other type is replaced by a
            // generic "A server error stopped your order from being placed" message.
            // Keep the wording identical to Controller/Returns/NoPaymentMethods.php.
            throw new LocalizedException(__(
                'We\'re sorry, but we cannot process this order. Please try adjusting your cart total or'
                . ' selecting a different payment method. If you continue to experience issues, please'
                . ' contact our support team.'
            ));
        }

        $url = $this->config->getHostedCheckoutSubDomain($storeId) . $response->partialRedirectUrl;

        $this->paymentRedirected($payment, $url);

        $payment->setTransactionId($response->hostedCheckoutId);

        $payment->setAdditionalInformation(Config::HOSTED_CHECKOUT_ID_KEY, $response->hostedCheckoutId);
        $payment->setAdditionalInformation(Config::RETURNMAC_KEY, $response->RETURNMAC);
    }

    public function processNew(QuotePayment $payment, bool $requiresApproval): void
    {
        $storeId = $payment->getQuote()->getStoreId();

        try {
            $response = $this->client->createHostedCheckout(
                $this->createHostedCheckoutRequestBuilder->buildNew($payment, $requiresApproval),
                $storeId
            );

            $url = $this->config->getHostedCheckoutSubDomain($storeId) . $response->partialRedirectUrl;

            $this->paymentRedirectedNew($payment, $url);

            $payment->setTransactionId($response->hostedCheckoutId);

            $payment->setAdditionalInformation(Config::HOSTED_CHECKOUT_ID_KEY, $response->hostedCheckoutId);
            $payment->setAdditionalInformation(Config::HOSTED_CHECKOUT_ID_TIMESTAMP_KEY, time());
            $payment->setAdditionalInformation(Config::RETURNMAC_KEY, $response->RETURNMAC);

            $this->persistCreatedPaymentNew($payment, $response->hostedCheckoutId);
        } catch (ResponseException $e) {
            if (!$this->apiErrorClassifier->isNoPaymentProductsAvailable($e)) {
                throw $e;
            }

            $this->paymentUnavailableNew($payment);
        }
    }

    /**
     * Deliberately leaves the hosted checkout / payment identifiers unset: AuthorizeCommand's
     * reusesExistingPayment() guard keys off them, so a retry after the shopper fixes the cart
     * must create a fresh hosted checkout rather than reuse this failed attempt.
     */
    private function paymentUnavailableNew(QuotePayment $payment): void
    {
        $payment->setAdditionalInformation(
            Config::REDIRECT_URL_KEY,
            $this->urlBuilder->getUrl(self::NO_PAYMENT_METHODS_URL)
        );
    }

    private function persistCreatedPaymentNew(QuotePayment $payment, string $hostedCheckoutId): void
    {
        $getHostedCheckout = $this->client->getHostedCheckout($hostedCheckoutId);
        if ($getHostedCheckout->createdPaymentOutput) {
            $createdPayment = $getHostedCheckout->createdPaymentOutput->payment;

            $payment->setAdditionalInformation(Config::PAYMENT_ID_KEY, $createdPayment->id);
            $payment->setAdditionalInformation(Config::PAYMENT_STATUS_KEY, $createdPayment->status);
        } else {
            $payment->setAdditionalInformation(Config::PAYMENT_STATUS_KEY, StatusInterface::CREATED);
        }
    }

    private function paymentRedirected(OrderPayment $payment, string $url): void
    {
        $payment->setData('order_state', Order::STATE_PENDING_PAYMENT);

        $payment->setIsTransactionClosed(false);
        $payment->setIsTransactionPending(true);
        $payment->setAdditionalInformation(Config::REDIRECT_URL_KEY, $url);
    }

    private function paymentRedirectedNew(QuotePayment $payment, string $url): void
    {
        $payment->setIsTransactionClosed(false);
        $payment->setIsTransactionPending(true);
        $payment->setAdditionalInformation(Config::REDIRECT_URL_KEY, $url);
    }
}
