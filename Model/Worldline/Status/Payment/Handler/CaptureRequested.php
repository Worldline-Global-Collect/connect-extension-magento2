<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Payment\Handler;

use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\Worldline\Status\Payment\HandlerInterface;
use Worldline\Connect\Model\Worldline\Token\TokenService;
use Worldline\Connect\Sdk\V1\Domain\Payment;

class CaptureRequested extends AbstractHandler implements HandlerInterface
{
    protected const EVENT_STATUS = 'capture_requested';

    /**
     * @var TokenService
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
    private $tokenService;
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    public function __construct(
        ManagerInterface $eventManager,
        ConfigInterface $config,
        TokenService $tokenService,
        QuoteFactory $quoteFactory
    ) {
        parent::__construct($eventManager, $config);
        $this->tokenService = $tokenService;
        $this->quoteFactory = $quoteFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function resolveStatus(Order $order, Payment $status)
    {
        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus(Order::STATE_PROCESSING);

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

        $this->tokenService->createByOrderAndPayment($order, $status);

        $this->dispatchEvent($order, $status);
    }
}
