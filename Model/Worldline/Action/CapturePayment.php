<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Action;

use Magento\Sales\Model\Order\Payment;
use Worldline\Connect\Helper\Data;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\StatusResponseManager;
use Worldline\Connect\Model\Transaction\TransactionManager;
use Worldline\Connect\Model\Worldline\Api\ClientInterface;
use Worldline\Connect\Sdk\V1\Domain\ApprovePaymentRequestFactory;
use Worldline\Connect\Sdk\V1\Domain\OrderApprovePaymentFactory;
use Worldline\Connect\Sdk\V1\Domain\OrderReferencesApprovePaymentFactory;

class CapturePayment extends AbstractAction implements ActionInterface
{
    public function __construct(
        StatusResponseManager $statusResponseManager,
        ClientInterface $worldlineClient,
        TransactionManager $transactionManager,
        ConfigInterface $config,
        private readonly ApprovePaymentRequestFactory $approvePaymentRequestFactory,
        private readonly OrderApprovePaymentFactory $orderApprovePaymentFactory,
        private readonly OrderReferencesApprovePaymentFactory $orderReferencesApprovePaymentFactory,
    ) {
        parent::__construct(
            $statusResponseManager,
            $worldlineClient,
            $transactionManager,
            $config
        );
    }

    public function process(Payment $payment, mixed $amount): void
    {
        $order = $payment->getOrder();

        $orderReferencesApprovePayment = $this->orderReferencesApprovePaymentFactory->create();
        $orderReferencesApprovePayment->merchantReference = $order->getIncrementId();

        $orderApprovePayment = $this->orderApprovePaymentFactory->create();
        $orderApprovePayment->references = $orderReferencesApprovePayment;

        $approvePaymentRequest = $this->approvePaymentRequestFactory->create();
        $approvePaymentRequest->order = $orderApprovePayment;
        $approvePaymentRequest->amount = Data::formatWorldlineAmount($amount);

        $response = $this->worldlineClient->worldlinePaymentApprove(
            $payment->getLastTransId(),
            $approvePaymentRequest,
            $order->getStoreId()
        );

        $this->postProcess($payment, $response->payment);
    }
}
