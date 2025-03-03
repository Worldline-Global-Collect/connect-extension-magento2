<?php

namespace Worldline\Connect\Model\Worldline;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Observer\SendInvoiceEmailObserver;
use Magento\Quote\Observer\SubmitObserver;
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
     * @param SendInvoiceEmailObserver $sendInvoiceEmailObserver
     * @param SubmitObserver $submitObserver
     */
    public function __construct(SendInvoiceEmailObserver $sendInvoiceEmailObserver, SubmitObserver $submitObserver)
    {
        $this->sendInvoiceEmailObserver = $sendInvoiceEmailObserver;
        $this->submitObserver = $submitObserver;
    }

    public function sendEmails(Order $order, Quote $quote): void
    {
        $observer = new Observer();
        $event = new Event();
        $event->setData([
            'order' => $order,
            'quote' => $quote
        ]);
        $observer->setEvent($event);

        $this->submitObserver->execute($observer);
        $this->sendInvoiceEmailObserver->execute($observer);
    }
}
