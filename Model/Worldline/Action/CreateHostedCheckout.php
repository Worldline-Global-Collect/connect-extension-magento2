<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Action;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Gateway\Command\CreatePaymentRequest\CreateHostedCheckoutRequestBuilder;
use Worldline\Connect\Model\Config;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\StatusResponseManager;
use Worldline\Connect\Model\Transaction\TransactionManager;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;

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

    public function process(Payment $payment, bool $requiresApproval): void
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

    private function paymentRedirected(Payment $payment, string $url): void
    {
        $payment->setData('order_state', Order::STATE_PENDING_PAYMENT);

        $payment->setIsTransactionClosed(false);
        $payment->setIsTransactionPending(true);
        $payment->setAdditionalInformation(Config::REDIRECT_URL_KEY, $url);
    }
}
