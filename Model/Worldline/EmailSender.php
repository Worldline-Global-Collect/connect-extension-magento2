<?php

namespace Worldline\Connect\Model\Worldline;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Observer\SendInvoiceEmailObserver;
use Magento\Quote\Observer\SubmitObserver;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;

class EmailSender
{
    /**
     * @var SendInvoiceEmailObserver
     */
    private $sendInvoiceEmailObserver;
    /**
     * @var SubmitObserver
     */
    private $submitObserver;
    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @param SendInvoiceEmailObserver $sendInvoiceEmailObserver
     * @param SubmitObserver $submitObserver
     * @param InvoiceRepositoryInterface $invoiceRepository
     */
    public function __construct(
        SendInvoiceEmailObserver $sendInvoiceEmailObserver,
        SubmitObserver $submitObserver,
        InvoiceRepositoryInterface $invoiceRepository
    ) {
        $this->sendInvoiceEmailObserver = $sendInvoiceEmailObserver;
        $this->submitObserver = $submitObserver;
        $this->invoiceRepository = $invoiceRepository;
    }

    public function sendEmails(Order $order, Quote $quote): void
    {
        $order->setCanSendNewEmailFlag(true);

        $this->sendOrderEmail($order, $quote);
        $this->sendInvoiceEmail($order, $quote);
    }

    private function sendOrderEmail(Order $order, Quote $quote): void
    {
        if ($order->getEmailSent()) {
            return;
        }

        $order->setCanSendNewEmailFlag(true);

        $observer = new Observer();
        $event = new Event();
        $event->setData([
            'order' => $order,
            'quote' => $quote
        ]);
        $observer->setEvent($event);

        $this->submitObserver->execute($observer);
    }

    private function sendInvoiceEmail(Order $order, Quote $quote): void
    {
        /** @var Order\Invoice $invoice */
        $invoice = current($order->getInvoiceCollection()->getItems());

        if (!$invoice || $invoice->getEmailSent()) {
            return;
        }

        $order->setCanSendNewEmailFlag(true);

        $observer = new Observer();
        $event = new Event();
        $event->setData([
            'order' => $order,
            'quote' => $quote
        ]);
        $observer->setEvent($event);

        $this->invoiceRepository->save($invoice);

        $this->sendInvoiceEmailObserver->execute($observer);
    }
}
