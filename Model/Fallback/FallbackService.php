<?php

namespace Worldline\Connect\Model\Fallback;

use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use Worldline\Connect\Api\QuoteManagerInterface;
use Worldline\Connect\Api\WorldlineStatusCheckerInterface;
use Worldline\Connect\Model\Order\SkipOrderCreationException;

class FallbackService implements FallbackServiceInterface
{
    /** @var QuoteManagerInterface */
    private $quoteManager;

    /** @var WorldlineStatusCheckerInterface */
    private $statusChecker;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param QuoteManagerInterface $quoteManager
     * @param WorldlineStatusCheckerInterface $statusChecker
     * @param LoggerInterface $logger
     */
    public function __construct(
        QuoteManagerInterface $quoteManager,
        WorldlineStatusCheckerInterface $statusChecker,
        LoggerInterface $logger
    ) {
        $this->quoteManager = $quoteManager;
        $this->statusChecker = $statusChecker;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function process(): void
    {
        /** @var Quote[] $uncertainQuotes */
        $uncertainQuotes = $this->quoteManager->getUncertainWorldlineQuotes();

        foreach ($uncertainQuotes as $quote) {
            try {
                $order = $this->statusChecker->processUncertainQuote($quote);
            } catch (SkipOrderCreationException $e) {
                $this->logger->info($e->getMessage());
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }
}
