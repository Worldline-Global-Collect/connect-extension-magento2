<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Action;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Worldline\Connect\Gateway\Command\CreatePaymentRequest\CreateHostedCheckoutRequestBuilder;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\StatusResponseManager;
use Worldline\Connect\Model\Transaction\TransactionManager;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Model\Worldline\StatusInterface;

class CreateHostedCheckout extends AbstractAction
{
    public function __construct(
        StatusResponseManager $statusResponseManager,
        ClientInterface $worldlineClient,
        TransactionManager $transactionManager,
        ConfigInterface $config,
        private readonly ClientInterface $client,
        private readonly CreateHostedCheckoutRequestBuilder $createHostedCheckoutRequestBuilder,
    ) {
        parent::__construct($statusResponseManager, $worldlineClient, $transactionManager, $config);
    }

    public function process(OrderPayment $payment, bool $requiresApproval): void
    {
        $payment->getOrder()->setCanSendNewEmailFlag(false);
        $storeId = $payment->getOrder()->getStoreId();

        $response = $this->client->createHostedCheckout(
            $this->createHostedCheckoutRequestBuilder->build($payment, $requiresApproval),
            $storeId
        );

        $url = $this->config->getHostedCheckoutSubDomain($storeId) . $response->partialRedirectUrl;

        $this->paymentRedirected($payment, $url);

        $payment->setTransactionId($response->hostedCheckoutId);

        $payment->setAdditionalInformation(Config::HOSTED_CHECKOUT_ID_KEY, $response->hostedCheckoutId);
        $payment->setAdditionalInformation(Config::RETURNMAC_KEY, $response->RETURNMAC);
    }

    public function processNew(QuotePayment $payment, bool $requiresApproval): void
    {
        $storeId = $payment->getQuote()->getStoreId();

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

        $getHostedCheckout = $this->client->getHostedCheckout($response->hostedCheckoutId);
        if ($getHostedCheckout->createdPaymentOutput) {
            $payment->setAdditionalInformation(Config::PAYMENT_ID_KEY, $getHostedCheckout->createdPaymentOutput->payment->id);
            $payment->setAdditionalInformation(Config::PAYMENT_STATUS_KEY, $getHostedCheckout->createdPaymentOutput->payment->status);
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
