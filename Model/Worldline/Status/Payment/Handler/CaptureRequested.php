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
        TokenService $tokenService,
        EmailSender $emailSender,
        CartRepositoryInterface $quoteRepository
    ) {
        parent::__construct($eventManager, $config);
        $this->tokenService = $tokenService;
        $this->emailSender = $emailSender;
        $this->quoteRepository = $quoteRepository;
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

        if ($order->getEmailSent()) {
            $order->setCanSendNewEmailFlag(false);
        }

        $orderPayment->registerCaptureNotification($order->getBaseGrandTotal());

        if (!$order->getEmailSent()) {
            $order->setCanSendNewEmailFlag(true);
            $this->emailSender->sendEmails($order, $this->quoteRepository->get($order->getQuoteId()));
        }

        $this->tokenService->createByOrderAndPayment($order, $status);

        $this->dispatchEvent($order, $status);
    }
}
