<?php
declare(strict_types=1);

namespace Worldline\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Worldline\Connect\Api\QuoteRestorationInterface;

/**
 * Event controller_action_predispatch_checkout_index_index
 */
class ShiftReservedQuoteId implements ObserverInterface
{
    /**
     * @var QuoteRestorationInterface
     */
    private $quoteRestoration;

    public function __construct(
        QuoteRestorationInterface $quoteRestoration
    ) {
        $this->quoteRestoration = $quoteRestoration;
    }

    /**
     * @param Observer $observer
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        $this->quoteRestoration->shiftQuoteId();
    }
}
