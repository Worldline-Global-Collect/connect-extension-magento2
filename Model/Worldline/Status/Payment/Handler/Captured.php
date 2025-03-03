<?php

declare(strict_types=1);

namespace Worldline\Connect\Model\Worldline\Status\Payment\Handler;

use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Worldline\Connect\Model\ConfigInterface;
use Worldline\Connect\Model\Worldline\EmailSender;
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
     * @var EmailSender
     */
    private $emailSender;
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    public function __construct(
        ManagerInterface $eventManager,
        ConfigInterface $config,
        EmailSender $emailSender,
        CartRepositoryInterface $quoteRepository
    ) {
        parent::__construct($eventManager, $config);
        $this->emailSender = $emailSender;
        $this->quoteRepository = $quoteRepository;
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

        if ($order->getEmailSent()) {
            $order->setCanSendNewEmailFlag(false);
        }

        $orderPayment->registerCaptureNotification($order->getBaseGrandTotal());

        if (!$order->getEmailSent()) {
            $order->setCanSendNewEmailFlag(true);
            $this->emailSender->sendEmails($order, $this->quoteRepository->get($order->getQuoteId()));
        }

        $this->dispatchEvent($order, $status);
    }
}
