<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Payment\Handler;

use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\Worldline\Status\Payment\HandlerInterface;
use Worldline\Connect\Sdk\V1\Domain\Payment;

/**
 * Class Captured
 *
 * @package Worldline\Connect\Model
 */
class Captured extends AbstractHandler implements HandlerInterface
{
    protected const EVENT_STATUS = 'captured';
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    public function __construct(
        ManagerInterface $eventManager,
        ConfigInterface $config,
        QuoteFactory $quoteFactory
    ) {
        parent::__construct($eventManager, $config);
        $this->quoteFactory = $quoteFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function resolveStatus(Order $order, Payment $status)
    {
        /** @var OrderPayment $orderPayment */
        $orderPayment = $order->getPayment();
        $orderPayment->setIsTransactionPending(false);
        $orderPayment->setIsTransactionClosed(true);

        $orderPayment->registerCaptureNotification($order->getBaseGrandTotal());


        if (!$order->getEmailSent()) {
            $order->setCanSendNewEmailFlag(true);
            $this->eventManager->dispatch(
                'sales_model_service_quote_submit_success',
                [
                    'order' => $order,
                    'quote' => $this->quoteFactory->create()->load($order->getQuoteId()),
                ]
            );
        }

        $this->dispatchEvent($order, $status);
    }
}
